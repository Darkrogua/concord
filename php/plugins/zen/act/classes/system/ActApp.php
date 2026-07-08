<?php namespace Zen\Act\Classes\System;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RainLab\User\Models\User;
use Zen\Act\Classes\Support\ActEnvelope;
use Zen\Act\Classes\Support\ActStorage;
use Zen\Act\Models\Act;

class ActApp
{
    public const RESTORE_FORMAT_VERSION = 2;

    public const LEGACY_RESTORE_FORMAT_VERSION = 1;

    public static function make(): self
    {
        return new self();
    }

    /**
     * @return array<string, mixed>
     */
    public function aiSchema(): array
    {
        return [
            'command' => './bin/artisan act:acts ai <action> [--data=\'{}\'] [--data-file=/abs/path.json]',
            'restore_command' => './bin/artisan act:restore-from-storage [--dry-run] [--act=UUID]',
            'playground_command' => './bin/artisan act:playground pull|push|status|list --act=UUID --block=UUID',
            'playground_root' => 'playground/{act_uuid}/{block_uuid}/fragment.html',
            'gallery_assets_path' => 'storage/acts/{act_uuid}/assets/images/{block_uuid}/{image_uuid}.ext',
            'gallery_upload_api' => 'POST /act.api/Assets:upload (multipart: act_id, block_id, file)',
            'gallery_serve_url' => '/act.assets/{act_uuid}/{block_uuid}/{image_uuid}',
            'storage_root' => storage_path('acts'),
            'current_path' => 'storage/acts/{act_uuid}/current.json',
            'legacy_restore_path' => 'storage/acts/{act_uuid}/restore.json',
            'actions' => [
                'schema' => [
                    'description' => 'Эта справка',
                    'input' => [],
                ],
                'list' => [
                    'description' => 'Список актов с опциональными фильтрами',
                    'optional' => ['owner_id', 'search'],
                ],
                'show' => [
                    'description' => 'Полный снимок акта со списком state-файлов',
                    'required' => ['id'],
                ],
                'create' => [
                    'description' => 'Создать акт (автоматически пишется первый state-снимок)',
                    'required' => ['name'],
                    'optional' => ['id', 'description', 'owner_id', 'activate_at', 'stop_at'],
                ],
                'update' => [
                    'description' => 'Частичное обновление полей акта',
                    'required' => ['id'],
                    'optional' => ['name', 'description', 'owner_id', 'activate_at', 'stop_at'],
                ],
                'delete' => [
                    'description' => 'Удалить акт и каталог storage/acts/{id}',
                    'required' => ['id'],
                ],
                'block-list' => [
                    'description' => 'Список SQLite-блоков акта',
                    'required' => ['act_id'],
                ],
                'block-show' => [
                    'description' => 'Показать SQLite-блок акта',
                    'required' => ['act_id', 'id'],
                ],
                'block-create' => [
                    'description' => 'Создать SQLite-блок и запись log',
                    'required' => ['act_id', 'name'],
                    'optional' => ['id', 'data'],
                ],
                'block-update' => [
                    'description' => 'Обновить SQLite-блок и записать log',
                    'required' => ['act_id', 'id'],
                    'optional' => ['name', 'data'],
                ],
                'block-delete' => [
                    'description' => 'Удалить SQLite-блок и записать log',
                    'required' => ['act_id', 'id'],
                ],
                'block-reorder' => [
                    'description' => 'Изменить порядок SQLite-блоков и записать log',
                    'required' => ['act_id', 'block_ids'],
                ],
                'block-verify' => [
                    'description' => 'Проверить hashes blocks и chain log',
                    'required' => ['act_id'],
                ],
                'access-get' => [
                    'description' => 'Список grants для ресурса акта',
                    'required' => ['act_id', 'resource_type', 'resource_id'],
                ],
                'access-set' => [
                    'description' => 'Заменить grants для ресурса',
                    'required' => ['act_id', 'resource_type', 'resource_id', 'grants'],
                ],
                'access-migrate' => [
                    'description' => 'Миграция legacy visibility в access',
                    'optional' => ['act_id', 'dry_run'],
                ],
                'list-states' => [
                    'description' => 'Список версий акта из SQLite snapshots',
                    'required' => ['id'],
                ],
                'restore' => [
                    'description' => 'Восстановить акт из версии snapshots',
                    'required' => ['id'],
                    'optional' => ['snapshot_id', 'snapshot_key'],
                ],
                'merge-states' => [
                    'description' => 'Слить диапазон версий (from_index..to_index, 1 = новейшая)',
                    'required' => ['id', 'from_index', 'to_index'],
                ],
                'validate' => [
                    'description' => 'Dry-run проверка payload без изменений',
                    'required' => ['type'],
                    'allowed_type' => ['create', 'update', 'delete', 'restore'],
                ],
            ],
            'payload_notes' => [
                'id' => 'UUID акта; при create можно задать явно',
                'owner_id' => 'bigint users.id (RainLab.User) или null',
                'activate_at' => 'ISO 8601 или null',
                'stop_at' => 'ISO 8601 или null',
                'snapshot_id' => 'integer id из snapshots; snapshot_key — alias',
                'from_index' => 'номер версии в списке (1 = новейшая)',
                'to_index' => 'номер версии в списке (>= from_index)',
                'gallery_block_data' => '{ type: "gallery", items: [{ id, filename, mime, size_bytes, width, height, alt, active, sort_order }] }',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForOwner(int $owner_id): array
    {
        $query = Act::query()
            ->where('owner_id', $owner_id)
            ->orderByDesc('updated_at')
            ->orderBy('name');

        $items = [];
        foreach ($query->get() as $act) {
            $items[] = $this->actCard($act);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $user_id): array
    {
        $user = User::find($user_id);
        $viewer_login = $user ? (string) ($user->username ?? '') : '';
        $items = [];
        $seen = [];

        $owned = Act::query()
            ->where('owner_id', $user_id)
            ->orderByDesc('updated_at')
            ->orderBy('name')
            ->get();

        foreach ($owned as $act) {
            $card = $this->actCard($act);
            $card['access'] = 'owner';
            $card['is_mine'] = true;
            $items[] = $card;
            $seen[(string) $act->id] = true;
        }

        $candidates = Act::query()
            ->where(function ($query) use ($user_id): void {
                $query
                    ->whereNull('owner_id')
                    ->orWhere('owner_id', '<>', $user_id);
            })
            ->orderByDesc('updated_at')
            ->orderBy('name')
            ->get();

        $access = AccessApp::make();
        foreach ($candidates as $act) {
            $act_id = (string) $act->id;
            if (isset($seen[$act_id])) {
                continue;
            }

            $owner_login = $access->getMeta($act_id)['owner_login'] ?? '';
            $shared = $access->sharedAccessKind($viewer_login !== '' ? $viewer_login : null, $act_id, (string) $owner_login);
            if ($shared === null) {
                continue;
            }

            $card = $this->actCard($act);
            $card['access'] = $shared;
            $card['is_mine'] = false;
            $items[] = $card;
            $seen[$act_id] = true;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{acts: list<array<string, mixed>>, page: array{limit: int, next_cursor: string|null, has_more: bool}}
     */
    public function listForUserPaginated(int $user_id, array $params = []): array
    {
        $user = User::find($user_id);
        $viewer_login = $user ? (string) ($user->username ?? '') : '';
        $limit = 20;

        // Список «Мои акты» — только личное участие (владелец, явный grant, подписант).
        // @public / @authenticated дают доступ по прямой ссылке, но не попадают в список.
        if ($viewer_login === '') {
            return [
                'acts' => [],
                'page' => ['limit' => $limit, 'next_cursor' => null, 'has_more' => false],
            ];
        }

        $viewer_keys = [$viewer_login];

        $sort_created = in_array((string) ($params['sort_created'] ?? ''), ['asc', 'desc'], true)
            ? (string) $params['sort_created']
            : null;

        $owner_logins = $this->parseOwnerLoginsFilter($params['owner_logins'] ?? null);
        $created_from = $this->parseDateFilter($params['created_from'] ?? null, true);
        $created_to = $this->parseDateFilter($params['created_to'] ?? null, false);
        $cursor = $this->decodeListCursor((string) ($params['cursor'] ?? ''));

        $tag_filter = $this->parseTagsFilter($params['tags'] ?? null, $params['tag_ops'] ?? null);
        if ($tag_filter !== null) {
            try {
                $tag_act_ids = TagsApp::make()->resolveActIdsFromFilter(
                    $viewer_login,
                    $tag_filter['tags'],
                    $tag_filter['ops']
                );
            } catch (\InvalidArgumentException $exception) {
                throw $exception;
            }

            if ($tag_act_ids === []) {
                return [
                    'acts' => [],
                    'page' => ['limit' => $limit, 'next_cursor' => null, 'has_more' => false],
                ];
            }
        } else {
            $tag_act_ids = null;
        }

        $placeholders = implode(',', array_fill(0, count($viewer_keys), '?'));
        $visibilitySql = "
            SELECT DISTINCT ON (idx.act_id)
                idx.act_id,
                idx.access,
                idx.owner_login
            FROM zen_act_viewer_index idx
            WHERE idx.viewer_key IN ({$placeholders})
            ORDER BY idx.act_id,
                CASE idx.access WHEN 'owner' THEN 1 WHEN 'shared_act' THEN 2 ELSE 3 END
        ";

        $query = DB::table(DB::raw("({$visibilitySql}) as vis"))
            ->mergeBindings(DB::table('zen_act_viewer_index')->whereIn('viewer_key', $viewer_keys))
            ->join('zen_act_acts as acts', 'acts.id', '=', 'vis.act_id')
            ->select([
                'acts.id',
                'acts.name',
                'acts.owner_id',
                'acts.created_at',
                'acts.updated_at',
                'vis.access',
                'vis.owner_login',
            ]);

        if ($owner_logins !== []) {
            $query->whereIn(DB::raw('LOWER(vis.owner_login)'), array_map('mb_strtolower', $owner_logins));
        }

        if ($created_from !== null) {
            $query->where('acts.created_at', '>=', $created_from);
        }

        if ($created_to !== null) {
            $query->where('acts.created_at', '<=', $created_to);
        }

        if ($tag_act_ids !== null) {
            $query->whereIn('acts.id', $tag_act_ids);
        }

        if ($sort_created !== null) {
            $order = $sort_created === 'asc' ? 'asc' : 'desc';
            if ($cursor !== null && ($cursor['sort'] ?? '') === 'created' && ($cursor['order'] ?? '') === $order) {
                $this->applyCreatedCursor($query, $cursor, $order);
            }
            $query->orderBy('acts.created_at', $order)->orderBy('acts.id', $order);
        } else {
            if ($cursor !== null && ($cursor['sort'] ?? '') === 'default') {
                $this->applyDefaultCursor($query, $cursor);
            }
            $query->orderByDesc('acts.updated_at')->orderBy('acts.name')->orderBy('acts.id');
        }

        $rows = $query->limit($limit + 1)->get();
        $has_more = $rows->count() > $limit;
        if ($has_more) {
            $rows = $rows->slice(0, $limit);
        }

        $acts = [];
        foreach ($rows as $row) {
            $acts[] = $this->actListCardFromRow($row, $viewer_login);
        }

        $next_cursor = null;
        if ($has_more && $rows->isNotEmpty()) {
            $last = $rows->last();
            $next_cursor = $this->encodeListCursor($sort_created, $last);
        }

        return [
            'acts' => $acts,
            'page' => [
                'limit' => $limit,
                'next_cursor' => $next_cursor,
                'has_more' => $has_more,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function parseOwnerLoginsFilter(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $logins = [];
        foreach ($parts as $part) {
            $login = mb_strtolower(trim((string) $part));
            if ($login !== '') {
                $logins[] = $login;
            }
        }

        return array_values(array_unique($logins));
    }

    /**
     * @return array{tags: list<string>, ops: list<string>}|null
     */
    private function parseTagsFilter(mixed $tags_value, mixed $ops_value): ?array
    {
        if ($tags_value === null || $tags_value === '') {
            return null;
        }

        $parts = is_array($tags_value) ? $tags_value : explode(',', (string) $tags_value);
        $tags = [];
        foreach ($parts as $part) {
            $tag = trim((string) $part);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        if ($tags === []) {
            return null;
        }

        $ops = [];
        if ($ops_value !== null && $ops_value !== '') {
            $op_parts = is_array($ops_value) ? $ops_value : explode(',', (string) $ops_value);
            foreach ($op_parts as $op) {
                $ops[] = mb_strtolower(trim((string) $op));
            }
        }

        return ['tags' => $tags, 'ops' => $ops];
    }

    private function parseDateFilter(mixed $value, bool $start_of_day): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $carbon = Carbon::createFromFormat('Y-m-d', $value, 'UTC');
        if ($start_of_day) {
            return $carbon->startOfDay()->format('Y-m-d H:i:s');
        }

        return $carbon->endOfDay()->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeListCursor(string $cursor): ?array
    {
        $cursor = trim($cursor);
        if ($cursor === '') {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @param  object  $row
     */
    private function encodeListCursor(?string $sort_created, object $row): string
    {
        if ($sort_created !== null) {
            $payload = [
                'sort' => 'created',
                'order' => $sort_created,
                'created_at' => (string) $row->created_at,
                'act_id' => (string) $row->id,
            ];
        } else {
            $payload = [
                'sort' => 'default',
                'updated_at' => (string) $row->updated_at,
                'name' => (string) $row->name,
                'act_id' => (string) $row->id,
            ];
        }

        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $cursor
     */
    private function applyDefaultCursor($query, array $cursor): void
    {
        $updated_at = (string) ($cursor['updated_at'] ?? '');
        $name = (string) ($cursor['name'] ?? '');
        $act_id = (string) ($cursor['act_id'] ?? '');
        if ($updated_at === '' || $act_id === '') {
            return;
        }

        $query->where(function ($builder) use ($updated_at, $name, $act_id): void {
            $builder
                ->where('acts.updated_at', '<', $updated_at)
                ->orWhere(function ($sameUpdated) use ($updated_at, $name, $act_id): void {
                    $sameUpdated
                        ->where('acts.updated_at', '=', $updated_at)
                        ->where(function ($nameGroup) use ($updated_at, $name, $act_id): void {
                            $nameGroup
                                ->where('acts.name', '>', $name)
                                ->orWhere(function ($sameName) use ($updated_at, $name, $act_id): void {
                                    $sameName
                                        ->where('acts.name', '=', $name)
                                        ->where('acts.id', '>', $act_id);
                                });
                        });
                });
        });
    }

    /**
     * @param  array<string, mixed>  $cursor
     */
    private function applyCreatedCursor($query, array $cursor, string $order): void
    {
        $created_at = (string) ($cursor['created_at'] ?? '');
        $act_id = (string) ($cursor['act_id'] ?? '');
        if ($created_at === '' || $act_id === '') {
            return;
        }

        if ($order === 'asc') {
            $query->where(function ($builder) use ($created_at, $act_id): void {
                $builder
                    ->where('acts.created_at', '>', $created_at)
                    ->orWhere(function ($sameCreated) use ($created_at, $act_id): void {
                        $sameCreated
                            ->where('acts.created_at', '=', $created_at)
                            ->where('acts.id', '>', $act_id);
                    });
            });

            return;
        }

        $query->where(function ($builder) use ($created_at, $act_id): void {
            $builder
                ->where('acts.created_at', '<', $created_at)
                ->orWhere(function ($sameCreated) use ($created_at, $act_id): void {
                    $sameCreated
                        ->where('acts.created_at', '=', $created_at)
                        ->where('acts.id', '<', $act_id);
                });
        });
    }

    /**
     * @param  object  $row
     * @return array<string, mixed>
     */
    private function actListCardFromRow(object $row, string $viewer_login): array
    {
        $access = (string) ($row->access ?? 'owner');
        $owner_login = (string) ($row->owner_login ?? '');
        $is_mine = $access === 'owner'
            || ($viewer_login !== '' && strcasecmp($viewer_login, $owner_login) === 0);

        return [
            'id' => (string) $row->id,
            'name' => (string) ($row->name ?? ''),
            'access' => $access,
            'is_mine' => $is_mine,
            'created_at' => $row->created_at ? Carbon::parse((string) $row->created_at)->toIso8601String() : null,
            'owner_login' => $owner_login !== '' ? $owner_login : null,
            'owner_display_name' => $owner_login !== '' ? $this->displayNameForLogin($owner_login) : null,
        ];
    }

    private function displayNameForLogin(string $login): string
    {
        $user = AuthApp::make()->findByLogin($login);
        if (! $user) {
            return $login;
        }

        if ($user->name !== null && trim((string) $user->name) !== '') {
            return trim((string) $user->name);
        }

        return (string) ($user->username ?? $login);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForOwner(int $owner_id, array $data): Act
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Поле name обязательно');
        }

        $act = new Act();
        $act->name = $name;
        $act->owner_id = $owner_id;
        $act->save();

        return $act;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForOwner(int $owner_id, string $id, array $data): ?Act
    {
        $act = Act::find(trim($id));
        if (! $act) {
            return null;
        }

        if ($act->owner_id === null || (int) $act->owner_id !== $owner_id) {
            throw new \RuntimeException('Нет прав на изменение акта');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Поле name обязательно');
        }

        $act->name = $name;
        $act->save();

        SnapshotApp::make()->finalizeMutation((string) $act->id, 'act', [
            'patch' => ['name' => ['to' => $name]],
        ]);

        return $act;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function showForViewer(string $id, ?int $viewer_id, bool $front_view = false, ?string $as_viewer = null): ?array
    {
        $act = Act::find(trim($id));
        if (! $act) {
            return null;
        }

        $viewer_login = null;
        if ($viewer_id !== null) {
            $viewer = User::find($viewer_id);
            $viewer_login = $viewer ? (string) ($viewer->username ?? '') : null;
        }

        $access = AccessApp::make();
        $is_mine = $viewer_id !== null && $act->owner_id !== null && (int) $act->owner_id === $viewer_id;
        $effective_login = $access->resolveViewerLogin(
            $viewer_login,
            (string) $act->id,
            $front_view,
            $is_mine,
            $as_viewer
        );

        if (! $is_mine || $front_view) {
            if (! $access->can($effective_login, (string) $act->id, 'act', (string) $act->id, 'read', $front_view)) {
                return null;
            }
        }

        $owner = $act->owner;
        $snapshots_count = SnapshotApp::make()->count((string) $act->id);

        $payload = [
            'act' => $this->actCard($act),
            'owner' => $owner ? [
                'login' => (string) ($owner->username ?? ''),
                'display_name' => $this->ownerDisplayName($owner),
            ] : null,
            'is_mine' => $is_mine,
        ];

        if ($is_mine) {
            $payload['stats'] = [
                'snapshots_count' => $snapshots_count,
            ];
        }

        if ($viewer_login !== null && trim($viewer_login) !== '') {
            $tag_login = trim($viewer_login);
            if ($is_mine || $access->hasReadableGrant($tag_login, (string) $act->id)) {
                $payload['my_tags'] = TagsApp::make()->listForUser((string) $act->id, $tag_login);
            }
        }

        return $payload;
    }

    public function recordStateSnapshot(string $act_id): int
    {
        return SnapshotApp::make()->finalizeMutation($act_id, 'all', ['action' => 'snapshot']);
    }

    public function syncCurrentFile(string $act_id): void
    {
        $act_id = trim($act_id);
        if ($act_id === '') {
            return;
        }

        $storage = ActStorage::make();
        $act = Act::find($act_id);
        if (! $act) {
            $storage->removeCurrentFile($act_id);

            return;
        }

        $blocks = BlockApp::make()->listForSnapshot($act_id);
        $access = AccessApp::make()->dumpForSnapshot($act_id);
        $owner_tags = TagsApp::make()->dumpOwnerTagsForSnapshot($act_id);
        $envelope = ActEnvelope::make()->build($act, $blocks, $access, $owner_tags);
        $payload = $envelope;
        $payload['format_version'] = self::RESTORE_FORMAT_VERSION;
        $payload['written_at'] = Carbon::now('UTC')->toIso8601String();
        $payload['envelope_hash'] = ActEnvelope::make()->hash($envelope);
        $payload['log_head_id'] = SnapshotApp::make()->logHeadId($act_id);

        $owner_login = $access['meta']['owner_login'] ?? null;
        if ($owner_login === null && $act->owner_id !== null) {
            $owner = $act->owner;
            if ($owner) {
                $owner_login = (string) ($owner->username ?? '');
            }
        }
        $payload['owner_login'] = $owner_login;

        $storage->writeCurrentFile($act_id, $payload);
    }

    /** @deprecated use syncCurrentFile */
    public function syncRestoreFile(string $act_id): void
    {
        $this->syncCurrentFile($act_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreFromStorage(?string $act_id = null, bool $dry_run = false): array
    {
        $storage = ActStorage::make();
        $filter = $act_id !== null ? trim($act_id) : '';

        if ($filter !== '') {
            $act_ids = [$filter];
        } else {
            $act_ids = $storage->listActDirectoriesWithRestore();
        }

        $restored = [];
        $failed = [];

        foreach ($act_ids as $id) {
            try {
                $restored[] = $this->restoreOneFromStorage($id, $dry_run);
            } catch (\Throwable $exception) {
                $failed[] = [
                    'id' => $id,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'dry_run' => $dry_run,
            'restored' => $restored,
            'failed' => $failed,
            'counts' => [
                'restored' => count($restored),
                'failed' => count($failed),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreOneFromStorage(string $act_id, bool $dry_run): array
    {
        $act_id = trim($act_id);
        $storage = ActStorage::make();
        $restore = $storage->readCurrentFile($act_id);

        if ($restore === null) {
            throw new \RuntimeException("current.json не найден для акта id={$act_id}");
        }

        $format_version = (int) ($restore['format_version'] ?? 0);
        if (! in_array($format_version, [self::RESTORE_FORMAT_VERSION, self::LEGACY_RESTORE_FORMAT_VERSION], true)) {
            throw new \RuntimeException(
                "Неподдерживаемый format_version={$format_version} для акта id={$act_id}"
            );
        }

        $file_id = trim((string) ($restore['id'] ?? ''));
        if ($file_id === '') {
            throw new \RuntimeException("В current.json отсутствует id для каталога {$act_id}");
        }

        if (strcasecmp($file_id, $act_id) !== 0) {
            throw new \RuntimeException(
                "id в current.json ({$file_id}) не совпадает с каталогом ({$act_id})"
            );
        }

        $owner_id = array_key_exists('owner_id', $restore) && $restore['owner_id'] !== null
            ? (int) $restore['owner_id']
            : null;

        if ($owner_id !== null && ! User::find($owner_id)) {
            $owner_login = (string) ($restore['owner_login'] ?? '');
            $hint = $owner_login !== '' ? " (login: {$owner_login})" : '';
            throw new \RuntimeException(
                "Пользователь owner_id={$owner_id} не найден{$hint}; акт id={$act_id} не восстановлен"
            );
        }

        $name = trim((string) ($restore['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException("В current.json отсутствует name для акта id={$act_id}");
        }

        if ($dry_run) {
            return [
                'id' => $act_id,
                'dry_run' => true,
                'name' => $name,
                'owner_id' => $owner_id,
                'blocks_count' => is_array($restore['blocks'] ?? null) ? count($restore['blocks']) : 0,
            ];
        }

        $act = Act::find($act_id);
        $created = $act === null;

        if ($created) {
            $act = new Act();
            $act->id = $act_id;
        }

        $act->name = $name;
        $act->description = array_key_exists('description', $restore)
            ? $this->stringOrNull($restore['description'])
            : null;
        $act->owner_id = $owner_id;
        $act->activate_at = array_key_exists('activate_at', $restore)
            ? $this->parseDateOrNull($restore['activate_at'])
            : null;
        $act->stop_at = array_key_exists('stop_at', $restore)
            ? $this->parseDateOrNull($restore['stop_at'])
            : null;

        if (array_key_exists('created_at', $restore) && $restore['created_at'] !== null) {
            $act->created_at = $this->parseDateOrNull($restore['created_at']);
        }
        if (array_key_exists('updated_at', $restore) && $restore['updated_at'] !== null) {
            $act->updated_at = $this->parseDateOrNull($restore['updated_at']);
        }

        $act->save();

        if (array_key_exists('blocks', $restore) && is_array($restore['blocks'])) {
            BlockApp::make()->restoreBlocksFromSnapshot($act_id, $restore['blocks'], $owner_id);
        }

        if (array_key_exists('access', $restore) && is_array($restore['access'])) {
            AccessApp::make()->restoreFromSnapshot($act_id, $restore['access']);
        } else {
            AccessApp::make()->migrateLegacyVisibility($act_id);
        }

        $this->syncCurrentFile($act_id);

        return [
            'id' => $act_id,
            'created' => $created,
            'name' => $name,
            'owner_id' => $owner_id,
            'blocks_count' => is_array($restore['blocks'] ?? null) ? count($restore['blocks']) : 0,
        ];
    }

    /**
     * @return list<array{snapshot_id: int, snapshot_key: string, display_index: int, timestamp: string, created_at: string, kind: string, scope: string, size_bytes: int}>
     */
    public function listStatesForOwner(int $owner_id, string $act_id): array
    {
        $this->requireOwnedAct($owner_id, $act_id);

        return SnapshotApp::make()->list($act_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreForOwner(int $owner_id, string $act_id, string $snapshot_key): array
    {
        $this->requireOwnedAct($owner_id, $act_id);
        $snapshot_id = (int) $snapshot_key;
        if ($snapshot_id <= 0) {
            throw new \RuntimeException('Недопустимый snapshot_key');
        }

        return SnapshotApp::make()->restore($act_id, $snapshot_id, $owner_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeStatesForOwner(int $owner_id, string $act_id, int $from_index, int $to_index): array
    {
        $this->requireOwnedAct($owner_id, $act_id);

        return SnapshotApp::make()->mergeRange($act_id, $from_index, $to_index, $owner_id);
    }

    public function requireOwnedActPublic(int $owner_id, string $act_id): Act
    {
        return $this->requireOwnedAct($owner_id, $act_id);
    }

    public function parseDateOrNullPublic(mixed $value): ?Carbon
    {
        return $this->parseDateOrNull($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function actCard(Act $act): array
    {
        $owner_login = null;
        if ($act->owner) {
            $owner_login = (string) ($act->owner->username ?? '');
        }

        return [
            'id' => (string) $act->id,
            'name' => (string) ($act->name ?? ''),
            'access' => 'owner',
            'is_mine' => true,
            'created_at' => $act->created_at?->toIso8601String(),
            'owner_login' => $owner_login !== '' ? $owner_login : null,
            'owner_display_name' => $owner_login !== '' ? $this->displayNameForLogin($owner_login) : null,
        ];
    }

    private function requireOwnedAct(int $owner_id, string $act_id): Act
    {
        $act = Act::find(trim($act_id));
        if (! $act) {
            throw new \RuntimeException("Акт id={$act_id} не найден");
        }

        if ($act->owner_id === null || (int) $act->owner_id !== $owner_id) {
            throw new \RuntimeException('Нет прав на изменение акта');
        }

        return $act;
    }

    private function ownerDisplayName(User $owner): string
    {
        if ($owner->name !== null && trim((string) $owner->name) !== '') {
            return trim((string) $owner->name);
        }

        return (string) ($owner->username ?? 'User');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function listForAi(array $filters = []): array
    {
        $query = Act::query()->orderByDesc('updated_at')->orderBy('name');

        if (array_key_exists('owner_id', $filters) && $filters['owner_id'] !== null && $filters['owner_id'] !== '') {
            $query->where('owner_id', (int) $filters['owner_id']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('id', 'like', $like);
            });
        }

        $items = [];
        foreach ($query->get() as $act) {
            $items[] = $this->actListItem($act);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function showFull(string $id): ?array
    {
        $act = Act::find(trim($id));
        if (! $act) {
            return null;
        }

        $storage = ActStorage::make();
        $actDirectory = $storage->actDirectory((string) $act->id);
        $sqlitePath = $actDirectory.'/act.sqlite';

        return [
            'act' => $this->actRowForExport($act),
            'owner' => $this->ownerSummary($act),
            'storage' => [
                'directory' => $actDirectory,
                'exists' => is_dir($actDirectory),
                'sqlite_path' => $sqlitePath,
                'sqlite_exists' => is_file($sqlitePath),
                'snapshots' => SnapshotApp::make()->list((string) $act->id),
                'snapshots_count' => SnapshotApp::make()->count((string) $act->id),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromAiData(array $data): Act
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Поле name обязательно');
        }

        $act = new Act();
        $explicit_id = trim((string) ($data['id'] ?? ''));
        if ($explicit_id !== '') {
            $act->id = $explicit_id;
        }

        $this->applyAiPayload($act, $data, true);
        $act->save();

        return $act;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFromAiData(string $id, array $data): ?Act
    {
        $act = Act::find(trim($id));
        if (! $act) {
            return null;
        }

        $this->applyAiPayload($act, $data, false);
        $act->save();

        SnapshotApp::make()->finalizeMutation((string) $act->id, 'act', [
            'patch' => $data,
        ]);

        return $act;
    }

    /**
     * @return array{deleted: bool, id: string}
     */
    public function deleteAct(string $id): array
    {
        $id = trim($id);
        $act = Act::find($id);
        if (! $act) {
            throw new \RuntimeException("Акт id={$id} не найден");
        }

        $act->delete();

        return ['deleted' => true, 'id' => $id];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listStates(string $id): array
    {
        $act = Act::find(trim($id));
        if (! $act) {
            throw new \RuntimeException("Акт id={$id} не найден");
        }

        return SnapshotApp::make()->list((string) $act->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function restoreFromSnapshot(array $data): array
    {
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            throw new \InvalidArgumentException('Поле id обязательно');
        }

        $snapshot_id = null;
        if (array_key_exists('snapshot_id', $data) && $data['snapshot_id'] !== null && $data['snapshot_id'] !== '') {
            $snapshot_id = (int) $data['snapshot_id'];
        } elseif (array_key_exists('snapshot_key', $data) && trim((string) $data['snapshot_key']) !== '') {
            $snapshot_id = (int) $data['snapshot_key'];
        }

        if ($snapshot_id === null || $snapshot_id <= 0) {
            $items = SnapshotApp::make()->list($id);
            $snapshot_id = isset($items[0]['snapshot_id']) ? (int) $items[0]['snapshot_id'] : 0;
        }

        if ($snapshot_id <= 0) {
            throw new \RuntimeException('Снимок не найден');
        }

        $owner_id = array_key_exists('owner_id', $data) && $data['owner_id'] !== null
            ? (int) $data['owner_id']
            : null;

        return SnapshotApp::make()->restore($id, $snapshot_id, $owner_id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validateAiPayload(string $type, array $data): array
    {
        return match ($type) {
            'create' => $this->validateCreatePayload($data),
            'update' => $this->validateUpdatePayload($data),
            'delete' => $this->validateDeletePayload($data),
            'restore' => $this->validateRestorePayload($data),
            default => throw new \InvalidArgumentException("Неизвестный type \"{$type}\""),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyAiPayload(Act $act, array $data, bool $is_create): void
    {
        if (array_key_exists('name', $data)) {
            $act->name = trim((string) $data['name']);
        }

        if (array_key_exists('description', $data)) {
            $act->description = $this->stringOrNull($data['description']);
        }

        if (array_key_exists('owner_id', $data)) {
            $owner_id = $data['owner_id'];
            if ($owner_id === null || $owner_id === '') {
                $act->owner_id = null;
            } else {
                $owner_id = (int) $owner_id;
                if (! User::find($owner_id)) {
                    throw new \RuntimeException("Пользователь owner_id={$owner_id} не найден");
                }
                $act->owner_id = $owner_id;
            }
        }

        if (array_key_exists('activate_at', $data)) {
            $act->activate_at = $this->parseDateOrNull($data['activate_at']);
        }

        if (array_key_exists('stop_at', $data)) {
            $act->stop_at = $this->parseDateOrNull($data['stop_at']);
        }

        if ($is_create && ! array_key_exists('name', $data)) {
            throw new \InvalidArgumentException('Поле name обязательно при создании');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function actListItem(Act $act): array
    {
        $snapshots_count = SnapshotApp::make()->count((string) $act->id);

        return [
            'id' => (string) $act->id,
            'name' => (string) ($act->name ?? ''),
            'description' => $act->description !== null ? (string) $act->description : null,
            'owner_id' => $act->owner_id !== null ? (int) $act->owner_id : null,
            'activate_at' => $act->activate_at?->toIso8601String(),
            'stop_at' => $act->stop_at?->toIso8601String(),
            'updated_at' => $act->updated_at?->toIso8601String(),
            'snapshots_count' => $snapshots_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actRowForExport(Act $act): array
    {
        return ActStorage::make()->snapshotFromAct($act);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ownerSummary(Act $act): ?array
    {
        if ($act->owner_id === null) {
            return null;
        }

        $owner = $act->owner;
        if (! $owner) {
            return [
                'id' => (int) $act->owner_id,
                'missing' => true,
            ];
        }

        return [
            'id' => (int) $owner->id,
            'full_name' => (string) ($owner->full_name ?? ''),
            'email' => (string) ($owner->email ?? ''),
        ];
    }

    private function parseDateOrNull(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string_value = trim((string) $value);

        return $string_value === '' ? null : $string_value;
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
    private function validateDeletePayload(array $data): array
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
    private function validateRestorePayload(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['id'] ?? '')) === '') {
            $errors[] = 'id обязателен';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
