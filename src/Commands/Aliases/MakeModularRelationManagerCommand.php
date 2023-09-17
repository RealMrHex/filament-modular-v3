<?php

namespace RealMrHex\FilamentModularV3\Commands\Aliases;

use Filament\Commands;

class MakeModularRelationManagerCommand extends Commands\MakeRelationManagerCommand
{
    protected $hidden = true;

    protected $signature = 'filament-module:relation-manager {module?} {resource?} {relationship?} {recordTitleAttribute?} {--attach} {--associate} {--soft-deletes} {--view} {--panel=} {--F|force}';
}
