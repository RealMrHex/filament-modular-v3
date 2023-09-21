<?php

namespace RealMrHex\FilamentModularV3\Commands\Aliases;

use Filament\Commands;

class MakeModularPageCommand extends Commands\MakePageCommand
{
    protected $hidden = true;

    protected $signature = 'filament-module:page {module?} {name?} {--R|resource=} {--T|type=} {--panel=} {--F|force}';
}
