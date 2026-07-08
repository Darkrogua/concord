<?php namespace Zen\Chub\Classes\System;

use Db;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Models\Feature;

class FeatureApp
{
    public static function make(): self
    {
        return new self();
    }

    public static function defaultDataFilePath(): string
    {
        return base_path('plugins/zen/chub/data/featurelist/features_data.json');
    }

    public function saveToDataFile(?string $path = null): void
    {
        $path = $path ?: self::defaultDataFilePath();
        $dir_path = dirname($path);
        if (! is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }

        $features = [];
        foreach (Feature::orderBy('sort_order')->orderBy('name')->get() as $feature) {
            $features[] = $this->featureRowForExport($feature);
        }

        $dependencies = [];
        foreach (Db::table('zen_chub_feature_dependencies')->get() as $edge) {
            $dependencies[] = [
                'feature_id' => (string) $edge->feature_id,
                'depends_on_feature_id' => (string) $edge->depends_on_feature_id,
                'comment' => $edge->comment !== null ? (string) $edge->comment : null,
            ];
        }

        Transformers::make()->arrayToFile([
            'features' => $features,
            'dependencies' => $dependencies,
        ], $path);
    }

    public function restoreFromDataFile(?string $path = null): int
    {
        $path = $path ?: self::defaultDataFilePath();
        $payload = Transformers::make()->arrayFromFile($path);
        if ($payload === null || $payload === []) {
            return 0;
        }

        $features = $payload['features'] ?? null;
        if (! is_array($features)) {
            throw new \RuntimeException('Некорректный features_data.json: нет массива features');
        }

        $dependencies = $payload['dependencies'] ?? [];
        if (! is_array($dependencies)) {
            throw new \RuntimeException('Некорректный features_data.json: нет массива dependencies');
        }

        Db::transaction(function () use ($features, $dependencies): void {
            Feature::query()->delete();

            /** @var array<string, string|null> $parent_ids */
            $parent_ids = [];

            foreach ($features as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $feature_id = trim((string) ($row['id'] ?? ''));
                if ($feature_id === '') {
                    continue;
                }

                $parent_ids[$feature_id] = trim((string) ($row['parent_id'] ?? '')) ?: null;
                $row['parent_id'] = null;

                $feature = new Feature();
                $feature->forceFill($row);
                $feature->save();
            }

            foreach ($parent_ids as $feature_id => $parent_id) {
                if ($parent_id === null) {
                    continue;
                }

                Feature::where('id', $feature_id)->update(['parent_id' => $parent_id]);
            }

            foreach ($dependencies as $dep) {
                if (! is_array($dep)) {
                    continue;
                }

                $feature_id = trim((string) ($dep['feature_id'] ?? ''));
                $depends_on_feature_id = trim((string) ($dep['depends_on_feature_id'] ?? ''));
                if ($feature_id === '' || $depends_on_feature_id === '') {
                    continue;
                }

                Db::table('zen_chub_feature_dependencies')->insert([
                    'feature_id' => $feature_id,
                    'depends_on_feature_id' => $depends_on_feature_id,
                    'comment' => trim((string) ($dep['comment'] ?? '')) ?: null,
                ]);
            }
        });

        return count($features);
    }

    /**
     * @return array<string, mixed>
     */
    private function featureRowForExport(Feature $feature): array
    {
        $row = $feature->getAttributes();
        $row['tags'] = $feature->tags;
        $row['acceptance_criteria'] = $feature->acceptance_criteria;
        $row['acceptance_checks'] = $feature->acceptance_checks;
        $row['workflow_files'] = $feature->workflow_files;
        $row['workflow_tags'] = $feature->workflow_tags;

        return $row;
    }

