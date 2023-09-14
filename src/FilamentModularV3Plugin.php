<?php

namespace RealMrHex\FilamentModularV3;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentModularV3Plugin implements Plugin
{
    /**
     * Plugin ID
     *
     * @return string
     */
    public function getId(): string
    {
        return 'filament-modular-v3';
    }

    /**
     * Register panel
     *
     * @param Panel $panel
     *
     * @return void
     */
    public function register(Panel $panel): void
    {
        //
    }

    /**
     * Bootstrap panel
     *
     * @param Panel $panel
     *
     * @return void
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Make an instance
     *
     * @return static
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Get the plugin
     *
     * @return static
     */
    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
