<?php namespace Zen\Chub\Classes\Tools;

use Illuminate\Support\Str;
use RainLab\Builder\Classes\PluginCode;
use RainLab\Builder\Models\MigrationModel;
use System\Classes\VersionManager;
use Zen\Chub\Classes\System\Files;
use View;
use Yaml;

class EntityBuilder
{
    private string $name; // Единственное число
    private string $names; // Множественное число
    private string $name_title; // Название
    private string $name_create; // Создать
    private string $name_update; // Изменить


    public static function make(): self
    {
        return new self();
    }

    public function create(
        string $name,
        string $name_plural,
        string $name_title = "Записи",
        string $name_create = "Создать запись",
        string $name_update = "Изменить запись",
    ) {
        $this->name = Str::snake($name);
        $this->names = Str::snake($name_plural);
        $this->name_title = $name_title;
        $this->name_create = $name_create;
        $this->name_update = $name_update;

        $this->makeModel();
        $this->makeModelColumnsConfig();
        $this->makeModelFormConfig();
        $this->makeController();
        $this->makeControllerIndex();
        $this->makeControllerCreate();
        $this->makeControllerUpdate();
        $this->makeControllerToolbar();
        $this->makeControllerConfigForm();
        $this->makeControllerConfigList();
        $this->makeMigration();

        $created_version = $this->appendVersion();
        $this->applyMigration($created_version);
    }

    public function remove(string $name, string $name_plural,)
    {
        $this->name = Str::snake($name);
        $this->names = Str::snake($name_plural);

        $migration_file_name = "builder_table_create_zen_chub_{$this->names}.php";
        $version_key = $this->findVersionByMigrationFile($migration_file_name);

        if ($version_key !== null) {
            $this->rollbackMigration($version_key);
            $this->removeVersionEntry($version_key);
        }

        $this->removeGeneratedFiles();
    }

    private function makeControllerConfigList()
    {
        $folder_name = str_replace('_', '', $this->names);
        $this->createFile(
            'zen.chub::blueprints.controller_config_list',
            base_path("plugins/zen/chub/controllers/$folder_name/config_list.yaml"),
            [
                'name' => str_replace('_', '', $this->name),
                'names' => $folder_name,
                'model_name' => Str::studly($this->name),
                'controller_name' => Str::studly($this->names),
            ]
        );
    }

    private function makeControllerConfigForm()
    {
        $folder_name = str_replace('_', '', $this->names);
        $this->createFile(
            'zen.chub::blueprints.controller_config_form',
            base_path("plugins/zen/chub/controllers/$folder_name/config_form.yaml"),
            [
                'name' => str_replace('_', '', $this->name),
                'names' => $folder_name,
                'model_name' => Str::studly($this->name),
                'controller_name' => Str::studly($this->names),
            ]
        );
    }

    private function makeControllerToolbar()
    {
        $folder_name = str_replace('_', '', $this->names);
        $this->createFile(
            'zen.chub::blueprints.controller_toolbar',
            base_path("plugins/zen/chub/controllers/$folder_name/_list_toolbar.php"),
            [
                'names' => $folder_name,
                'title' => $this->name_title,
                'name_create' => $this->name_create,
            ]
        );
    }

    private function makeControllerUpdate()
    {
        $folder_name = str_replace('_', '', $this->names);
        $this->createFile(
            'zen.chub::blueprints.controller_update',
            base_path("plugins/zen/chub/controllers/$folder_name/update.php"),
            [
                'names' => $folder_name,
                'title' => $this->name_title,
                'name_update' => $this->name_update,
            ]
        );
    }

    private function makeControllerCreate()
    {
        $folder_name = str_replace('_', '', $this->names);
        $this->createFile(
            'zen.chub::blueprints.controller_create',
            base_path("plugins/zen/chub/controllers/$folder_name/create.php"),
            [
                'names' => $folder_name,
                'title' => $this->name_title,
                'name_create' => $this->name_create,
            ]
        );
    }

    private function makeControllerIndex()
    {
        $folder_name = str_replace('_', '', $this->names);
        $this->createFile(
            'zen.chub::blueprints.controller_index',
            base_path("plugins/zen/chub/controllers/$folder_name/index.php"),
            [
                'title' => $this->name_title,
            ]
        );
    }

    private function makeController()
    {
        $class_name = Str::studly($this->names);
        $file_path = base_path("plugins/zen/chub/controllers/$class_name.php");
        Files::make()->defineFilePath($file_path);
        $this->createFile(
            'zen.chub::blueprints.controller',
            $file_path,
            [
                'class_name' => $class_name
            ]
        );
    }

    private function makeModelFormConfig()
    {
        $name = $this->name;
        $name = str_replace('_', '', $name);
        $file_path = base_path("plugins/zen/chub/models/$name/fields.yaml");
        Files::make()->defineFilePath($file_path);
        $this->createFile(
            'zen.chub::blueprints.form_config',
            $file_path,
            []
        );
    }

    private function makeModelColumnsConfig()
    {
        $name = $this->name;
        $name = str_replace('_', '', $name);
        $file_path = base_path("plugins/zen/chub/models/$name/columns.yaml");
        Files::make()->defineFilePath($file_path);
        $this->createFile(
            'zen.chub::blueprints.columns_config',
            $file_path,
            []
        );
    }

    private function makeModel()
    {
        $model_name = Str::studly($this->name);
        $table_name = $this->names;
        $this->createFile(
            'zen.chub::blueprints.model',
            base_path("plugins/zen/chub/models/$model_name.php"),
            [
                'model_name' => $model_name,
                'table_name' => $table_name
            ]
        );
    }

