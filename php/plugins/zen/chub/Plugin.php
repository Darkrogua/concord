<?php namespace Zen\Chub;

use Backend\Widgets\ListStructure;
use Illuminate\Support\Facades\Log;
use System\Classes\PluginBase;

# Публикация классов
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\Support\Strings;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\CronApp;
use Zen\Chub\Classes\Support\Vite;


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
        $this->registerConsoleCommand('stream', 'Zen\Chub\Console\StreamCommand');
        $this->registerConsoleCommand('patch', 'Zen\Chub\Console\PatchCommand');
        $this->registerConsoleCommand('refresh', 'Zen\Chub\Console\RefreshCommand');
        $this->registerConsoleCommand('devbot', 'Zen\Chub\Console\DevBotCommand');
        $this->registerConsoleCommand('deploy', 'Zen\Chub\Console\DeployCommand');
        $this->registerConsoleCommand('command', 'Zen\Chub\Console\CommandCommand');
        $this->registerConsoleCommand('flow', 'Zen\Chub\Console\FlowCommand');
        $this->registerConsoleCommand('base', 'Zen\Chub\Console\BaseCommand');
        $this->registerConsoleCommand('entity', 'Zen\Chub\Console\EntityCommand');
        $this->registerConsoleCommand('block', 'Zen\Chub\Console\BlockCommand');
        $this->registerConsoleCommand('feature', 'Zen\Chub\Console\FeatureCommand');
        $this->registerConsoleCommand('blocks.sync', 'Zen\Chub\Console\BlocksSyncCommand');
    }

    public function registerReportWidgets()
    {
        return [
            \Zen\Chub\ReportWidgets\Deploy::class => [
                'label' => 'Деплой',
                'context' => 'dashboard',
            ],
            \Zen\Chub\ReportWidgets\CheckinsCount::class => [
                'label' => 'Заезды в базе',
                'context' => 'dashboard',
                'group' => 'CHub',
            ],
        ];
    }

    /**
     * registerSchedule defines console scheduler.
     */
    public function registerSchedule($schedule)
    {
        CronApp::make()->execute($schedule);
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        // Подхват structure.includeReferencePool из конфига списка (в ядре не в fillFromConfig).
        ListStructure::extend(function (ListStructure $widget) {
            if ($widget->getConfig('includeReferencePool', false)) {
                $widget->includeReferencePool = true;
            }
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
        return [
            'options' => [
                'label'       => 'Основные настройки',
                'description' => 'Настройки речных круизов',
                'icon'        => 'oc-icon-anchor',
                'permissions' => [],
                'category' => 'Cruise HUB',
                'class' => 'Zen\Chub\Models\Settings',
                'order' => 100,
            ]
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'chub_vite' => function ($entries) {
                    $entries = is_array($entries) ? $entries : [$entries];
                    return Vite::tags($entries);
                }
            ]
        ];
    }
}
