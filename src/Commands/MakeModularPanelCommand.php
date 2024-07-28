<?php

namespace RealMrHex\FilamentModularV3\Commands;

use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;
use function Laravel\Prompts\text;

class MakeModularPanelCommand extends Command
{
    use CanManipulateFiles;

    protected $description = 'Create a new Filament panel';

    protected $signature = 'module:make-filament-panel {module?} {id?} {--F|force}';

    public function handle(): int
    {
        FindModule:
        try
        {
            $module_name = $this->argument('module') ?? text(
                label      : 'What is the module name?',
                placeholder: 'User',
                required   : true,
            );
            $module_name = Str::of($module_name)->studly()->toString();
            $module = app('modules')->findOrFail($module_name);
            $moduleNamespace = config('modules.namespace') . "\\{$module->getName()}\Providers\Filament\Panels";
        }
        catch (Throwable)
        {
            $this->components->warn('Module not found, make sure module exists and try again.');

            goto FindModule;
        }

        $id = Str::lcfirst($this->argument('id') ?? text(
            label      : 'What is the ID?',
            placeholder: 'app',
            required   : true,
        ));

        $class = (string)str($id)
            ->studly()
            ->append('PanelProvider')
        ;

        $path = (string)str($class)
            ->prepend("{$module->getPath()}/Providers/Filament/Panels/")
            ->replace('\\', '/')
            ->append('.php')
        ;

        if (!$this->option('force') && $this->checkForCollision([$path]))
        {
            return static::INVALID;
        }

        $this->copyStubToApp('PanelProvider', $path, [
            'class'     => $class,
            'directory' => str($id)->studly(),
            'id'        => $id,
            'module'    => $module_name,
            'namespace' => $moduleNamespace,
        ]);

        $this->components->info("Successfully created $class!");

        return static::SUCCESS;
    }
}
