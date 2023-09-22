<?php

namespace RealMrHex\FilamentModularV3\Commands\Aliases;

use Filament\Commands;

class MakeModularPanelCommand extends Commands\MakePanelCommand
{
    protected $hidden = true;

    protected $signature = 'module:make-filament-panel {module?} {id?} {--F|force}';
}