    private function makeMigration()
    {
        $table_name = $this->names;
        $migration_path = base_path("plugins/zen/chub/updates/builder_table_create_zen_chub_$table_name.php");
        $this->createFile(
            'zen.chub::blueprints.migration',
            $migration_path,
            [
                'camel_plural_name' => Str::studly($this->names),
                'table_name' => $table_name
            ]
        );
    }

    private function appendVersion(): string
    {
        $migration_model = new MigrationModel();
        $migration_model->setPluginCodeObj(new PluginCode('Zen.Chub'));

        $next_version = $migration_model->getNextVersion();
        $table_name = "zen_chub_{$this->names}";
        $migration_file_name = "builder_table_create_zen_chub_{$this->names}.php";
        $version_file_path = base_path('plugins/zen/chub/updates/version.yaml');

        $version_data = Yaml::parseFile($version_file_path) ?: [];
        $version_data["v{$next_version}"] = [
            "Created table {$table_name}",
            $migration_file_name
        ];

        file_put_contents($version_file_path, Yaml::render($version_data));

        return $next_version;
    }

    private function applyMigration(string $version): void
    {
        VersionManager::instance()->updatePlugin('Zen.Chub', $version);
    }

    private function rollbackMigration(string $version_key): void
    {
        $version_data = $this->loadVersionData();
        $latest_version_key = $this->getLatestVersionKey($version_data);

        if ($latest_version_key !== $version_key) {
            throw new \RuntimeException(
                "Откат миграции возможен только для последней версии в version.yaml. Запрошено: {$version_key}, последняя: {$latest_version_key}"
            );
        }

        $version = $this->normalizeVersion($version_key);
        VersionManager::instance()->removePluginToVersion('Zen.Chub', $version, true);
    }

    private function removeVersionEntry(string $version_key): void
    {
        $version_file_path = base_path('plugins/zen/chub/updates/version.yaml');
        $version_data = $this->loadVersionData();

        if (array_key_exists($version_key, $version_data)) {
            unset($version_data[$version_key]);
        }

        file_put_contents($version_file_path, Yaml::render($version_data));
    }

    private function findVersionByMigrationFile(string $migration_file_name): ?string
    {
        $version_data = $this->loadVersionData();

        foreach ($version_data as $version_key => $details) {
            if (is_array($details) && in_array($migration_file_name, $details, true)) {
                return (string) $version_key;
            }
        }

        return null;
    }

    private function loadVersionData(): array
    {
        $version_file_path = base_path('plugins/zen/chub/updates/version.yaml');
        $version_data = Yaml::parseFile($version_file_path);

        return is_array($version_data) ? $version_data : [];
    }

    private function getLatestVersionKey(array $version_data): ?string
    {
        if (empty($version_data)) {
            return null;
        }

        $version_keys = array_keys($version_data);

        usort($version_keys, function ($a, $b) {
            return version_compare(
                $this->normalizeVersion((string) $a),
                $this->normalizeVersion((string) $b)
            );
        });

        $latest_version = end($version_keys);

        return $latest_version === false ? null : (string) $latest_version;
    }

    private function normalizeVersion(string $version): string
    {
        return rtrim(ltrim((string) $version, 'v'), '.');
    }

    private function removeGeneratedFiles(): void
    {
        $model_name = Str::studly($this->name);
        $controller_name = Str::studly($this->names);
        $model_folder_name = str_replace('_', '', $this->name);
        $controller_folder_name = str_replace('_', '', $this->names);
        $migration_file_name = "builder_table_create_zen_chub_{$this->names}.php";

        $file_paths = [
            base_path("plugins/zen/chub/models/{$model_name}.php"),
            base_path("plugins/zen/chub/models/{$model_folder_name}/columns.yaml"),
            base_path("plugins/zen/chub/models/{$model_folder_name}/fields.yaml"),
            base_path("plugins/zen/chub/controllers/{$controller_name}.php"),
            base_path("plugins/zen/chub/controllers/{$controller_folder_name}/config_list.yaml"),
            base_path("plugins/zen/chub/controllers/{$controller_folder_name}/config_form.yaml"),
            base_path("plugins/zen/chub/controllers/{$controller_folder_name}/_list_toolbar.php"),
            base_path("plugins/zen/chub/controllers/{$controller_folder_name}/index.php"),
            base_path("plugins/zen/chub/controllers/{$controller_folder_name}/create.php"),
            base_path("plugins/zen/chub/controllers/{$controller_folder_name}/update.php"),
            base_path("plugins/zen/chub/updates/{$migration_file_name}"),
        ];

        foreach ($file_paths as $file_path) {
            if (is_file($file_path)) {
                unlink($file_path);
            }
        }

        $this->removeDirIfEmpty(base_path("plugins/zen/chub/models/{$model_folder_name}"));
        $this->removeDirIfEmpty(base_path("plugins/zen/chub/controllers/{$controller_folder_name}"));
    }

    private function removeDirIfEmpty(string $dir_path): void
    {
        if (!is_dir($dir_path)) {
            return;
        }

        $items = scandir($dir_path);
        if ($items === false) {
            return;
        }

        if (count($items) <= 2) {
            rmdir($dir_path);
        }
    }

    private function createFile(
        string $template_path,
        string $file_path,
        array $data,
    ) {

        if (file_exists($file_path)) {
            unlink($file_path);
        }

        $file_data = View::make($template_path, $data)->render();
        Files::make()->defineFilePath($file_path);
        file_put_contents($file_path, $file_data);
    }
}