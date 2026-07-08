<?php namespace Zen\Chub\Classes\System;

use Db;
use October\Rain\Exception\ValidationException;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Models\Block;

/**
 * Консольный и программный доступ к реестру UI-блоков (модель Block).
 */
class BlockApp
{
    public const EXPORT_SCHEMA = 'zen.chub.block/export';

    public const EXPORT_VERSION = 1;
    protected BlockPaths $paths;

    protected string $theme_code;

    public function __construct(string $theme_code = 'liner')
    {
        $this->theme_code = $theme_code;
        $this->paths = BlockPaths::make($theme_code);
    }

    public static function make(string $theme_code = 'liner'): self
    {
        return new self($theme_code);
    }

    /**
     * @return array<string, mixed>
     */
    public function help(): array
    {
        return [
            'service' => 'Реестр фронтенд-блоков (zen_chub_blocks). Store Book и partials темы. chub:blocks:sync — auto-bundle SCSS/JS.',
            'commands' => [
                [
                    'name' => 'help',
                    'usage' => 'php artisan chub:block help',
                    'description' => 'Эта справка в JSON.',
                ],
                [
                    'name' => 'list',
                    'usage' => 'php artisan chub:block list',
                    'description' => 'Краткий список всех записей.',
                ],
                [
                    'name' => 'show',
                    'usage' => 'php artisan chub:block show {id}',
                    'description' => 'Полный снимок: code, files, description из .md.',
                ],
                [
                    'name' => 'create',
                    'usage' => 'php artisan chub:block create --data=\'{...}\'',
                    'description' => 'Создание записи и scaffold partials при наличии code.',
                ],
                [
                    'name' => 'update',
                    'usage' => 'php artisan chub:block update {id} --data=\'{...}\'',
                    'description' => 'Изменение полей по id.',
                ],
                [
                    'name' => 'delete',
                    'usage' => 'php artisan chub:block delete {id}',
                    'description' => 'Удаление записи из реестра (файлы темы не удаляются).',
                ],
                [
                    'name' => 'import',
                    'usage' => 'php artisan chub:block import {code} [--no-bootstrap]',
                    'description' => 'Импорт из partials; bootstrap создаёт недостающие .md, .variants.json, .json.',
                ],
                [
                    'name' => 'import-all',
                    'usage' => 'php artisan chub:block import-all [--no-bootstrap]',
                    'description' => 'Импорт всех partials с {name}.htm + meta/json в реестр.',
                ],
                [
                    'name' => 'export-data',
                    'usage' => 'php artisan chub:block export-data',
                    'description' => 'Экспорт всех записей Block в *.meta.json и folders_data.json.',
                ],
                [
                    'name' => 'restore-data',
                    'usage' => 'php artisan chub:block restore-data',
                    'description' => 'Полное восстановление реестра из export-файлов темы.',
                ],
                [
                    'name' => 'lint-dom-ids',
                    'usage' => 'php artisan chub:block lint-dom-ids',
                    'description' => 'Проверка uuid/dom_id на корневых элементах partial-блоков.',
                ],
            ],
            'payload_create_update' => [
                'name' => 'string, обязательно при создании',
                'code' => 'string, обязательно для блока (не папки): header или alerts/alert',
                'description' => 'string|null, markdown в themes/{theme}/partials/{group}/{name}.md',
                'parent_id' => 'int|null',
                'is_folder' => '0|1',
                'sort_order' => 'int|null',
                'files' => 'array|null — если пусто при create, добавится главный .htm',
            ],
            'code_rules' => [
                'header' => 'partials/header/header.htm',
                'alerts/alert' => 'partials/alerts/alert.htm',
            ],
            'export' => [
                'block_file' => 'themes/{theme}/partials/{group}/{name}.meta.json',
                'folders_file' => 'plugins/zen/chub/data/blocks/folders_data.json',
                'schema' => self::EXPORT_SCHEMA,
                'version' => self::EXPORT_VERSION,
            ],
        ];
    }

