<?php

namespace RealMrHex\FilamentModularV3\Commands\Aliases;

use Filament\Commands;

class MakeModularResourceCommand extends Commands\MakeResourceCommand
{
    protected $hidden = true;

    protected $signature = 'filament-module:resource {module?} {name?} {--soft-deletes} {--view} {--G|generate} {--S|simple} {--panel=} {--F|force}';
}
