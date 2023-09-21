<?php

namespace RealMrHex\FilamentModularV3\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeModularPageCommand extends Command
{
    use CanManipulateFiles;

    protected $description = 'Create a new Filament page class and view';

    protected $signature = 'module:make-filament-page {module?} {name?} {--R|resource=} {--T|type=} {--panel=} {--F|force}';

    public function handle(): int
    {
        FindModule:
        try
        {
            $module_name = $this->argument('module') ?? text(
                label      : 'What is the module name?',
                placeholder: 'User',
                required   : true,
            );
            $module_name = Str::of($module_name)->studly()->toString();
            $module = app('modules')->findOrFail($module_name);
        }
        catch (Throwable)
        {
            $this->components->warn('Module not found, make sure module exists and try again.');

            goto FindModule;
        }

        $page = (string)str(
            $this->argument('name') ??
            text(
                label      : 'What is the page name?',
                placeholder: 'EditSettings',
                required   : true,
            ),
        )
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\')
        ;
        $pageClass = (string)str($page)->afterLast('\\');
        $pageNamespace = str($page)->contains('\\')
            ?
            (string)str($page)->beforeLast('\\')
            :
            '';

        $resource = null;
        $resourceClass = null;
        $resourcePage = null;

        $resourceInput = $this->option('resource') ?? text(
            label      : 'Would you like to create the page inside a resource?',
            placeholder: '[Optional] UserResource',
        );

        if (filled($resourceInput))
        {
            $resource = (string)str($resourceInput)
                ->studly()
                ->trim('/')
                ->trim('\\')
                ->trim(' ')
                ->replace('/', '\\')
            ;

            if (!str($resource)->endsWith('Resource'))
            {
                $resource .= 'Resource';
            }

            $resourceClass = (string)str($resource)
                ->afterLast('\\')
            ;

            $resourcePage = $this->option('type') ?? select(
                label  : 'Which type of page would you like to create?',
                options: [
                             'custom'        => 'Custom',
                             'ListRecords'   => 'List',
                             'CreateRecord'  => 'Create',
                             'EditRecord'    => 'Edit',
                             'ViewRecord'    => 'View',
                             'ManageRecords' => 'Manage',
                         ],
                default: 'custom'
            );
        }

        $panel = $this->option('panel');

        if ($panel)
        {
            $panel = Filament::getPanel($panel);
        }

        if (!$panel)
        {
            $panels = Filament::getPanels();

            /** @var Panel $panel */
            $panel = (count($panels) > 1) ? $panels[select(
                label  : 'Which panel would you like to create this in?',
                options: array_map(
                             fn(Panel $panel): string => $panel->getId(),
                             $panels,
                         ),
                default: Filament::getDefaultPanel()->getId()
            )] : Arr::first($panels);
        }

        $panelId = (string)str($panel->getId())->studly();

        if (empty($resource))
        {
            $pageDirectories = $panel->getPageDirectories();
            $pageNamespaces = $panel->getPageNamespaces();

            $pageModuleDirectory = $module->getPath() . "/Filament/$panelId/Pages/";
            $moduleNamespace = config('modules.namespace') . "\\{$module->getName()}\Filament\\$panelId\Pages";

            $namespace = (count($pageNamespaces) > 1)
                ?
                select(
                    label  : 'Which namespace would you like to create this in?',
                    options: $pageNamespaces
                )
                :
                (Arr::first($pageNamespaces) ?? $moduleNamespace);
            $path = (count($pageDirectories) > 1)
                ?
                $pageDirectories[array_search($namespace, $pageNamespaces)]
                :
                (Arr::first($pageDirectories) ?? $pageModuleDirectory);
        }
        else
        {
            $resourceDirectories = $panel->getResourceDirectories();
            $resourceNamespaces = $panel->getResourceNamespaces();

            $resourceModuleDirectory = $module->getPath() . "/Filament/$panelId/Resources/";
            $moduleNamespace = config('modules.namespace') . "\\{$module->getName()}\Filament\\$panelId\Resources";

            $resourceNamespace = (count($resourceNamespaces) > 1)
                ?
                select(
                    label  : 'Which namespace would you like to create this in?',
                    options: $resourceNamespaces
                )
                :
                (Arr::first($resourceNamespaces) ?? $moduleNamespace);
            $resourcePath = (count($resourceDirectories) > 1)
                ?
                $resourceDirectories[array_search($resourceNamespace, $resourceNamespaces)]
                :
                (Arr::first($resourceDirectories) ?? $resourceModuleDirectory);
        }


        $view = str($page)
            ->prepend(
                (string)str(empty($resource) ? "$namespace\\" : "$resourceNamespace\\$resource\\pages\\")
                    ->replaceFirst('App\\', '')
            )
            ->replace('\\', '/')
            ->explode('/')
            ->map(fn($segment) => Str::lower(Str::kebab($segment)))
            ->implode('.')
        ;

        $path = (string)str($page)
            ->prepend('/')
            ->prepend(empty($resource) ? ($path ?? '') : ($resourcePath ?? '') . "\\$resource\\Pages\\")
            ->replace('\\', '/')
            ->replace('//', '/')
            ->append('.php')
        ;

        $viewPath = resource_path(
            (string)str($view)
                ->replace('.', '/')
                ->prepend('views/')
                ->append('.blade.php'),
        );

        $files = [
            $path,
            ...($resourcePage === 'custom' ? [$viewPath] : []),
        ];

        if (!$this->option('force') && $this->checkForCollision($files))
        {
            return static::INVALID;
        }

        if (empty($resource))
        {
            $this->copyStubToApp('Page', $path, [
                'class'     => $pageClass,
                'namespace' => str($namespace ?? '') . ($pageNamespace !== '' ? "\\$pageNamespace" : ''),
                'view'      => $view,
            ]);
        }
        else
        {
            $this->copyStubToApp($resourcePage === 'custom' ? 'CustomResourcePage' : 'ResourcePage', $path, [
                'baseResourcePage'      => 'Filament\\Resources\\Pages\\' . ($resourcePage === 'custom' ? 'Page' : $resourcePage),
                'baseResourcePageClass' => $resourcePage === 'custom' ? 'Page' : $resourcePage,
                'namespace'             => ($resourceNamespace ?? '') . "\\$resource\\Pages" . ($pageNamespace !== '' ? "\\$pageNamespace" : ''),
                'resource'              => ($resourceNamespace ?? '') . "\\$resource",
                'resourceClass'         => $resourceClass,
                'resourcePageClass'     => $pageClass,
                'view'                  => $view,
            ]);
        }

        if (empty($resource) || $resourcePage === 'custom')
        {
            $this->copyStubToApp('PageView', $viewPath);
        }

        $this->components->info("Successfully created $page!");

        if ($resource !== null)
        {
            $this->components->info("Make sure to register the page in `$resourceClass::getPages()`.");
        }

        return static::SUCCESS;
    }
}
