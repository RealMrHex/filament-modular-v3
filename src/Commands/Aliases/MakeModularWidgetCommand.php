<?php

namespace RealMrHex\FilamentModularV3\Commands\Aliases;

use Filament\Widgets\Commands;

class MakeModularWidgetCommand extends Commands\MakeWidgetCommand
{
    protected $hidden = true;

    protected $signature = 'module:make-filament-widget {module?} {name?} {--R|resource=} {--C|chart} {--T|table} {--S|stats-overview} {--panel=} {--F|force}';
}
