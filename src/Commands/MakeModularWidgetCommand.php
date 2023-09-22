<?php

namespace RealMrHex\FilamentModularV3\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeModularWidgetCommand extends Command
{
    use CanManipulateFiles;

    protected $description = 'Create a new Filament widget class';

    protected $signature = 'module:make-filament-widget {module?} {name?} {--R|resource=} {--C|chart} {--T|table} {--S|stats-overview} {--panel=} {--F|force}';

    public function handle(): int
    {

        FindModule:
        try {
            $module_name = $this->argument('module') ?? text(
                label      : 'What is the module name?',
                placeholder: 'User',
                required   : true,
            );
            $module_name = Str::of($module_name)->studly()->toString();
            $module = app('modules')->findOrFail($module_name);
        } catch (Throwable) {
            $this->components->warn('Module not found, make sure module exists and try again.');

            goto FindModule;
        }

        $widget = (string) str($this->argument('name') ?? text(
            label: 'What is the widget name?',
            placeholder: 'BlogPostsChart',
            required: true,
        ))
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');
        $widgetClass = (string) str($widget)->afterLast('\\');
        $widgetNamespace = str($widget)->contains('\\') ?
            (string) str($widget)->beforeLast('\\') :
            '';

        $resource = null;
        $resourceClass = null;

        if (class_exists(Resource::class)) {
            $resourceInput = $this->option('resource') ?? text(
                label: 'Would you like to create the widget inside a resource?',
                placeholder: '[Optional] BlogPostResource',
            );

            if (filled($resourceInput)) {
                $resource = (string) str($resourceInput)
                    ->studly()
                    ->trim('/')
                    ->trim('\\')
                    ->trim(' ')
                    ->replace('/', '\\');

                if (! str($resource)->endsWith('Resource')) {
                    $resource .= 'Resource';
                }

                $resourceClass = (string) str($resource)
                    ->afterLast('\\');
            }
        }

        $panel = null;

        if (class_exists(Panel::class)) {
            $panel = $this->option('panel');

            if ($panel) {
                $panel = Filament::getPanel($panel);
            }

            if (! $panel) {
                $panels = Filament::getPanels();

                /** @var ?Panel $panel */
                $panel = $panels[select(
                    label: 'Where would you like to create this?',
                    options: array_unique([
                        ...array_map(
                            fn (Panel $panel): string => "The [{$panel->getId()}] panel",
                            $panels,
                        ),
                        '' => '[App\\Livewire] alongside other Livewire components',
                    ]),
                )] ?? null;
            }
        }

        $path = null;
        $namespace = null;
        $resourcePath = null;
        $resourceNamespace = null;

        $panelId = $panel ? (string) str($panel->getId())->studly() : null;

        if (! $panel) {
            $path = $module->getPath().'/Livewire/';
            $namespace = "\\{$module->getName()}\\Livewire";
        } elseif ($resource === null) {
            $widgetDirectories = $panel->getWidgetDirectories();
            $widgetNamespaces = $panel->getWidgetNamespaces();

            $widgetModuleDirectory = $module->getPath().'/Filament/Widgets/';
            $widgetModuleDirectoryWithPanel = $module->getPath()."/Filament/$panelId/Widgets/";

            $moduleNamespace = config('modules.namespace')."\\{$module->getName()}\Filament\\Widgets";
            $moduleNamespaceWithPanel = config('modules.namespace')."\\{$module->getName()}\Filament\\$panelId\Widgets";

            $namespace = count($widgetNamespaces) > 0 ? $moduleNamespaceWithPanel : $moduleNamespace;
            $path = count($widgetDirectories) > 0 ? $widgetModuleDirectoryWithPanel : $widgetModuleDirectory;

        } else {
            $resourceDirectories = $panel->getResourceDirectories();
            $resourceNamespaces = $panel->getResourceNamespaces();

            $resourceModuleDirectory = $module->getPath()."/Filament/$panelId/Resources/";
            $moduleNamespaceWithPanel = config('modules.namespace')."\\{$module->getName()}\Filament\\$panelId";

            $resourceNamespace = (count($resourceNamespaces) > 1) ?
                select(
                    label: 'Which namespace would you like to create this in?',
                    options: $resourceNamespaces,
                ) :
                (Arr::first($resourceNamespaces) ?? $moduleNamespaceWithPanel);
            $resourcePath = (count($resourceDirectories) > 1) ?
                $resourceDirectories[array_search($resourceNamespace, $resourceNamespaces)] :
                (Arr::first($resourceDirectories) ?? $resourceModuleDirectory);
        }

        $view = str($widget)->prepend(
            (string) str($resource === null ? ($panel ? "{$namespace}\\" : 'livewire\\') : "{$resourceNamespace}\\{$resource}\\widgets\\")
                ->replaceFirst('App\\', '')
        )
            ->replace('\\', '/')
            ->explode('/')
            ->map(fn ($segment) => Str::lower(Str::kebab($segment)))
            ->implode('.');

        $path = (string) str($widget)
            ->prepend('/')
            ->prepend($resource === null ? $path : "{$resourcePath}\\{$resource}\\Widgets\\")
            ->replace('\\', '/')
            ->replace('//', '/')
            ->append('.php');

        $viewPath = $module->getPath().'/Resources/'.
            (string) str($view)
                ->replace('.', '/')
                ->prepend('views/')
                ->append('.blade.php');

        if (! $this->option('force') && $this->checkForCollision([
            $path,
            ...($this->option('stats-overview') || $this->option('chart')) ? [] : [$viewPath],
        ])) {
            return static::INVALID;
        }

        if ($this->option('chart')) {
            $chart = select(
                label: 'Which type of chart would you like to create?',
                options: [
                    'Bar chart',
                    'Bubble chart',
                    'Doughnut chart',
                    'Line chart',
                    'Pie chart',
                    'Polar area chart',
                    'Radar chart',
                    'Scatter chart',
                ],
            );

            $this->copyStubToApp('ChartWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? "{$resourceNamespace}\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $namespace.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
                'type' => match ($chart) {
                    'Bar chart' => 'bar',
                    'Bubble chart' => 'bubble',
                    'Doughnut chart' => 'doughnut',
                    'Pie chart' => 'pie',
                    'Polar area chart' => 'polarArea',
                    'Radar chart' => 'radar',
                    'Scatter chart' => 'scatter',
                    default => 'line',
                },
            ]);
        } elseif ($this->option('table')) {
            $this->copyStubToApp('TableWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? "{$resourceNamespace}\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $namespace.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
            ]);
        } elseif ($this->option('stats-overview')) {
            $this->copyStubToApp('StatsOverviewWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? "{$resourceNamespace}\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $namespace.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
            ]);
        } else {
            $this->copyStubToApp('Widget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? "{$resourceNamespace}\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $namespace.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
                'view' => $view,
            ]);

            $this->copyStubToApp('WidgetView', $viewPath);
        }

        $this->components->info("Successfully created {$widget}!");

        if ($resource !== null) {
            $this->components->info("Make sure to register the widget in `{$resourceClass}::getWidgets()`, and then again in `getHeaderWidgets()` or `getFooterWidgets()` of any `{$resourceClass}` page.");
        }

        return static::SUCCESS;
    }
}
