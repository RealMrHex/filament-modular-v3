<?php

namespace RealMrHex\FilamentModularV3;

use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use RealMrHex\FilamentModularV3\Commands\FilamentModularV3Command;
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
                        $command
                            ->publishConfigFile()
                            ->askToStarRepoOnGitHub('realmrhex/filament-modular-v3')
                        ;
                    }
                )
        ;

        if (file_exists($package->basePath("/../config/{$package->shortName()}.php")))
            $package->hasConfigFile();
    }

    public function packageRegistered(): void {}

    /**
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
        return [
            FilamentModularV3Command::class,
        ];
    }
}
