<?php

namespace RealMrHex\FilamentModularV3\Commands;

use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class MakePanelCommand extends Command
{
    use CanManipulateFiles;

    protected $description = 'Create a new Filament panel';

    protected $signature = 'filament-module:panel {module?} {id?} {--F|force}';

    public function handle(): int
    {

        $module_name = $this->argument('module') ?? text(
            label: 'What is the module name?',
            placeholder: 'User',
            required: true,
        );

        $module_name = Str::of($module_name)->studly()->toString();
        $module = null;
        $moduleNamespace = null;
        try {
            $module = app('modules')->findOrFail($module_name);
            $moduleNamespace = config('modules.namespace')."\\{$module->getName()}\Providers\Filament\Panels";
        } catch (Throwable $exception) {
            $this->error('module not found');

            return static::INVALID;
        }

        $id = Str::lcfirst($this->argument('id') ?? text(
            label: 'What is the ID?',
            placeholder: 'app',
            required: true,
        ));

        $class = (string) str($id)
            ->studly()
            ->append('PanelProvider');

        $path = (string) str($class)
            ->prepend("{$module->getPath()}/Providers/Filament/Panels/")
            ->replace('\\', '/')
            ->append('.php');

        if (! $this->option('force') && $this->checkForCollision([$path])) {
            return static::INVALID;
        }

        $this->copyStubToApp('PanelProvider', $path, [
            'class' => $class,
            'directory' => str($id)->studly(),
            'id' => $id,
            'namespace' => $moduleNamespace,
        ]);

        $this->components->info("Successfully created {$class}!");

        return static::SUCCESS;
    }
}
