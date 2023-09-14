<?php

namespace RealMrHex\FilamentModularV3\Commands;

use Illuminate\Console\Command;

class FilamentModularV3Command extends Command
{
    public $signature = 'filament-modular-v3';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