    /**
     * Схема AI-интерфейса (chub:feature ai schema).
     *
     * @return array<string, mixed>
     */
    public function aiSchema(): array
    {
        return [
            'command' => './bin/artisan chub:feature ai <action> [--data=\'{}\'] [--data-file=/abs/path.json]',
            'data_file' => self::defaultDataFilePath(),
            'feature_groups' => ['Frontend', 'Admin', 'Backend', 'SEO'],
            'workflow_statuses' => ['', 'in_progress', 'ready'],
            'actions' => [
                'schema' => [
                    'description' => 'Эта справка',
                    'input' => [],
                ],
                'list' => [
                    'description' => 'Краткий список фич с опциональными фильтрами',
                    'optional' => [
                        'feature_group', 'workflow_status', 'catalog_tag', 'workflow_tag',
                        'parent_id', 'active', 'search',
                    ],
                ],
                'show' => [
                    'description' => 'Полный снимок фичи с зависимостями',
                    'required' => ['id'],
                ],
                'create' => [
                    'description' => 'Создать фичу',
                    'required' => ['name'],
                    'optional' => [
                        'id', 'parent_id', 'section_heading', 'description', 'feature_group',
                        'tags', 'acceptance_criteria', 'acceptance_checks',
                        'workflow_status', 'workflow_comment', 'workflow_files', 'workflow_tags',
                        'active', 'sort_order',
                    ],
                ],
                'update' => [
                    'description' => 'Частичное обновление полей фичи',
                    'required' => ['id'],
                    'optional' => 'любые поля из create',
                ],
                'move' => [
                    'description' => 'Перемещение: parent_id, feature_group (слой), sort_order, direction up/down',
                    'required' => ['id'],
                    'optional' => ['parent_id', 'feature_group', 'sort_order', 'direction'],
                ],
                'link-dependency' => [
                    'description' => 'Добавить исходящую зависимость feature_id → depends_on_feature_id',
                    'required' => ['feature_id', 'depends_on_feature_id'],
                    'optional' => ['comment'],
                ],
                'unlink-dependency' => [
                    'description' => 'Удалить одну исходящую зависимость',
                    'required' => ['feature_id', 'depends_on_feature_id'],
                ],
                'delete' => [
                    'description' => 'Удалить фичу; без force — только если нет связей',
                    'required' => ['id'],
                    'optional' => ['force'],
                ],
                'validate-graph' => [
                    'description' => 'Проверка графа зависимостей на циклы',
                    'input' => [],
                ],
                'validate' => [
                    'description' => 'Dry-run проверка payload без изменений',
                    'required' => ['type'],
                    'allowed_type' => [
                        'create', 'update', 'move', 'link-dependency', 'unlink-dependency', 'delete',
                    ],
                ],
                'export-data' => [
                    'description' => 'Сохранить все фичи и связи в features_data.json',
                    'optional' => ['path'],
                ],
                'restore-data' => [
                    'description' => 'Восстановить из features_data.json (полная замена БД)',
                    'optional' => ['path'],
                ],
            ],
            'payload_notes' => [
                'tags' => 'теги каталога — массив строк',
                'workflow_tags' => 'теги workflow — массив строк',
                'acceptance_criteria' => 'массив строк',
                'acceptance_checks' => 'объект {индекс_критерия: bool}',
                'workflow_files' => 'массив {id, path, note}',
                'parent_id' => 'UUID или null для корня дерева',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function listForAi(array $filters = []): array
    {
        $query = Feature::query()->orderBy('sort_order')->orderBy('name');

        if (array_key_exists('feature_group', $filters) && $filters['feature_group'] !== null && $filters['feature_group'] !== '') {
            $query->where('feature_group', (string) $filters['feature_group']);
        }

        if (array_key_exists('workflow_status', $filters)) {
            $query->where('workflow_status', (string) ($filters['workflow_status'] ?? ''));
        }

        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $query->where('active', (bool) $filters['active']);
        }

        if (array_key_exists('parent_id', $filters)) {
            $parent_id = $filters['parent_id'];
            if ($parent_id === null || $parent_id === '') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (string) $parent_id);
            }
        }

        $catalog_tag = trim((string) ($filters['catalog_tag'] ?? ''));
        if ($catalog_tag !== '') {
            $query->filterCatalogTags([$catalog_tag]);
        }

        $workflow_tag = trim((string) ($filters['workflow_tag'] ?? ''));
        if ($workflow_tag !== '') {
            $driver = $query->getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                $query->whereRaw('workflow_tags::jsonb @> ?::jsonb', [json_encode([$workflow_tag], JSON_UNESCAPED_UNICODE)]);
            } else {
                $query->where('workflow_tags', 'like', '%"'.addcslashes($workflow_tag, '"\\').'"%');
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('section_heading', 'like', $like);
            });
        }

        $items = [];
        foreach ($query->get() as $feature) {
            $items[] = $this->featureListItem($feature);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function showFull(string $id): ?array
    {
        $feature = Feature::find(trim($id));
        if (! $feature) {
            return null;
        }

        $row = $this->featureRowForExport($feature);
        $row['dependencies_out'] = [];
        foreach ($feature->dependencies()->orderBy('name')->get() as $dep) {
            $row['dependencies_out'][] = [
                'id' => (string) $dep->id,
                'name' => (string) $dep->name,
                'feature_group' => $dep->feature_group ? (string) $dep->feature_group : null,
                'comment' => (string) ($dep->pivot->comment ?? ''),
            ];
        }
        $row['dependencies_in'] = $this->getDependedBy((string) $feature->id);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromAiData(array $data): Feature
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Поле name обязательно');
        }

        $feature = new Feature();
        $explicit_id = trim((string) ($data['id'] ?? ''));
        if ($explicit_id !== '') {
            $feature->id = $explicit_id;
        }

        $this->applyAiPayload($feature, $data, true);
        $feature->save();

        return $feature;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFromAiData(string $id, array $data): ?Feature
    {
        $feature = Feature::find(trim($id));
        if (! $feature) {
            return null;
        }

        $this->applyAiPayload($feature, $data, false);
        $feature->save();

        return $feature;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function moveFromAiData(array $data): Feature
    {
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            throw new \InvalidArgumentException('Поле id обязательно');
        }

        $feature = Feature::find($id);
        if (! $feature) {
            throw new \RuntimeException("Фича id={$id} не найдена");
        }

        $direction = trim((string) ($data['direction'] ?? ''));
        if ($direction !== '') {
            if (! in_array($direction, ['up', 'down'], true)) {
                throw new \InvalidArgumentException('direction должен быть up или down');
            }
            $this->moveFeatureByDirection($feature, $direction);

            return $feature->fresh();
        }

        if (array_key_exists('parent_id', $data)) {
            $parent_id = $data['parent_id'];
            if ($parent_id === null || $parent_id === '') {
                $feature->parent_id = null;
            } else {
                $parent_id = (string) $parent_id;
                if ($parent_id === $feature->id) {
                    throw new \InvalidArgumentException('Фича не может быть родителем самой себя');
                }
                if (! Feature::find($parent_id)) {
                    throw new \RuntimeException("Родитель id={$parent_id} не найден");
                }
                $feature->parent_id = $parent_id;
            }
        }

        if (array_key_exists('feature_group', $data)) {
            $feature->feature_group = $this->stringOrNull($data['feature_group']);
        }

        if (array_key_exists('sort_order', $data)) {
            $feature->sort_order = $this->intOrNull($data['sort_order']);
        }

        $feature->save();

        return $feature->fresh();
    }

    public function linkDependency(string $feature_id, string $depends_on_feature_id, ?string $comment = null): void
    {
        $feature_id = trim($feature_id);
        $depends_on_feature_id = trim($depends_on_feature_id);

        if ($feature_id === '' || $depends_on_feature_id === '') {
            throw new \InvalidArgumentException('feature_id и depends_on_feature_id обязательны');
        }

        if ($feature_id === $depends_on_feature_id) {
            throw new \InvalidArgumentException('Фича не может зависеть от самой себя');
        }

        $feature = Feature::find($feature_id);
        if (! $feature) {
            throw new \RuntimeException("Фича id={$feature_id} не найдена");
        }

        if (! Feature::find($depends_on_feature_id)) {
            throw new \RuntimeException("Фича id={$depends_on_feature_id} не найдена");
        }

        $feature->dependencies()->syncWithoutDetaching([
            $depends_on_feature_id => ['comment' => trim((string) ($comment ?? ''))],
        ]);

        $validation = $this->validateDependencyGraph();
        if (! $validation['ok']) {
            $feature->dependencies()->detach($depends_on_feature_id);
            throw new \RuntimeException('Связь создаёт цикл: '.implode('; ', $validation['cycles']));
        }
    }

    public function unlinkDependency(string $feature_id, string $depends_on_feature_id): bool
    {
        $feature_id = trim($feature_id);
        $depends_on_feature_id = trim($depends_on_feature_id);

        $feature = Feature::find($feature_id);
        if (! $feature) {
            throw new \RuntimeException("Фича id={$feature_id} не найдена");
        }

        $detached = $feature->dependencies()->detach($depends_on_feature_id);

        return $detached > 0;
    }

    /**
     * @return array{deleted: bool, id: string}
     */
    public function deleteFeature(string $id, bool $force = false): array
    {
        $id = trim($id);
        $feature = Feature::find($id);
        if (! $feature) {
            throw new \RuntimeException("Фича id={$id} не найдена");
        }

        $outgoing = [];
        foreach ($feature->dependencies()->orderBy('name')->get() as $dep) {
            $outgoing[] = [
                'id' => (string) $dep->id,
                'name' => (string) $dep->name,
            ];
        }
        $incoming = $this->getDependedBy($id);

        if (! $force && ($outgoing !== [] || $incoming !== [])) {
            throw new \RuntimeException(
                'Удаление запрещено: сначала удалите связи через unlink-dependency или передайте force=true. '
                .'Исходящие: '.count($outgoing).', входящие: '.count($incoming)
            );
        }

        if ($force) {
            Db::table('zen_chub_feature_dependencies')
                ->where('feature_id', $id)
                ->orWhere('depends_on_feature_id', $id)
                ->delete();
        }

        $feature->delete();

        return ['deleted' => true, 'id' => $id];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validateAiPayload(string $type, array $data): array
    {
        return match ($type) {
            'create' => $this->validateCreatePayload($data),
            'update' => $this->validateUpdatePayload($data),
            'move' => $this->validateMovePayload($data),
            'link-dependency' => $this->validateLinkPayload($data),
            'unlink-dependency' => $this->validateUnlinkPayload($data),
            'delete' => $this->validateDeletePayload($data),
            default => throw new \InvalidArgumentException("Неизвестный type \"{$type}\""),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyAiPayload(Feature $feature, array $data, bool $is_create): void
    {
        $scalar_fields = [
            'name', 'section_heading', 'description', 'feature_group',
            'workflow_status', 'workflow_comment', 'active', 'sort_order', 'parent_id',
        ];

        foreach ($scalar_fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'parent_id') {
                $value = $data['parent_id'];
                if ($value === null || $value === '') {
                    $feature->parent_id = null;
                } else {
                    $parent_id = (string) $value;
                    if ($feature->id && $parent_id === (string) $feature->id) {
                        throw new \InvalidArgumentException('Фича не может быть родителем самой себя');
                    }
                    if (! Feature::find($parent_id)) {
                        throw new \RuntimeException("Родитель id={$parent_id} не найден");
                    }
                    $feature->parent_id = $parent_id;
                }

                continue;
            }

            if ($field === 'active') {
                $feature->active = (bool) $data['active'];

                continue;
            }

            if ($field === 'sort_order') {
                $feature->sort_order = $this->intOrNull($data['sort_order']);

                continue;
            }

            if ($field === 'workflow_status') {
                $status = (string) ($data['workflow_status'] ?? '');
                if (! in_array($status, Feature::WORKFLOW_STATUSES, true)) {
                    throw new \InvalidArgumentException('Недопустимый workflow_status');
                }
                $feature->workflow_status = $status;

                continue;
            }

            $feature->{$field} = $this->stringOrNull($data[$field]);
        }

        if (array_key_exists('tags', $data)) {
            $feature->tags = Feature::normalizeStringList(is_array($data['tags']) ? $data['tags'] : []);
        }

        if (array_key_exists('workflow_tags', $data)) {
            $feature->workflow_tags = Feature::normalizeStringList(is_array($data['workflow_tags']) ? $data['workflow_tags'] : []);
        }

        if (array_key_exists('acceptance_criteria', $data)) {
            $feature->acceptance_criteria = Feature::normalizeStringList(
                is_array($data['acceptance_criteria']) ? $data['acceptance_criteria'] : []
            );
        }

        if (array_key_exists('acceptance_checks', $data)) {
            $feature->acceptance_checks = is_array($data['acceptance_checks']) ? $data['acceptance_checks'] : [];
        }

        if (array_key_exists('workflow_files', $data)) {
            $feature->workflow_files = Feature::normalizeWorkflowFiles(
                is_array($data['workflow_files']) ? $data['workflow_files'] : []
            );
        }

        if ($is_create && ! array_key_exists('name', $data)) {
            throw new \InvalidArgumentException('Поле name обязательно при создании');
        }
    }

    private function moveFeatureByDirection(Feature $feature, string $direction): void
    {
        $query = Feature::query()->orderBy('sort_order')->orderBy('name');
        if ($feature->parent_id === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $feature->parent_id);
        }

        $siblings = $query->get()->values();
        $index = null;
        foreach ($siblings as $i => $sibling) {
            if ((string) $sibling->id === (string) $feature->id) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            throw new \RuntimeException('Фича не найдена среди siblings');
        }

        $swap_index = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swap_index < 0 || $swap_index >= $siblings->count()) {
            throw new \RuntimeException('Перемещение невозможно: достигнута граница списка');
        }

        /** @var Feature $other */
        $other = $siblings[$swap_index];
        $my_order = $feature->sort_order;
        $other_order = $other->sort_order;

        if ($my_order === null && $other_order === null) {
            $feature->sort_order = $swap_index + 1;
            $other->sort_order = $index + 1;
        } else {
            $feature->sort_order = $other_order;
            $other->sort_order = $my_order;
        }

        $feature->saveQuietly();
        $other->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    private function featureListItem(Feature $feature): array
    {
        return [
            'id' => (string) $feature->id,
            'name' => (string) ($feature->name ?? ''),
            'feature_group' => $feature->feature_group ? (string) $feature->feature_group : null,
            'workflow_status' => (string) ($feature->workflow_status ?? ''),
            'parent_id' => $feature->parent_id ? (string) $feature->parent_id : null,
            'sort_order' => $feature->sort_order !== null ? (int) $feature->sort_order : null,
            'active' => (bool) ($feature->active ?? false),
            'tags' => is_array($feature->tags) ? $feature->tags : [],
            'workflow_tags' => is_array($feature->workflow_tags) ? $feature->workflow_tags : [],
            'dependencies_out_count' => $feature->dependencies()->count(),
            'dependencies_in_count' => count($this->getDependedBy((string) $feature->id)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateCreatePayload(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors[] = 'name обязателен';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateUpdatePayload(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['id'] ?? '')) === '') {
            $errors[] = 'id обязателен';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateMovePayload(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['id'] ?? '')) === '') {
            $errors[] = 'id обязателен';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateLinkPayload(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['feature_id'] ?? '')) === '') {
            $errors[] = 'feature_id обязателен';
        }
        if (trim((string) ($data['depends_on_feature_id'] ?? '')) === '') {
            $errors[] = 'depends_on_feature_id обязателен';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateUnlinkPayload(array $data): array
    {
        return $this->validateLinkPayload($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateDeletePayload(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['id'] ?? '')) === '') {
            $errors[] = 'id обязателен';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string_value = trim((string) $value);

        return $string_value === '' ? null : $string_value;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('sort_order должен быть числом');
        }

        return (int) $value;
    }

    /**
     * @return array<int, array{id: string, name: string, feature_group: string|null}>
     */
    public function getDependedBy(string $feature_id): array
    {
        $feature_id = trim($feature_id);
        if ($feature_id === '') {
            return [];
        }

        $rows = Db::table('zen_chub_feature_dependencies as d')
            ->join('zen_chub_features as f', 'f.id', '=', 'd.feature_id')
            ->where('d.depends_on_feature_id', $feature_id)
            ->orderBy('f.name')
            ->get(['f.id', 'f.name', 'f.feature_group']);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'feature_group' => $row->feature_group ? (string) $row->feature_group : null,
            ];
        }

        return $result;
    }

    /**
     * @return array{ok: bool, cycles: array<int, string>}
     */
    public function validateDependencyGraph(): array
    {
        $edges = Db::table('zen_chub_feature_dependencies')
            ->get(['feature_id', 'depends_on_feature_id']);

        $graph = [];
        foreach ($edges as $edge) {
            $from = (string) $edge->feature_id;
            $to = (string) $edge->depends_on_feature_id;
            $graph[$from] ??= [];
            $graph[$from][] = $to;
        }

        $cycles = [];
        $visited = [];
        $stack = [];

        $dfs = function (string $node) use (&$dfs, &$graph, &$visited, &$stack, &$cycles): void {
            $visited[$node] = true;
            $stack[$node] = true;

            foreach ($graph[$node] ?? [] as $nei) {
                if (isset($stack[$nei])) {
                    $cycles[] = "Цикл: {$node} → {$nei}";

                    continue;
                }
                if (! isset($visited[$nei])) {
                    $dfs($nei);
                }
            }

            unset($stack[$node]);
        };

        foreach (array_keys($graph) as $node) {
            if (! isset($visited[$node])) {
                $dfs($node);
            }
        }

        return [
            'ok' => $cycles === [],
            'cycles' => $cycles,
        ];
    }
}
