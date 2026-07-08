<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use October\Rain\Exception\ValidationException;
use Zen\Chub\Classes\System\BlockApp;
use Zen\Chub\Classes\System\BlockPaths;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Models\Block;

class Blocks extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Zen.Chub', 'chub');
    }

    public function formExtendFields($form): void
    {
        $block = $form->model ?? null;
        if (!$block instanceof Block || !$block->exists || (bool) ($block->is_folder ?? false)) {
            return;
        }

        $code = trim((string) ($block->code ?? ''));
        if ($code === '') {
            return;
        }

        $paths = BlockPaths::make();
        $dom_id = $paths->domId($code);

        $form->addFields([
            'dom_id_hint' => [
                'label' => 'Dom ID (uuid в разметке)',
                'type' => 'partial',
                'path' => '$/zen/chub/controllers/blocks/_dom_id_hint.php',
                'span' => 'full',
                'context' => ['update'],
            ],
        ], 'primary');

        $block->setAttribute('dom_id', $dom_id);

        $fields = [];

        foreach ($paths->listDemoJsonSlots($code) as $slot) {
            $field_name = $this->demoJsonFieldName($slot['variant_code']);
            $content = $paths->readDemoJsonRaw($code, $slot['variant_code']);

            if ($content === '' && !($slot['exists'] ?? false)) {
                $content = "{\n    \"default\": {}\n}";
            }

            $block->setAttribute($field_name, $content);

            $comment = 'Демо-данные по умолчанию для всех вариантов представления';
            if ($slot['variant_code'] !== null && ($slot['fallback_filename'] ?? null)) {
                $comment = 'Demo для варианта «'.$slot['variant_code'].'»; если файла нет — fallback на '.$slot['fallback_filename'];
            }

            $fields[$field_name] = [
                'label' => (string) $slot['filename'],
                'comment' => $comment,
                'type' => 'codeeditor',
                'language' => 'javascript',
                'size' => 'giant',
                'span' => 'full',
                'tab' => 'Демо-данные',
            ];
        }

        if ($fields === []) {
            return;
        }

        $form->addTabFields($fields);
    }

    public function formBeforeSave($model): void
    {
        if (!$model instanceof Block) {
            return;
        }

        $field_names = $this->demoJsonFieldNamesForModel($model);
        if ($field_names === []) {
            return;
        }

        $model->bindEventOnce('model.saveInternal', function () use ($model, $field_names): void {
            foreach ($field_names as $field_name) {
                unset($model->attributes[$field_name]);
            }
        });
    }

    public function formAfterSave($model): void
    {
        if (!$model instanceof Block || (bool) ($model->is_folder ?? false)) {
            return;
        }

        $code = trim((string) ($model->code ?? ''));
        if ($code === '') {
            return;
        }

        $paths = BlockPaths::make();
        $post_data = post('Block', []);
        if (!is_array($post_data)) {
            return;
        }

        foreach ($paths->listDemoJsonSlots($code) as $slot) {
            $field_name = $this->demoJsonFieldName($slot['variant_code']);
            if (!array_key_exists($field_name, $post_data)) {
                continue;
            }

            $raw = (string) ($post_data[$field_name] ?? '');
            try {
                $paths->writeDemoJsonRaw($code, $slot['variant_code'], $raw);
            } catch (\InvalidArgumentException $e) {
                throw new ValidationException([$field_name => $e->getMessage()]);
            }
        }
    }

    public function onSaveData()
    {
        try {
            $result = BlockApp::make()->exportAllToTheme();
            Flash::success(sprintf(
                'Данные сохранены: блоков %d, папок %d',
                $result['blocks'],
                $result['folders']
            ));
        } catch (\Throwable $e) {
            LogsApp::addAdminActionError($e, 'blocks.save');
            Flash::error('Ошибка при сохранении данных: '.$e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $result = BlockApp::make()->restoreAllFromTheme();
            Flash::success(sprintf(
                'Данные восстановлены: записей %d (блоков %d, папок %d)',
                $result['restored'],
                $result['blocks'],
                $result['folders']
            ));
        } catch (\Throwable $e) {
            LogsApp::addAdminActionError($e, 'blocks.restore');
            Flash::error('Ошибка при восстановлении данных: '.$e->getMessage());
        }

        return $this->listRefresh();
    }

    /**
     * @return list<string>
     */
    private function demoJsonFieldNamesForModel(Block $block): array
    {
        $code = trim((string) ($block->code ?? ''));
        if ($code === '' || (bool) ($block->is_folder ?? false)) {
            return [];
        }

        $names = [];
        foreach (BlockPaths::make()->listDemoJsonSlots($code) as $slot) {
            $names[] = $this->demoJsonFieldName($slot['variant_code']);
        }

        return $names;
    }

    private function demoJsonFieldName(?string $variant_code): string
    {
        if ($variant_code === null || $variant_code === '' || $variant_code === 'default') {
            return 'demo_json_default';
        }

        $suffix = preg_replace('/[^a-z0-9_\-]/i', '_', $variant_code) ?? 'variant';

        return 'demo_json_'.$suffix;
    }
}
