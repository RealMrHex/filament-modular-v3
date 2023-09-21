<?php

namespace RealMrHex\FilamentModularV3\Commands;

use Filament\Facades\Filament;
use Filament\Forms\Commands\Concerns\CanGenerateForms;
use Filament\Panel;
use Filament\Support\Commands\Concerns\CanIndentStrings;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Filament\Tables\Commands\Concerns\CanGenerateTables;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeModularResourceCommand extends Command
{
    use CanGenerateForms;
    use CanGenerateTables;
    use CanIndentStrings;
    use CanManipulateFiles;
    use CanReadModelSchemas;

    protected $description = 'Create a new Filament resource class and default page classes';

    protected $signature = 'module:filament-resource {module?} {name?} {--soft-deletes} {--view} {--G|generate} {--S|simple} {--panel=} {--F|force}';

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
        }
        catch (Throwable)
        {
            $this->components->warn('Module not found, make sure module exists and try again.');

            goto FindModule;
        }

        $model = (string)str($this->argument('name') ?? text(
            label      : 'What is the model name?',
            placeholder: 'BlogPost',
            required   : true,
        ))
            ->studly()
            ->beforeLast('Resource')
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->studly()
            ->replace('/', '\\')
        ;

        if (blank($model))
        {
            $model = 'Resource';
        }

        $modelClass = (string)str($model)->afterLast('\\');
        $modelNamespace = str($model)->contains('\\')
            ?
            (string)str($model)->beforeLast('\\')
            :
            '';
        $pluralModelClass = (string)str($modelClass)->pluralStudly();

        $panel = $this->option('panel');

        if ($panel)
        {
            $panel = Filament::getPanel($panel);
        }

        if (!$panel)
        {
            $panels = Filament::getPanels();

            /** @var Panel $panel */
            $panel = (count($panels) > 1) ? $panels[select(
                label  : 'Which panel would you like to create this in?',
                options: array_map(
                             fn(Panel $panel): string => $panel->getId(),
                             $panels,
                         ),
                default: Filament::getDefaultPanel()->getId()
            )] : Arr::first($panels);
        }

        $resourceDirectories = $panel->getResourceDirectories();
        $resourceNamespaces = $panel->getResourceNamespaces();

        $panelId = (string)str($panel->getId())->studly();

        $resourceModuleDirectory = $module->getPath() . "/Filament/$panelId/Resources/";
        $moduleNamespace = config('modules.namespace') . "\\{$module->getName()}\Filament\\$panelId\Resources";

        $namespace = (count($resourceNamespaces) > 1)
            ?
            select(
                label  : 'Which namespace would you like to create this in?',
                options: $resourceNamespaces
            )
            :
            (Arr::first($resourceNamespaces) ?? $module->getPath());
        $path = (count($resourceDirectories) > 1)
            ?
            $resourceDirectories[array_search($namespace, $resourceNamespaces)]
            :
            (Arr::first($resourceDirectories) ?? $resourceModuleDirectory);

        $resource = "{$model}Resource";
        $resourceClass = "{$modelClass}Resource";
        $namespace = $moduleNamespace;
        $listResourcePageClass = "List$pluralModelClass";
        $manageResourcePageClass = "Manage$pluralModelClass";
        $createResourcePageClass = "Create$modelClass";
        $editResourcePageClass = "Edit$modelClass";
        $viewResourcePageClass = "View$modelClass";

        $baseResourcePath =
            (string)str($resource)
                ->prepend('/')
                ->prepend($path)
                ->replace('\\', '/')
                ->replace('//', '/')
                ->replace(app_path(), $module->getPath())
        ;

        $resourcePath = "$baseResourcePath.php";
        $resourcePagesDirectory = "$baseResourcePath/Pages";
        $listResourcePagePath = "$resourcePagesDirectory/$listResourcePageClass.php";
        $manageResourcePagePath = "$resourcePagesDirectory/$manageResourcePageClass.php";
        $createResourcePagePath = "$resourcePagesDirectory/$createResourcePageClass.php";
        $editResourcePagePath = "$resourcePagesDirectory/$editResourcePageClass.php";
        $viewResourcePagePath = "$resourcePagesDirectory/$viewResourcePageClass.php";

        if (!$this->option('force') && $this->checkForCollision([
                                                                    $resourcePath,
                                                                    $listResourcePagePath,
                                                                    $manageResourcePagePath,
                                                                    $createResourcePagePath,
                                                                    $editResourcePagePath,
                                                                    $viewResourcePagePath,
                                                                ]))
        {
            return static::INVALID;
        }

        $pages = '\'index\' => Pages\\' . ($this->option('simple') ? $manageResourcePageClass : $listResourcePageClass) . '::route(\'/\'),';

        if (!$this->option('simple'))
        {
            $pages .= PHP_EOL . "'create' => Pages\\$createResourcePageClass::route('/create'),";

            if ($this->option('view'))
            {
                $pages .= PHP_EOL . "'view' => Pages\\$viewResourcePageClass::route('/{record}'),";
            }

            $pages .= PHP_EOL . "'edit' => Pages\\$editResourcePageClass::route('/{record}/edit'),";
        }

        $tableActions = [];

        if ($this->option('view'))
        {
            $tableActions[] = 'Tables\Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\EditAction::make(),';

        $relations = '';

        if ($this->option('simple'))
        {
            $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes'))
            {
                $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
                $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
            }
        }
        else
        {
            $relations .= PHP_EOL . 'public static function getRelations(): array';
            $relations .= PHP_EOL . '{';
            $relations .= PHP_EOL . '    return [';
            $relations .= PHP_EOL . '        //';
            $relations .= PHP_EOL . '    ];';
            $relations .= PHP_EOL . '}' . PHP_EOL;
        }

        $tableActions = implode(PHP_EOL, $tableActions);

        $tableEmptyStateActions = [];

        $tableEmptyStateActions[] = 'Tables\Actions\CreateAction::make(),';

        $tableEmptyStateActions = implode(PHP_EOL, $tableEmptyStateActions);

        $tableBulkActions = [];

        $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';

        $eloquentQuery = '';

        if ($this->option('soft-deletes'))
        {
            $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';
            $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';

            $eloquentQuery .= PHP_EOL . PHP_EOL . 'public static function getEloquentQuery(): Builder';
            $eloquentQuery .= PHP_EOL . '{';
            $eloquentQuery .= PHP_EOL . '    return parent::getEloquentQuery()';
            $eloquentQuery .= PHP_EOL . '        ->withoutGlobalScopes([';
            $eloquentQuery .= PHP_EOL . '            SoftDeletingScope::class,';
            $eloquentQuery .= PHP_EOL . '        ]);';
            $eloquentQuery .= PHP_EOL . '}';
        }

        $tableBulkActions = implode(PHP_EOL, $tableBulkActions);

        $this->copyStubToApp('Resource', $resourcePath, [
            'eloquentQuery'          => $this->indentString($eloquentQuery),
            'formSchema'             => $this->indentString($this->option('generate') ? $this->getResourceFormSchema(
                'App\\Models' . ($modelNamespace !== '' ? "\\$modelNamespace" : '') . '\\' . $modelClass,
            ) : '//',                                       4),
            'model'                  => $model === 'Resource' ? 'Resource as ResourceModel' : $model,
            'modelClass'             => $model === 'Resource' ? 'ResourceModel' : $modelClass,
            'namespace'              => $namespace,
            'pages'                  => $this->indentString($pages, 3),
            'relations'              => $this->indentString($relations),
            'resource'               => "$namespace\\$resourceClass",
            'resourceClass'          => $resourceClass,
            'tableActions'           => $this->indentString($tableActions, 4),
            'tableBulkActions'       => $this->indentString($tableBulkActions, 5),
            'tableEmptyStateActions' => $this->indentString($tableEmptyStateActions, 4),
            'tableColumns'           => $this->indentString($this->option('generate') ? $this->getResourceTableColumns(
                'App\Models' . ($modelNamespace !== '' ? "\\$modelNamespace" : '') . '\\' . $modelClass,
            ) : '//',                                       4),
            'tableFilters'           => $this->indentString(
                $this->option('soft-deletes') ? 'Tables\Filters\TrashedFilter::make(),' : '//',
                4,
            ),
        ]);

        if ($this->option('simple'))
        {
            $this->copyStubToApp('ResourceManagePage', $manageResourcePagePath, [
                'namespace'         => "$namespace\\$resourceClass\\Pages",
                'resource'          => "$namespace\\$resourceClass",
                'resourceClass'     => $resourceClass,
                'resourcePageClass' => $manageResourcePageClass,
            ]);
        }
        else
        {
            $this->copyStubToApp('ResourceListPage', $listResourcePagePath, [
                'namespace'         => "$namespace\\$resourceClass\\Pages",
                'resource'          => "$namespace\\$resourceClass",
                'resourceClass'     => $resourceClass,
                'resourcePageClass' => $listResourcePageClass,
            ]);

            $this->copyStubToApp('ResourcePage', $createResourcePagePath, [
                'baseResourcePage'      => 'Filament\\Resources\\Pages\\CreateRecord',
                'baseResourcePageClass' => 'CreateRecord',
                'namespace'             => "$namespace\\$resourceClass\\Pages",
                'resource'              => "$namespace\\$resourceClass",
                'resourceClass'         => $resourceClass,
                'resourcePageClass'     => $createResourcePageClass,
            ]);

            $editPageActions = [];

            if ($this->option('view'))
            {
                $this->copyStubToApp('ResourceViewPage', $viewResourcePagePath, [
                    'namespace'         => "$namespace\\$resourceClass\\Pages",
                    'resource'          => "$namespace\\$resourceClass",
                    'resourceClass'     => $resourceClass,
                    'resourcePageClass' => $viewResourcePageClass,
                ]);

                $editPageActions[] = 'Actions\ViewAction::make(),';
            }

            $editPageActions[] = 'Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes'))
            {
                $editPageActions[] = 'Actions\ForceDeleteAction::make(),';
                $editPageActions[] = 'Actions\RestoreAction::make(),';
            }

            $editPageActions = implode(PHP_EOL, $editPageActions);

            $this->copyStubToApp('ResourceEditPage', $editResourcePagePath, [
                'actions'           => $this->indentString($editPageActions, 3),
                'namespace'         => "$namespace\\$resourceClass\\Pages",
                'resource'          => "$namespace\\$resourceClass",
                'resourceClass'     => $resourceClass,
                'resourcePageClass' => $editResourcePageClass,
            ]);
        }

        $this->components->info("Successfully created $resource!");

        return static::SUCCESS;
    }
}