    /**
     * Экспорт всех записей: блоки → *.meta.json, папки → folders_data.json.
     *
     * @return array{blocks: int, folders: int}
     */
    public function exportAllToTheme(): array
    {
        $blocks_written = 0;
        $folder_records = [];
        $active_codes = [];

        foreach (Block::query()->orderBy('id')->get() as $block) {
            if ((bool) ($block->is_folder ?? false)) {
                $folder_records[] = $this->exportRecordPayload($block);

                continue;
            }

            $code = trim((string) ($block->code ?? ''));
            if ($code === '') {
                continue;
            }

            $this->writeBlockMetaExport($block);
            $active_codes[] = $code;
            $blocks_written++;
        }

        $this->writeFoldersExport($folder_records);
        $this->pruneStaleExportMetaFiles($active_codes);

        return [
            'blocks' => $blocks_written,
            'folders' => count($folder_records),
        ];
    }

    /**
     * Полное восстановление реестра из export-файлов (truncate + insert).
     *
     * @return array{restored: int, blocks: int, folders: int}
     */
    public function restoreAllFromTheme(): array
    {
        $records = [];

        foreach ($this->readFoldersExport() as $row) {
            $records[] = $this->normalizeExportRecord($row);
        }

        foreach ($this->paths->scanMetaJsonFiles() as $meta_path) {
            $document = $this->readMetaDocumentAtPath($meta_path);
            $record = $this->extractExportRecord($document);
            if ($record === null) {
                continue;
            }

            $records[] = $this->normalizeExportRecord($record);
        }

        if ($records === []) {
            throw new \RuntimeException('Не найдено export-данных Block (*.meta.json или folders_data.json).');
        }

        $records = $this->dedupeExportRecords($records);

        usort($records, fn (array $a, array $b): int => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));

        Db::transaction(function () use ($records): void {
            Block::truncate();

            foreach ($records as $row) {
                $block = new Block();
                $block->forceFill($row);
                $block->save();
            }

            $this->syncBlockIdSequence();
        });

        $folders = 0;
        $blocks = 0;
        foreach ($records as $row) {
            if ((bool) ($row['is_folder'] ?? false)) {
                $folders++;
            } else {
                $blocks++;
            }
        }

