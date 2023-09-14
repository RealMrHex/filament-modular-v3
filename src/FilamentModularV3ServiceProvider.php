<?php

namespace RealMrHex\FilamentModularV3;

use Filament\Panel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Nwidart\Modules\Laravel\Module;
use RealMrHex\FilamentModularV3\Testing\TestsFilamentModularV3;
use ReflectionException;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentModularV3ServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-modular-v3';

    /**
     * Configure the package
     *
     * @param Package $package
     *
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
                ->hasCommands($this->getCommands())
                ->hasInstallCommand(
                    function (InstallCommand $command)
                    {
                        $command->askToStarRepoOnGitHub('realmrhex/filament-modular-v3');
                    }
                )
        ;

        if (file_exists($package->basePath("/../config/{$package->shortName()}.php")))
            $package->hasConfigFile();
    }

    /**
     * Handle package registration
     *
     * @return void
     */
    public function packageRegistered(): void
    {
        $this->app->beforeResolving('filament', fn() => $this->addModularFunctionality());
        $this->registerModuleDiscoveryMacros();
    }

    /**
     * Add modular functionality to Filament
     *
     * @return void
     */
    private function addModularFunctionality(): void
    {
        $modules = $this->app['modules']->allEnabled();

        foreach ($modules as $module)
        {
            $this->discoverPanels($module);
        }
    }

    /**
     * Discover Filament Panels
     *
     * @param Module $module
     *
     * @return void
     */
    private function discoverPanels(Module $module): void
    {
        $providersDir = "{$module->getPath()}/Filament/Panels";

        if (!is_dir($providersDir))
            return;

        $providers = scandir($providersDir);

        foreach ($providers as $provider)
        {
            if (!preg_match('/^(.+)\.php$/', $provider, $matches))
            {
                continue;
            }

            $providerClass = "Modules\\{$module->getStudlyName()}\\Filament\\Panels\\{$matches[1]}";

            if (class_exists($providerClass))
                $this->app->register($providerClass);
        }
    }

    /**
     * Register modules discovery macros
     *
     * @return void
     */
    private function registerModuleDiscoveryMacros(): void
    {
        $this->discoverResourcesMacro();
        $this->discoverPagesMacro();
        $this->discoverWidgetsMacro();
    }

    /**
     * Discover resources macro
     *
     * @return void
     */
    private function discoverResourcesMacro(): void
    {
        Panel::macro(
            'discoverModulesResources',
            function ()
            {
                $resourcesList = [];
                $modules = app()['modules']->allEnabled();
                $panelId = Str::of($this->getId())->studly();

                foreach ($modules as $module)
                {
                    $filamentDir = "{$module->getPath()}/Filament/$panelId/Resources";

                    if (!is_dir($filamentDir))
                        continue;

                    $resources = scandir($filamentDir);

                    foreach ($resources as $resource)
                    {
                        if (!preg_match('/^(.+)\.php$/', $resource, $matches))
                            continue;

                        $resourceClass = "Modules\\{$module->getStudlyName()}\\Filament\\$panelId\\Resources\\$matches[1]";

                        if (class_exists($resourceClass))
                            $resourcesList[] = $resourceClass;
                    }
                }

                $this->resources = [
                    ...$this->resources,
                    ...$resourcesList,
                ];

                return $this;
            }
        );
    }

    /**
     * Discover pages macro
     *
     * @return void
     */
    private function discoverPagesMacro(): void
    {
        Panel::macro(
            'discoverModulesPages',
            function ()
            {
                $pagesList = [];
                $modules = app()['modules']->allEnabled();
                $panelId = Str::of($this->getId())->studly();

                foreach ($modules as $module)
                {
                    $filamentDir = "{$module->getPath()}/Filament/$panelId/Pages";

                    if (!is_dir($filamentDir))
                    {
                        continue;
                    }

                    $pages = scandir($filamentDir);

                    foreach ($pages as $page)
                    {
                        if (!preg_match('/^(.+)\.php$/', $page, $matches))
                            continue;

                        $pageClass = "Modules\\{$module->getStudlyName()}\\Filament\\$panelId\\Pages\\$matches[1]";

                        if (class_exists($pageClass))
                        {
                            $pagesList[] = $pageClass;
                        }
                    }
                }

                $this->pages = [
                    ...$this->pages,
                    ...$pagesList,
                ];

                foreach ($pagesList as $page)
                {
                    $this->queueLivewireComponentForRegistration($page);
                }

                return $this;
            }
        );
    }

    /**
     * Discover widgets macro
     *
     * @return void
     */
    private function discoverWidgetsMacro(): void
    {
        Panel::macro(
            'discoverModulesWidgets',
            function ()
            {
                $widgetsList = [];
                $modules = app()['modules']->allEnabled();
                $panelId = Str::of($this->getId())->studly();

                foreach ($modules as $module)
                {
                    $widgetsDir = "{$module->getPath()}/Filament/$panelId/Widgets";

                    if (is_dir($widgetsDir))
                    {
                        $widgets = scandir($widgetsDir);

                        foreach ($widgets as $widget)
                        {
                            if (!preg_match('/^(.+)\.php$/', $widget, $matches))
                            {
                                continue;
                            }

                            $widgetClass = "Modules\\{$module->getStudlyName()}\\Filament\\$panelId\\Widgets\\$matches[1]";

                            if (class_exists($widgetClass))
                            {
                                $widgetsList[] = $widgetClass;
                            }
                        }
                    }

                    $filamentDir = "{$module->getPath()}/Filament/$panelId/Resources";

                    if (!is_dir($filamentDir))
                    {
                        continue;
                    }

                    $resources = scandir($filamentDir);
                    unset($resources[0], $resources[1]);

                    foreach ($resources as $resource)
                    {
                        if (!is_dir("$filamentDir/$resource") || !is_dir("$filamentDir/$resource/Widgets"))
                            continue;

                        $widgets = scandir("$filamentDir/$resource/Widgets");

                        foreach ($widgets as $widget)
                        {
                            if (!preg_match('/^(.+)\.php$/', $widget, $matches))
                                continue;

                            $widgetClass = "Modules\\{$module->getStudlyName()}\\Filament\\$panelId\\Resources\\$resource\\Widgets\\$matches[1]";

                            if (class_exists($widgetClass))
                                $widgetsList[] = $widgetClass;
                        }
                    }
                }

                $this->widgets = [
                    ...$this->widgets,
                    ...$widgetsList,
                ];

                foreach ($widgetsList as $widget)
                    $this->queueLivewireComponentForRegistration($this->normalizeWidgetClass($widget));

                return $this;
            }
        );
    }

    /**
     * Handle package boot
     *
     * @throws ReflectionException
     */
    public function packageBooted(): void
    {
        // Handle Stubs
        if (app()->runningInConsole())
        {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file)
            {
                $this->publishes(
                    [
                        $file->getRealPath() => base_path("stubs/filament-modular-v3/{$file->getFilename()}"),
                    ],
                    'filament-modular-v3-stubs'
                );
            }
        }

        // Testing
        Testable::mixin(new TestsFilamentModularV3());
    }


    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [];
    }
}
