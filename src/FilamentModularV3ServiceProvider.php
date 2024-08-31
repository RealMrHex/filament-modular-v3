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
            $this->discoverPanels($module);
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

            $resourcesList = Cache::rememberForever('filament_module_v3_resources_'.$panelId, function () use($panelId){

                $resourcesList = [];
                $modules = app()['modules']->allEnabled();
                
                foreach ($modules as $module) {
                    $filamentDir = "{$module->getPath()}/Filament/$panelId/Resources";
                    if (is_dir($filamentDir)) {
                        foreach (scandir($filamentDir) as $resource) {
                            if (preg_match('/^(.+)\.php$/', $resource, $matches)) {
                                $resourceClass = "Modules\\{$module->getStudlyName()}\\Filament\\$panelId\\Resources\\{$matches[1]}";
                                if (class_exists($resourceClass)) {
                                    $resourcesList[] = $resourceClass;
                                }
                            }
                        }
                    }
                }
                
                return $resourcesList;
            });

            $this->resources = array_merge($this->resources, $resourcesList);
            return $this;
        });
    }

    private function discoverPagesMacro(): void
    {
        Panel::macro('discoverModulesPages', function () {

            $panelId = Str::of($this->getId())->studly();

            $pagesList = Cache::rememberForever('filament_module_v3_pages_'.$panelId, function () use($panelId) {

                $pagesList = [];
                $modules = app()['modules']->allEnabled();

                foreach ($modules as $module) {
                    $filamentDir = "{$module->getPath()}/Filament/$panelId/Pages";
                    if (is_dir($filamentDir)) {
                        foreach (scandir($filamentDir) as $page) {
                            if (preg_match('/^(.+)\.php$/', $page, $matches)) {
                                $pageClass = "Modules\\{$module->getStudlyName()}\\Filament\\$panelId\\Pages\\{$matches[1]}";
                                if (class_exists($pageClass)) {
                                    $pagesList[] = $pageClass;
                                }
                            }
                        }
                    }
                }

                return $pagesList;
            });

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

            
            $widgetsList = Cache::rememberForever('filament_module_v3_widgets_'.$panelId, function () use($panelId) {
                $widgetsList = [];
                $modules = app()['modules']->allEnabled();
                

                foreach ($modules as $module) {
                    // Discover widgets in the main directory
                    $widgetsDir = "{$module->getPath()}/Filament/$panelId/Widgets";
                    if (is_dir($widgetsDir)) {
                        foreach (scandir($widgetsDir) as $widget) {
                            if (preg_match('/^(.+)\.php$/', $widget, $matches)) {
                                $widgetClass = "Modules\\{$module->getStudlyName()}\\Filament\\$panelId\\Widgets\\{$matches[1]}";
                                if (class_exists($widgetClass)) {
                                    $widgetsList[] = $widgetClass;
                                }
                            }
                        }
                    }

                    // Discover widgets in the resources directory
                    $resourcesDir = "{$module->getPath()}/Filament/$panelId/Resources";
                    if (is_dir($resourcesDir)) {
                        foreach (scandir($resourcesDir) as $resource) {
                            if (is_dir("$resourcesDir/$resource/Widgets")) {
                                $resourceWidgetsDir = "$resourcesDir/$resource/Widgets";
                                foreach (scandir($resourceWidgetsDir) as $widget) {
                                    if (preg_match('/^(.+)\.php$/', $widget, $matches)) {
                                        $widgetClass = "Modules\\{$module->getStudlyName()}\\Filament\\$panelId\\Resources\\$resource\\Widgets\\{$matches[1]}";
                                        if (class_exists($widgetClass)) {
                                            $widgetsList[] = $widgetClass;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                return $widgetsList;
            });

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

    private function discoverModulesWithCache(string $key, callable $callback)
    {
        $cacheEnabled = config('filament-modular-v3.enable_auto_discover_cache', false) || app()->isProduction();

        if ($cacheEnabled) {
            return Cache::rememberForever($key, $callback);
        }

        return $callback();
    }
}