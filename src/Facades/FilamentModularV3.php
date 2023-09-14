<?php

namespace RealMrHex\FilamentModularV3\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RealMrHex\FilamentModularV3\FilamentModularV3
 */
class FilamentModularV3 extends Facade
{
    /**
     * Get the facade accessor
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \RealMrHex\FilamentModularV3\FilamentModularV3::class;
    }
}
