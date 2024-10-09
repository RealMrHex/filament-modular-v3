<?php

namespace RealMrHex\FilamentModularV3;

use Filament\Panel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Nwidart\Modules\Laravel\Module;
use RealMrHex\FilamentModularV3\Commands\{
    MakeModularPageCommand,
    MakeModularRelationManagerCommand,
    MakeModularResourceCommand,
    MakeModularWidgetCommand
};
use RealMrHex\FilamentModularV3\Testing\TestsFilamentModularV3;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentModularV3ServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-modular-v3';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command->askToStarRepoOnGitHub('realmrhex/filament-modular-v3');
            });

        if (file_exists($package->basePath("/../config/{$package->shortName()}.php"))) {
            $package->hasConfigFile();
        }
    }

    public function packageRegistered(): void
    {
        $this->app->beforeResolving('filament', fn () => $this->addModularFunctionality());
        $this->registerModuleDiscoveryMacros();
    }

    private function addModularFunctionality(): void
    {
        $modules = $this->app['modules']->allEnabled();

        foreach ($modules as $module) {
            // Discover panels
            $this->discoverPanels($module);

            // Register module configurations
            if (!file_exists(base_path('bootstrap/cache/config.php'))) {
                $this->registerConfigs($module);
            }
        }
    }

    private function discoverPanels(Module $module): void
    {
        $providersDir = "{$module->getPath()}/Providers/Filament/Panels";

        if (!is_dir($providersDir)) {
            return;
        }

        $providers = scandir($providersDir);

        foreach ($providers as $provider) {
            if (preg_match('/^(.+)\.php$/', $provider, $matches)) {
                $providerClass = "Modules\\{$module->getStudlyName()}\\Providers\\Filament\\Panels\\{$matches[1]}";
                if (class_exists($providerClass)) {
                    $this->app->register($providerClass);
                }
            }
        }
    }

    private function registerModuleDiscoveryMacros(): void
    {
        $this->discoverResourcesMacro();
        $this->discoverPagesMacro();
        $this->discoverWidgetsMacro();
    }

    private function discoverResourcesMacro(): void
    {
        Panel::macro('discoverModulesResources', function () {
            $panelId = Str::of($this->getId())->studly();
            $cacheKey = "filament_module_v3_resources_{$panelId}";
            $cacheEnabled = config('filament-modular-v3.enable_auto_discover_cache', false) || app()->isProduction();

            $discoverResourcesFromDirectory = function (string $directory, string $namespace) {
                $resourcesList = [];

                if (!is_dir($directory)) {
                    return $resourcesList;
                }

                foreach (scandir($directory) as $resource) {
                    if (preg_match('/^(.+)\.php$/', $resource, $matches)) {
                        $resourceClass = "{$namespace}\\{$matches[1]}";
                        if (class_exists($resourceClass)) {
                            $resourcesList[] = $resourceClass;
                        }
                    }
                }

                return $resourcesList;
            };

            $getResourcesList = function () use ($panelId, $discoverResourcesFromDirectory) {
                $resourcesList = [];
                $modules = app('modules')->allEnabled();

                foreach ($modules as $module) {
                    $baseNamespace = "Modules\\{$module->getStudlyName()}\\Filament\\{$panelId}";
                    $resourcesDir = "{$module->getPath()}/Filament/{$panelId}/Resources";

                    $resourcesList = array_merge($resourcesList, $discoverResourcesFromDirectory($resourcesDir, $baseNamespace . '\\Resources'));
                }

                return $resourcesList;
            };

            $resourcesList = $cacheEnabled
                ? Cache::rememberForever($cacheKey, $getResourcesList)
                : $getResourcesList();

            $this->resources = array_merge($this->resources, $resourcesList);

            return $this;
        });
    }

    private function discoverPagesMacro(): void
    {
        Panel::macro('discoverModulesPages', function () {
            $panelId = Str::of($this->getId())->studly();
            $cacheKey = "filament_module_v3_pages_{$panelId}";
            $cacheEnabled = config('filament-modular-v3.enable_auto_discover_cache', false) || app()->isProduction();

            $discoverPagesFromDirectory = function (string $directory, string $namespace) {
                $pagesList = [];

                if (!is_dir($directory)) {
                    return $pagesList;
                }

                foreach (scandir($directory) as $page) {
                    if (preg_match('/^(.+)\.php$/', $page, $matches)) {
                        $pageClass = "{$namespace}\\{$matches[1]}";
                        if (class_exists($pageClass)) {
                            $pagesList[] = $pageClass;
                        }
                    }
                }

                return $pagesList;
            };

            $getPagesList = function () use ($panelId, $discoverPagesFromDirectory) {
                $pagesList = [];
                $modules = app('modules')->allEnabled();

                foreach ($modules as $module) {
                    $baseNamespace = "Modules\\{$module->getStudlyName()}\\Filament\\{$panelId}";
                    $pagesDir = "{$module->getPath()}/Filament/{$panelId}/Pages";

                    $pagesList = array_merge($pagesList, $discoverPagesFromDirectory($pagesDir, $baseNamespace . '\\Pages'));
                }

                return $pagesList;
            };

            $pagesList = $cacheEnabled
                ? Cache::rememberForever($cacheKey, $getPagesList)
                : $getPagesList();

            $this->pages = array_merge($this->pages, $pagesList);

            foreach ($pagesList as $page) {
                $this->queueLivewireComponentForRegistration($page);
            }

            return $this;
        });
    }


    private function discoverWidgetsMacro(): void
    {
        Panel::macro('discoverModulesWidgets', function () {
            $panelId = Str::of($this->getId())->studly();
            $cacheKey = "filament_module_v3_widgets_{$panelId}";
            $cacheEnabled = config('filament-modular-v3.enable_auto_discover_cache', false) || app()->isProduction();

            $discoverWidgetsFromDirectory = function (string $directory, string $namespace) {
                $widgetsList = [];

                if (!is_dir($directory)) {
                    return $widgetsList;
                }

                foreach (scandir($directory) as $widget) {
                    if (preg_match('/^(.+)\.php$/', $widget, $matches)) {
                        $widgetClass = "{$namespace}\\{$matches[1]}";
                        if (class_exists($widgetClass)) {
                            $widgetsList[] = $widgetClass;
                        }
                    }
                }

                return $widgetsList;
            };

            $getWidgetList = function () use ($panelId, $discoverWidgetsFromDirectory) {
                $widgetsList = [];
                $modules = app('modules')->allEnabled();

                foreach ($modules as $module) {
                    $baseNamespace = "Modules\\{$module->getStudlyName()}\\Filament\\{$panelId}";

                    $widgetsDir = "{$module->getPath()}/Filament/{$panelId}/Widgets";
                    $widgetsList = array_merge($widgetsList, $discoverWidgetsFromDirectory($widgetsDir, $baseNamespace . '\\Widgets'));

                    $resourcesDir = "{$module->getPath()}/Filament/{$panelId}/Resources";
                    if (is_dir($resourcesDir)) {
                        foreach (scandir($resourcesDir) as $resource) {
                            $resourceWidgetsDir = "{$resourcesDir}/{$resource}/Widgets";
                            $widgetsList = array_merge($widgetsList, $discoverWidgetsFromDirectory($resourceWidgetsDir, "{$baseNamespace}\\Resources\\{$resource}\\Widgets"));
                        }
                    }
                }

                return $widgetsList;
            };

            $widgetsList = $cacheEnabled 
                ? Cache::rememberForever($cacheKey, $getWidgetList) 
                : $getWidgetList();

            $this->widgets = array_merge($this->widgets, $widgetsList);

            foreach ($widgetsList as $widget) {
                $this->queueLivewireComponentForRegistration($this->normalizeWidgetClass($widget));
            }

            return $this;
        });
    }

    public function packageBooted(): void
    {
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__.'/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-modular-v3/{$file->getFilename()}"),
                ], 'filament-modular-v3-stubs');
            }
        }

        Testable::mixin(new TestsFilamentModularV3());
    }

    protected function getCommands(): array
    {
        $commands = [
            MakeModularPageCommand::class,
            MakeModularRelationManagerCommand::class,
            MakeModularResourceCommand::class,
            MakeModularWidgetCommand::class,
        ];

        $aliases = array_filter(array_map(function ($command) {
            $class = 'RealMrHex\\Commands\\Aliases\\'.class_basename($command);
            return class_exists($class) ? $class : null;
        }, $commands));

        return array_merge($commands, $aliases);
    }

    protected function registerConfigs(Module $module): void
    {
        $configPath = "{$module->getPath()}/Config";

        if (!is_dir($configPath)) {
            return;
        }

        foreach (glob($configPath . '/*.php') as $configFile) {
            $filename = pathinfo($configFile, PATHINFO_FILENAME);
            config()->set($filename, array_merge(config()->get($filename, []), require $configFile));
        }
    }

}