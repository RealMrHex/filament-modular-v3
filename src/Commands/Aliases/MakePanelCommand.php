<?php

namespace RealMrHex\FilamentModularV3\Commands\Aliases;

use Filament\Commands;

class MakePanelCommand extends Commands\MakePanelCommand
{
    protected $hidden = true;

    protected $signature = 'module:filament-panel {module?} {id?} {--F|force}';
}
