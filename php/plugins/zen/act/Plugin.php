<?php namespace Zen\Act;

use RainLab\User\Models\User;
use System\Classes\PluginBase;
use Zen\Act\Classes\System\AuthApp;

/**
 * Plugin class
 */
class Plugin extends PluginBase
{
    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        $this->loadViewsFrom(__DIR__.'/views', 'zen.act');
        $this->registerConsoleCommand('acts', \Zen\Act\Console\ActCommand::class);
        $this->registerConsoleCommand('act.restore-from-storage', \Zen\Act\Console\RestoreFromStorageCommand::class);
        $this->registerConsoleCommand('act.playground', \Zen\Act\Console\PlaygroundCommand::class);
        $this->registerConsoleCommand('act.access-migrate', \Zen\Act\Console\AccessMigrateCommand::class);
        $this->registerConsoleCommand('act.viewer-index-rebuild', \Zen\Act\Console\ViewerIndexRebuildCommand::class);
        $this->registerConsoleCommand('act.tags-index-rebuild', \Zen\Act\Console\TagsIndexRebuildCommand::class);
        $this->registerConsoleCommand('act.snapshots-migrate', \Zen\Act\Console\SnapshotsMigrateCommand::class);
        $this->registerConsoleCommand('act.snapshots-merge', \Zen\Act\Console\SnapshotsMergeCommand::class);
        $this->registerConsoleCommand('act.assets-reconcile', \Zen\Act\Console\AssetsReconcileCommand::class);
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        AuthApp::configureLoginAttribute();

        User::extend(function ($model): void {
            $model->addFillable(['name', 'timezone']);

            $model->bindEvent('model.beforeValidate', function () use ($model) {
                $model->rules['email'] = [
                    'nullable',
                    'string',
                    'email',
                    'max:255',
                    'unique:users,email,'.$model->id.',id,is_guest,false',
                ];
                $model->rules['first_name'] = ['nullable', 'string', 'max:255'];
            });
        });
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
    }

    /**
     * registerSettings used by the backend.
     */
    public function registerSettings()
    {
    }

    public function registerPermissions(): array
    {
        return [
            'zen.act.manage_acts' => [
                'tab' => 'zen.act::lang.plugin.name',
                'label' => 'zen.act::lang.permissions.manage_acts',
            ],
        ];
    }
}
