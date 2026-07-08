<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Db;
use Flash;
use RuntimeException;
use Throwable;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Models\Document;

class Documents extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
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
        $document = $form->model ?? null;
        if (!$document instanceof Document || !$document->exists) {
            return;
        }

        if ((bool) ($document->is_folder ?? false)) {
            $form->removeField('data');
            $form->removeField('props');
        }
    }

    public function onReloadFromFiles()
    {
        $storage_root_path = base_path('plugins/zen/chub/data/documents');

        try {
            if (!is_dir($storage_root_path)) {
                throw new RuntimeException('Папка документов не найдена: ' . $storage_root_path);
            }

            $created_count = 0;

            Db::transaction(function () use ($storage_root_path, &$created_count) {
                Document::query()->truncate();
                $created_count = $this->importDocumentsTree($storage_root_path, null);
            });

            Flash::success('База документов пересобрана из файлов. Загружено записей: ' . $created_count . '.');
        } catch (Throwable $e) {
            LogsApp::addAdminActionError($e, 'documents.reload_from_files');
            Flash::error('Ошибка загрузки документов: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    private function importDocumentsTree(string $directory_path, ?int $parent_id): int
    {
        $entries = scandir($directory_path);
        if ($entries === false) {
            throw new RuntimeException('Не удалось прочитать папку: ' . $directory_path);
        }

        $entries = array_values(array_filter($entries, function (string $entry_name): bool {
            if ($entry_name === '.' || $entry_name === '..') {
                return false;
            }

            if (str_starts_with($entry_name, '.')) {
                return false;
            }

            return true;
        }));

        natcasesort($entries);

        $sort_order = 1;
        $created_count = 0;
        $used_codes = [];

        foreach ($entries as $entry_name) {
            $entry_path = $directory_path . DIRECTORY_SEPARATOR . $entry_name;

            if (is_dir($entry_path)) {
                $folder_name = $this->makeTitleFromName($entry_name);
                $folder_code = $this->makeCodeFromName($entry_name);
                $folder_code = $this->ensureUniqueCode($folder_code, $used_codes);
                $folder = new Document();
                $folder->forceFill([
                    'name' => $folder_name,
                    'code' => $folder_code,
                    'parent_id' => $parent_id,
                    'is_folder' => 1,
                    'active' => 1,
                    'sort_order' => $sort_order,
                ]);
                $folder->save();

                $created_count++;
                $created_count += $this->importDocumentsTree($entry_path, (int) $folder->id);
                $sort_order++;
                continue;
            }

            if (!is_file($entry_path)) {
                continue;
            }

            if (strtolower((string) pathinfo($entry_name, PATHINFO_EXTENSION)) !== 'md') {
                continue;
            }

            $file_basename = (string) pathinfo($entry_name, PATHINFO_FILENAME);
            $file_name = $this->makeTitleFromName($file_basename);
            $file_code = $this->makeCodeFromName($file_basename);
            $file_code = $this->ensureUniqueCode($file_code, $used_codes);
            $markdown_data = file_get_contents($entry_path);
            if ($markdown_data === false) {
                throw new RuntimeException('Не удалось прочитать файл: ' . $entry_path);
            }

            $parsed = Transformers::make()->parseMarkdownFrontmatter($markdown_data);
            $body = $parsed['body'];
            $props_rows = Document::yamlArrayToPropsRepeater($parsed['frontmatter_array']);

            $document = new Document();
            $document->forceFill([
                'name' => $file_name,
                'code' => $file_code,
                'parent_id' => $parent_id,
                'is_folder' => 0,
                'active' => 1,
                'sort_order' => $sort_order,
                'props' => $props_rows,
                'data' => $body,
            ]);
            $document->save();

            $created_count++;
            $sort_order++;
        }

        return $created_count;
    }

    private function makeTitleFromName(string $raw_name): string
    {
        $title = trim($raw_name);
        if ($title !== '') {
            return $title;
        }

        return 'Документ';
    }

    private function makeCodeFromName(string $raw_name): string
    {
        $raw_name = trim($raw_name);
        if ($raw_name === '') {
            return 'document';
        }

        $code = preg_replace('/\s+/u', '-', $raw_name);
        $code = preg_replace('/[^\p{L}\p{N}_-]+/u', '', (string) $code);
        $code = preg_replace('/-+/u', '-', (string) $code);
        $code = trim((string) $code, '-_');

        if ($code === '') {
            $code = 'document';
        }

        return $code;
    }

    /**
     * @param array<int, string> $used_codes
     */
    private function ensureUniqueCode(string $base_code, array &$used_codes): string
    {
        $candidate_code = $base_code;
        $counter = 2;

        while (in_array($candidate_code, $used_codes, true)) {
            $candidate_code = $base_code . '-' . $counter;
            $counter++;
        }

        $used_codes[] = $candidate_code;
        return $candidate_code;
    }

}