        return [
            'restored' => count($records),
            'blocks' => $blocks,
            'folders' => $folders,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMinimal(): array
    {
        return Block::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Block $block) => $this->listItem($block))
            ->values()
            ->all();
    }

    /**
     * Варианты представления блока для превью (store-book / админка).
     *
     * @return list<array<string, mixed>>
     */
    public function listPresentationVariants(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return [];
        }

        return array_map(function (array $definition) use ($code): array {
            return [
                'code' => $definition['code'],
                'label' => $definition['label'],
                'width' => $definition['width'],
                'partial_key' => $definition['partial_key'],
                'body_class' => $definition['body_class'],
                'demo' => $definition['demo'],
                'has_variant_file' => $definition['has_variant_file'],
                'preview_url' => $this->previewUrl($code, $definition['code'], (int) $definition['width'], $definition['demo']),
            ];
        }, $this->readVariantDefinitions($code));
    }

    public function previewUrl(string $code, string $variant_code = 'default', ?int $width = null, ?string $demo = null): string
    {
        $variant_code = trim($variant_code) !== '' ? trim($variant_code) : 'default';
        $params = [
            'code' => trim($code),
            'variant' => $variant_code,
        ];

        if ($width === null || $width <= 0) {
            foreach ($this->readVariantDefinitions($code) as $definition) {
                if ($definition['code'] === $variant_code) {
                    $width = (int) $definition['width'];
                    break;
                }
            }
        }

        if ($width !== null && $width > 0) {
            $params['width'] = max(200, min(2560, $width));
        }

        if ($demo !== null && trim($demo) !== '') {
            $params['demo'] = trim($demo);
        }

        return \Url::to('/store-book/preview').'?'.http_build_query($params);
    }

    /**
     * Контекст embed-превью для CMS-страницы /store-book/preview.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function buildPreviewContext(string $code, string $variant_code = 'default', array $overrides = []): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['error' => 'Не указан параметр code'];
        }

        $definitions = $this->readVariantDefinitions($code);
        if ($definitions === []) {
            return ['error' => "Блок «{$code}»: варианты представления не найдены"];
        }

        $variant_code = trim($variant_code) !== '' ? trim($variant_code) : 'default';
        $definition = null;
        foreach ($definitions as $item) {
            if ($item['code'] === $variant_code) {
                $definition = $item;
                break;
            }
        }

        if ($definition === null) {
            return ['error' => "Блок «{$code}»: вариант «{$variant_code}» не найден"];
        }

        $partial_key = (string) $definition['partial_key'];
        try {
            $partial_path = $this->paths->partialKeyToAbsolute($partial_key);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }

        if (!is_file($partial_path)) {
            return ['error' => "Partial не найден: {$partial_key} ({$partial_path})"];
        }

        $width_override = $overrides['width'] ?? null;
        $width = is_numeric($width_override) && (int) $width_override > 0
            ? max(200, min(2560, (int) $width_override))
            : (int) $definition['width'];

        $demo_id = trim((string) ($overrides['demo'] ?? $definition['demo'] ?? 'default'));
        if ($demo_id === '') {
            $demo_id = 'default';
        }

        return [
            'error' => null,
            'code' => $code,
            'variant_code' => $definition['code'],
            'variant_label' => $definition['label'],
            'partial_key' => $partial_key,
            'partial_params' => [
                'data' => $this->loadPartialDemoParams($code, $definition['code'], $demo_id),
                'variant' => $variant_code,
                '_store_book' => true,
                'demo' => $demo_id,
            ],
            'width' => $width,
            'body_class' => (string) $definition['body_class'],
            'demo' => $demo_id,
            'preview_viewport_width' => $width,
        ];
    }

    /**
     * Блоки для оглавления store-book (не папки, с code).
     *
     * @return list<array<string, mixed>>
     */
    public function listStoreBookEntries(): array
    {
        return Block::query()
            ->where('is_folder', 0)
            ->whereNotNull('code')
            ->where('code', '<>', '')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Block $block) {
                $code = (string) $block->code;

                return [
                    'id' => (int) $block->id,
                    'code' => $code,
                    'name' => (string) ($block->name ?? ''),
                    'partial_key' => $this->paths->partialKey($code),
                    'store_book_url' => $this->storeBookViewUrl($code),
                    'preview_url' => $this->previewUrl($code, 'default'),
                    'files_count' => count(is_array($block->files) ? $block->files : []),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function showFull(int $id): ?array
    {
        $block = Block::find($id);

        return $block ? $this->snapshot($block) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function showByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $block = Block::query()->where('code', $code)->first();

        return $block ? $this->snapshot($block) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromData(array $data): Block
    {
        unset($data['id']);

        $description = array_key_exists('description', $data) ? (string) ($data['description'] ?? '') : null;
        unset($data['description']);

        $model = new Block();
        $this->applyPayload($model, $data, true);

        if ($description !== null) {
            $model->description = $description;
        }

        try {
            $model->save();
        } catch (ValidationException $e) {
            throw new \RuntimeException('Валидация: '.$e->getMessage(), 0, $e);
        }

        if (!(bool) ($model->is_folder ?? false) && trim((string) ($model->code ?? '')) !== '') {
            $this->scaffoldThemeFiles($model, $description ?? '');
            $this->bootstrapThemeFiles($model);
            $this->syncBlockFilesFromTheme($model);
            $model = $model->fresh() ?? $model;
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFromData(int $id, array $data): ?Block
    {
        $model = Block::find($id);
        if (!$model) {
            return null;
        }

        unset($data['id']);

        $description = array_key_exists('description', $data) ? (string) ($data['description'] ?? '') : null;
        unset($data['description']);

        $this->applyPayload($model, $data, false);

        if ($description !== null) {
            $model->description = $description;
        }

        try {
            $model->save();
        } catch (ValidationException $e) {
            throw new \RuntimeException('Валидация: '.$e->getMessage(), 0, $e);
        }

        if (!(bool) ($model->is_folder ?? false) && trim((string) ($model->code ?? '')) !== '') {
            $this->syncBlockFilesFromTheme($model);
        }

        return $model->fresh() ?? $model;
    }

    public function deleteById(int $id): bool
    {
        $model = Block::find($id);

        return $model ? (bool) $model->delete() : false;
    }

    /**
     * Импорт папки partials в реестр (создание или обновление по code).
     */
    public function importFromTheme(string $code, bool $bootstrap = true): Block
    {
        $code = trim($code);
        BlockPaths::parseCode($code);

        $dir = $this->paths->partialsDir($code);
        if (!is_dir($dir)) {
            throw new \RuntimeException("Папка блока не найдена: {$dir}");
        }

        $export_record = $this->readExportRecord($code);
        if ($export_record !== null) {
            $block = $this->upsertFromExportRecord($export_record);
        } else {
            $parsed = BlockPaths::parseCode($code);
            $meta = $this->readLegacyMeta($code);
            $name = (string) ($meta['title'] ?? $parsed['name']);
            $description = (string) ($meta['description'] ?? '');

            $files = $this->discoverThemeFiles($code, $name, $meta);
            $existing = Block::query()->where('code', $code)->first();

            if ($existing) {
                $existing->name = $name;
                $existing->files = $this->appendDescriptionFile($files, $code);
                $existing->description = $description;
                $existing->save();
                $block = $existing->fresh() ?? $existing;
            } else {
                $block = $this->createFromData([
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'is_folder' => 0,
                    'files' => $files,
                ]);

                $block->files = $this->appendDescriptionFile(is_array($block->files) ? $block->files : [], $code);
                $block->save();
                $block = $block->fresh() ?? $block;
            }
        }

        if ($bootstrap) {
            $this->bootstrapThemeFiles($block);
        }

        $this->syncBlockFilesFromTheme($block);

        return $block->fresh() ?? $block;
    }

    /**
     * @return array{imported: list<array{code: string, id: int}>, errors: list<array{code: string, error: string}>}
     */
    public function importAllFromTheme(bool $bootstrap = true): array
    {
        $imported = [];
        $errors = [];

        foreach ($this->paths->discoverImportableCodes() as $code) {
            try {
                $block = $this->importFromTheme($code, $bootstrap);
                $imported[] = [
                    'code' => (string) ($block->code ?? $code),
                    'id' => (int) $block->id,
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'code' => $code,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    public function storeBookViewUrl(string $code): string
    {
        return \Url::to('/store-book/view').'?'.http_build_query([
            'code' => trim($code),
        ]);
    }

    private function upsertFromExportRecord(array $record): Block
    {
        $record = $this->normalizeExportRecord($record);
        $code = trim((string) ($record['code'] ?? ''));

        if ($code === '') {
            throw new \InvalidArgumentException('Export record: отсутствует code.');
        }

        $existing = Block::query()->where('code', $code)->first();
        if (!$existing && !empty($record['id'])) {
            $existing = Block::query()->find((int) $record['id']);
        }

        if ($existing) {
            $existing->forceFill($record);
            $existing->save();

            return $existing->fresh() ?? $existing;
        }

        $block = new Block();
        $block->forceFill($record);
        $block->save();

        return $block->fresh() ?? $block;
    }

    private function writeBlockMetaExport(Block $block): void
    {
        $code = trim((string) ($block->code ?? ''));
        if ($code === '') {
            return;
        }

        $document = [
            'schema' => self::EXPORT_SCHEMA,
            'version' => self::EXPORT_VERSION,
            'record' => $this->exportRecordPayload($block),
            'description_path' => $this->paths->descriptionRelative($code),
        ];

        $path = $this->paths->metaJsonAbsolute($code);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = Transformers::make()->toJson($document, true, true);
        file_put_contents($path, ($json ?? '{}')."\n");
    }

    /**
     * Удаляет export *.meta.json для блоков, которых уже нет в реестре.
     * Legacy meta без schema zen.chub.block/export не затрагиваются.
     *
     * @param list<string> $active_codes
     */
    private function pruneStaleExportMetaFiles(array $active_codes): void
    {
        $active_lookup = array_fill_keys($active_codes, true);

        foreach ($this->paths->scanMetaJsonFiles() as $meta_path) {
            $document = $this->readMetaDocumentAtPath($meta_path);
            if ($this->extractExportRecord($document) === null) {
                continue;
            }

            $code = trim((string) ($document['record']['code'] ?? ''));
            if ($code === '' || !isset($active_lookup[$code])) {
                if (is_file($meta_path)) {
                    unlink($meta_path);
                }
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $folder_records
     */
    private function writeFoldersExport(array $folder_records): void
    {
        $path = BlockPaths::foldersDataPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        Transformers::make()->arrayToFile($folder_records, $path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readFoldersExport(): array
    {
        $path = BlockPaths::foldersDataPath();
        if (!is_file($path)) {
            return [];
        }

        $data = Transformers::make()->arrayFromFile($path);

        if (!is_array($data)) {
            return [];
        }

        $rows = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportRecordPayload(Block $block): array
    {
        return [
            'id' => (int) $block->id,
            'code' => $block->code,
            'name' => (string) ($block->name ?? ''),
            'parent_id' => $block->parent_id !== null ? (int) $block->parent_id : null,
            'is_folder' => (int) ((bool) ($block->is_folder ?? false)),
            'sort_order' => $block->sort_order !== null ? (int) $block->sort_order : null,
            'files' => is_array($block->files) ? $block->files : [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function normalizeExportRecord(array $record): array
    {
        $parent_id = $record['parent_id'] ?? null;
        $sort_order = $record['sort_order'] ?? null;

        return [
            'id' => isset($record['id']) ? (int) $record['id'] : null,
            'code' => array_key_exists('code', $record) && $record['code'] !== null && $record['code'] !== ''
                ? (string) $record['code']
                : null,
            'name' => (string) ($record['name'] ?? ''),
            'parent_id' => $parent_id === null || $parent_id === '' ? null : (int) $parent_id,
            'is_folder' => (int) ((bool) ($record['is_folder'] ?? false)),
            'sort_order' => $sort_order === null || $sort_order === '' ? null : (int) $sort_order,
            'files' => Block::normalizeFiles(is_array($record['files'] ?? null) ? $record['files'] : []),
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<array<string, mixed>>
     */
    private function dedupeExportRecords(array $records): array
    {
        $by_id = [];

        foreach ($records as $record) {
            $id = (int) ($record['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $by_id[$id] = $record;
        }

        return array_values($by_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readExportRecord(string $code): ?array
    {
        $document = $this->readMetaDocument($code);

        return $this->extractExportRecord($document);
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetaDocument(string $code): array
    {
        return $this->readMetaDocumentAtPath($this->paths->metaJsonAbsolute($code));
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetaDocumentAtPath(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>|null
     */
    private function extractExportRecord(array $document): ?array
    {
        if (($document['schema'] ?? '') !== self::EXPORT_SCHEMA) {
            return null;
        }

        $record = $document['record'] ?? null;

        return is_array($record) ? $record : null;
    }

    private function syncBlockIdSequence(): void
    {
        Db::statement("
            SELECT setval(
                pg_get_serial_sequence('zen_chub_blocks', 'id'),
                COALESCE((SELECT MAX(id) FROM zen_chub_blocks), 0) + 1,
                false
            )
        ");
    }

    /**
     * @param list<array{name: string, description: string}> $files
     * @return list<array{name: string, description: string}>
     */
    private function appendDescriptionFile(array $files, string $code): array
    {
        $relative = $this->paths->descriptionRelative($code);
        foreach ($files as $row) {
            if (($row['name'] ?? '') === $relative) {
                return Block::normalizeFiles($files);
            }
        }

        if (is_file($this->paths->descriptionAbsolute($code))) {
            $files[] = [
                'name' => $relative,
                'description' => 'Описание блока (markdown)',
            ];
        }

        return Block::normalizeFiles($files);
    }

    /**
     * @return array<string, mixed>
     */
    private function readLegacyMeta(string $code): array
    {
        $document = $this->readMetaDocument($code);
        if ($this->extractExportRecord($document) !== null) {
            return [];
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array{name: string, description: string}>
     */
    private function discoverThemeFiles(string $code, string $block_title, array $meta): array
    {
        $dir = $this->paths->partialsDir($code);
        $parsed = BlockPaths::parseCode($code);
        $files = [];

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $absolute = $dir.'/'.$entry;
            if (!is_file($absolute)) {
                continue;
            }

            $files[] = [
                'name' => $this->paths->relativeFromThemeRoot($absolute),
                'description' => $this->guessFileDescription($entry, $parsed, $block_title, $meta),
            ];
        }

        usort($files, function (array $a, array $b) use ($parsed): int {
            $a_main = str_ends_with($a['name'], '/'.$parsed['name'].'.htm') ? 0 : 1;
            $b_main = str_ends_with($b['name'], '/'.$parsed['name'].'.htm') ? 0 : 1;
            if ($a_main !== $b_main) {
                return $a_main <=> $b_main;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return Block::normalizeFiles($files);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function guessFileDescription(string $filename, array $parsed, string $block_title, array $meta): string
    {
        $name = $parsed['name'];
        $group = $parsed['group'];

        if ($filename === $name.'.htm') {
            return $block_title !== '' ? $block_title : 'Главный partial блока';
        }

        if ($filename === $name.'.md') {
            return 'Описание блока (markdown)';
        }

        if ($filename === $name.'.meta.json') {
            return 'Экспорт записи Block (JSON)';
        }

        if ($filename === $name.'.json') {
            return 'Демо-данные для превью';
        }

        if ($filename === $name.'.variants.json') {
            return 'Варианты представления (JSON)';
        }

        if (preg_match('/^'.preg_quote($name, '/').'\\.([^.]+)\\.json$/', $filename, $matches)) {
            return 'Демо-данные варианта '.$matches[1];
        }

        if (str_ends_with($filename, '.scss')) {
            return 'Стили блока';
        }

        if (str_ends_with($filename, '.js')) {
            return 'Скрипты блока';
        }

        if (str_ends_with($filename, '.htm')) {
            return 'Дочерний partial: '.$group.'/'.pathinfo($filename, PATHINFO_FILENAME);
        }

        return $filename;
    }

    private function scaffoldThemeFiles(Block $model, string $description): void
    {
        $code = trim((string) ($model->code ?? ''));
        if ($code === '') {
            return;
        }

        $parsed = BlockPaths::parseCode($code);
        $dir = $this->paths->partialsDir($code);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $htm_path = $this->paths->mainHtmAbsolute($code);
        if (!is_file($htm_path)) {
            $dom_id = $this->paths->domId($code);
            $markup = <<<TWIG
[viewBag]
==
<?php
use Zen\Chub\Classes\System\BlockPartial;

function onStart()
{
    BlockPartial::mount(\$this, '{$code}');
}
?>
==
<section uuid="{{ block_dom_id }}" class="{$parsed['group']}" data-block="{$code}">
{# TODO: разметка блока {$parsed['partial_key']} #}
</section>
TWIG;
            file_put_contents($htm_path, $markup);
        }

        $md_path = $this->paths->descriptionAbsolute($code);
        if (!is_file($md_path)) {
            $title = trim((string) ($model->name ?? $parsed['name']));
            $body = trim($description) !== '' ? trim($description) : "Блок **{$title}** (`{$code}`).";
            file_put_contents($md_path, "# {$title}\n\n{$body}\n");
        } elseif (trim($description) !== '') {
            $this->paths->writeDescription($code, $description);
        }

        $demo_json_path = $this->paths->demoJsonAbsolute($code);
        if (!is_file($demo_json_path)) {
            file_put_contents(
                $demo_json_path,
                json_encode(['default' => (object) []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            );
        }

        $files = is_array($model->files) ? $model->files : [];
        if ($files === []) {
            $main_relative = $this->paths->mainHtmRelative($code);
            $files[] = [
                'name' => $main_relative,
                'description' => trim((string) ($model->name ?? '')) !== ''
                    ? (string) $model->name
                    : 'Главный partial блока',
            ];
            $demo_relative = $this->paths->demoJsonRelative($code);
            if (is_file($this->paths->demoJsonAbsolute($code))) {
                $files[] = [
                    'name' => $demo_relative,
                    'description' => 'Демо-данные для превью',
                ];
            }
            $model->files = $files;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Block $block): array
    {
        $files = is_array($block->files) ? $block->files : [];

        return [
            'id' => (int) $block->id,
            'code' => $block->code,
            'name' => (string) ($block->name ?? ''),
            'parent_id' => $block->parent_id !== null ? (int) $block->parent_id : null,
            'is_folder' => (int) ((bool) ($block->is_folder ?? false)),
            'files_count' => count($files),
            'sort_order' => $block->sort_order !== null ? (int) $block->sort_order : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Block $block): array
    {
        $files = is_array($block->files) ? $block->files : [];
        $code = trim((string) ($block->code ?? ''));

        return [
            'id' => (int) $block->id,
            'code' => $block->code,
            'name' => (string) ($block->name ?? ''),
            'description' => $block->description,
            'description_path' => $code !== '' ? $this->paths->descriptionRelative($code) : null,
            'partial_key' => $code !== '' ? $this->paths->partialKey($code) : null,
            'dom_id' => $code !== '' ? $this->paths->domId($code) : null,
            'parent_id' => $block->parent_id !== null ? (int) $block->parent_id : null,
            'is_folder' => (int) ((bool) ($block->is_folder ?? false)),
            'sort_order' => $block->sort_order !== null ? (int) $block->sort_order : null,
            'files' => $files,
            'files_count' => count($files),
            'store_book_url' => $code !== '' ? $this->storeBookViewUrl($code) : null,
            'presentation_variants' => $code !== '' ? $this->listPresentationVariants($code) : [],
            'demo_scenarios' => $code !== '' ? $this->paths->listDemoScenarioIds($code) : [],
            'demo_json_files' => $code !== ''
                ? array_map(
                    static fn (array $slot): string => (string) ($slot['relative'] ?? ''),
                    $this->paths->listDemoJsonSlots($code)
                )
                : [],
            'created_at' => $block->created_at ? (string) $block->created_at : null,
            'updated_at' => $block->updated_at ? (string) $block->updated_at : null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(Block $model, array $payload, bool $is_create): void
    {
        $allowed = array_flip([
            'code',
            'name',
            'parent_id',
            'is_folder',
            'files',
            'sort_order',
        ]);

        foreach ($payload as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }

            if ($key === 'files') {
                $model->files = is_array($value) ? $value : [];

                continue;
            }

            $model->{$key} = $value;
        }

        if ($is_create && trim((string) ($model->name ?? '')) === '') {
            throw new \InvalidArgumentException('Поле name обязательно при создании.');
        }

        if ($is_create && !(bool) ($model->is_folder ?? false) && trim((string) ($model->code ?? '')) === '') {
            throw new \InvalidArgumentException('Поле code обязательно при создании блока (не папки).');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readVariantDefinitions(string $code): array
    {
        $indexed = [];

        foreach ($this->paths->readVariantsJson($code) as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $definition = $this->normalizeVariantDefinition($code, $raw);
            $indexed[$definition['code']] = $definition;
        }

        foreach ($this->paths->discoverVariantHtmCodes($code) as $discovered_code) {
            if (isset($indexed[$discovered_code])) {
                continue;
            }

            $indexed[$discovered_code] = $this->normalizeVariantDefinition($code, [
                'code' => $discovered_code,
                'label' => ucfirst(str_replace(['-', '_'], ' ', $discovered_code)),
            ]);
        }

        if (!isset($indexed['default'])) {
            $indexed['default'] = $this->normalizeVariantDefinition($code, [
                'code' => 'default',
                'label' => 'Базовый',
                'width' => 1280,
            ]);
        }

        $result = [$indexed['default']];
        unset($indexed['default']);
        ksort($indexed);

        return array_merge($result, array_values($indexed));
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function normalizeVariantDefinition(string $code, array $raw): array
    {
        $variant_code = trim((string) ($raw['code'] ?? 'default'));
        if ($variant_code === '') {
            $variant_code = 'default';
        }

        $partial_override = trim(str_replace('\\', '/', (string) ($raw['partial'] ?? '')));
        $partial_key = $partial_override !== ''
            ? $partial_override
            : $this->paths->resolveVariantPartialKey($code, $variant_code);

        return [
            'code' => $variant_code,
            'label' => trim((string) ($raw['label'] ?? $variant_code)) ?: $variant_code,
            'partial_key' => $partial_key,
            'width' => max(200, min(2560, (int) ($raw['width'] ?? 1280))),
            'body_class' => trim((string) ($raw['body_class'] ?? '')),
            'demo' => trim((string) ($raw['demo'] ?? 'default')) ?: 'default',
            'has_variant_file' => $variant_code !== 'default'
                && is_file($this->paths->variantHtmAbsolute($code, $variant_code)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPartialDemoParams(string $code, string $presentation_variant, string $demo_id): array
    {
        return $this->paths->readDemoData($code, $presentation_variant, $demo_id);
    }

    private function bootstrapThemeFiles(Block $block): void
    {
        $code = trim((string) ($block->code ?? ''));
        if ($code === '' || (bool) ($block->is_folder ?? false)) {
            return;
        }

        $parsed = BlockPaths::parseCode($code);
        $legacy = $this->readLegacyMeta($code);

        if (!is_file($this->paths->descriptionAbsolute($code))) {
            $description = trim((string) ($block->description ?? ''));
            if ($description === '' && !empty($legacy['description'])) {
                $description = (string) $legacy['description'];
            }

            $title = trim((string) ($block->name ?? $parsed['name']));
            $body = $description !== '' ? $description : "Блок **{$title}** (`{$code}`).";
            file_put_contents($this->paths->descriptionAbsolute($code), "# {$title}\n\n{$body}\n");
        }

        if (!is_file($this->paths->variantsJsonAbsolute($code))) {
            file_put_contents(
                $this->paths->variantsJsonAbsolute($code),
                json_encode($this->defaultVariantsDocument(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            );
        }

        if (!is_file($this->paths->demoJsonAbsolute($code))) {
            file_put_contents(
                $this->paths->demoJsonAbsolute($code),
                json_encode(['default' => (object) []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            );
        }
    }

    private function syncBlockFilesFromTheme(Block $block): void
    {
        $code = trim((string) ($block->code ?? ''));
        if ($code === '' || (bool) ($block->is_folder ?? false)) {
            return;
        }

        $meta = $this->readMetaDocument($code);
        if ($this->extractExportRecord($meta) !== null) {
            $meta = [];
        }

        $name = trim((string) ($block->name ?? ''));
        if ($name === '') {
            $name = BlockPaths::parseCode($code)['name'];
        }

        $files = $this->discoverThemeFiles($code, $name, $meta);
        $block->files = $this->appendDescriptionFile($files, $code);
        $block->save();
    }

    /**
     * @return array{variants: list<array<string, mixed>>}
     */
    private function defaultVariantsDocument(): array
    {
        return [
            'variants' => [
                [
                    'code' => 'default',
                    'label' => 'Desktop',
                    'width' => 1280,
                    'demo' => 'default',
                ],
                [
                    'code' => 'mobile',
                    'label' => 'Mobile',
                    'width' => 375,
                    'demo' => 'default',
                ],
            ],
        ];
    }
}
