<?php namespace Zen\Act\Classes\System;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RainLab\User\Models\User;
use Zen\Act\Classes\Support\ActMutationLog;
use Zen\Act\Classes\Support\ActSqlite;
use Zen\Act\Models\Act;

class AccessApp
{
    public const SCHEMA_VERSION = 1;

    public const PUBLIC_PRINCIPAL = '@public';

    public const AUTH_PRINCIPAL = '@authenticated';

    /** @var array<string, list<string>> */
    private const ROLE_CAPABILITIES = [
        'viewer' => ['read'],
        'editor' => ['read', 'write'],
        'signer' => ['read', 'sign'],
        'steward' => ['read', 'write', 'grant'],
        'owner' => ['read', 'write', 'sign', 'grant', 'admin'],
    ];

    private const VALID_ROLES = ['viewer', 'editor', 'signer', 'steward'];

    /** @var array<string, int> */
    private const ACCESS_PRIORITY = [
        'owner' => 1,
        'shared_act' => 2,
        'shared_block' => 3,
    ];

    public static function make(): self
    {
        return new self();
    }

    public function ensureReady(string $act_id): void
    {
        ActSqlite::make($act_id);
        if ($this->getMetaValue($act_id, 'schema_version') !== null) {
            return;
        }

        $this->migrateLegacyVisibility($act_id);
    }

    /**
     * @return array{owner_login: string|null, schema_version: int}
     */
    public function getMeta(string $act_id): array
    {
        $this->ensureReady($act_id);

        return [
            'owner_login' => $this->getMetaValue($act_id, 'owner_login'),
            'schema_version' => (int) ($this->getMetaValue($act_id, 'schema_version') ?? self::SCHEMA_VERSION),
        ];
    }

    public function bootstrap(string $act_id, string $owner_login): void
    {
        $owner_login = $this->normalizeLogin($owner_login);
        if ($owner_login === '') {
            throw new \InvalidArgumentException('owner_login обязателен');
        }

        ActSqlite::make($act_id);
        $this->setMetaValue($act_id, 'owner_login', $owner_login);
        $this->setMetaValue($act_id, 'schema_version', (string) self::SCHEMA_VERSION);

        if ($this->listGrantsRaw($act_id, 'act', $act_id) === []) {
            $this->replaceGrants($act_id, 'act', $act_id, [
                ['login' => self::PUBLIC_PRINCIPAL, 'role' => 'viewer'],
            ], $owner_login, false);
        } else {
            $this->syncOwnerCache($act_id);
            $this->rebuildViewerIndex($act_id);
        }
    }

    /**
     * @return list<array{login: string, role: string}>
     */
    public function listGrants(string $act_id, string $resource_type, string $resource_id): array
    {
        $this->ensureReady($act_id);

        return $this->listGrantsRaw($act_id, $resource_type, $resource_id);
    }

    /**
     * @return array{
     *     grants: list<array{login: string, role: string, display_name?: string}>,
     *     effective_grants: list<array{login: string, role: string, display_name?: string}>,
     *     inherited_from: 'act'|'block'|null
     * }
     */
    public function describeGrants(string $act_id, string $resource_type, string $resource_id): array
    {
        $this->ensureReady($act_id);
        $resource_type = $this->normalizeResourceType($resource_type);
        $resource_id = trim($resource_id);

        if ($resource_type === 'act') {
            $resource_id = trim($act_id);
        }

        $grants = $this->listGrantsRaw($act_id, $resource_type, $resource_id);
        if ($grants !== [] || $resource_type === 'act') {
            return [
                'grants' => $this->enrichGrants($grants),
                'effective_grants' => $this->enrichGrants($grants),
                'inherited_from' => null,
            ];
        }

        if ($resource_type === 'block') {
            $parent_grants = $this->listGrantsRaw($act_id, 'act', $act_id);

            return [
                'grants' => [],
                'effective_grants' => $this->enrichGrants($parent_grants),
                'inherited_from' => 'act',
            ];
        }

        $parts = explode(':', $resource_id, 2);
        $block_id = trim($parts[0] ?? '');
        if ($resource_type === 'item' && $block_id !== '') {
            $parent = $this->describeGrants($act_id, 'block', $block_id);

            return [
                'grants' => [],
                'effective_grants' => $parent['effective_grants'],
                'inherited_from' => 'block',
            ];
        }

        return [
            'grants' => [],
            'effective_grants' => [],
            'inherited_from' => null,
        ];
    }

    /**
     * @return list<array{login: string, role: string}>
     */
    private function listGrantsRaw(string $act_id, string $resource_type, string $resource_id): array
    {
        $resource_type = $this->normalizeResourceType($resource_type);
        $resource_id = trim($resource_id);

        $rows = ActSqlite::make($act_id)
            ->query('access')
            ->where('resource_type', $resource_type)
            ->where('resource_id', $resource_id)
            ->orderBy('login')
            ->get();

        $grants = [];
        foreach ($rows as $row) {
            $grants[] = [
                'login' => (string) $row->login,
                'role' => (string) $row->role,
            ];
        }

        return $grants;
    }

    /**
     * @param  list<array{login: string, role: string}>  $grants
     */
    public function setGrants(
        string $act_id,
        string $resource_type,
        string $resource_id,
        array $grants,
        string $by_login,
    ): void {
        $by_login = $this->normalizeLogin($by_login);
        if (! $this->can($by_login, $act_id, $resource_type, $resource_id, 'grant', false)) {
            throw new \RuntimeException('Нет прав на изменение доступа');
        }

        SnapshotApp::make()->assertIntegrity($act_id);
        $this->replaceGrants($act_id, $resource_type, $resource_id, $grants, $by_login, true);
        $this->syncOwnerCache($act_id);
        SnapshotApp::make()->finalizeMutation($act_id, 'access', [
            'action' => 'set_grants',
            'resource_type' => $resource_type,
            'resource_id' => $resource_id,
        ]);
    }

    /**
     * @return list<string>
     */
    public function effectiveCapabilities(
        ?string $viewer_login,
        string $act_id,
        string $resource_type,
        string $resource_id,
        bool $front_view = false,
    ): array {
        if ($this->isOwnerBypass($viewer_login, $act_id, $front_view)) {
            return self::ROLE_CAPABILITIES['owner'];
        }

        $chain = $this->resourceChain($act_id, $resource_type, $resource_id);
        $capabilities = [];

        foreach ($chain as $level) {
            $grants = $this->listGrantsRaw($act_id, $level['type'], $level['id']);
            if ($grants === []) {
                continue;
            }

            if (! $this->matchesGrants($viewer_login, $grants)) {
                return [];
            }

            $capabilities = $this->capabilitiesFromGrants($viewer_login, $grants);
        }

        return array_values(array_unique($capabilities));
    }

    public function can(
        ?string $viewer_login,
        string $act_id,
        string $resource_type,
        string $resource_id,
        string $capability,
        bool $front_view = false,
    ): bool {
        return in_array($capability, $this->effectiveCapabilities(
            $viewer_login,
            $act_id,
            $resource_type,
            $resource_id,
            $front_view
        ), true);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public function filterBlocksForViewer(
        string $act_id,
        ?string $viewer_login,
        array $blocks,
        bool $front_view = false,
    ): array {
        return array_values(array_filter(
            $blocks,
            fn (array $block): bool => $this->can(
                $viewer_login,
                $act_id,
                'block',
                (string) ($block['id'] ?? ''),
                'read',
                $front_view
            )
        ));
    }

    public function hasReadableGrant(?string $viewer_login, string $act_id): bool
    {
        if ($viewer_login === null || $viewer_login === '') {
            return $this->can(null, $act_id, 'act', $act_id, 'read', false);
        }

        if ($this->can($viewer_login, $act_id, 'act', $act_id, 'read', false)) {
            return true;
        }

        foreach ($this->listRows($act_id) as $block) {
            if ($this->can($viewer_login, $act_id, 'block', (string) $block['id'], 'read', false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{login: string, display_name: string}>
     */
    public function listPreviewAudiences(string $act_id): array
    {
        $this->ensureReady($act_id);

        $owner_login = mb_strtolower($this->getMetaValue($act_id, 'owner_login') ?? '');
        $user_logins = [];

        $sqlite = ActSqlite::make($act_id);
        foreach ($sqlite->query('access')->get() as $row) {
            if (! $this->roleHasRead((string) $row->role)) {
                continue;
            }

            $login = $this->normalizePrincipal((string) $row->login);
            if ($login === '' || $this->isPrincipalLogin($login)) {
                continue;
            }

            if ($owner_login !== '' && mb_strtolower($login) === $owner_login) {
                continue;
            }

            $user_logins[mb_strtolower($login)] = $login;
        }

        $audiences = [
            [
                'login' => self::PUBLIC_PRINCIPAL,
                'display_name' => 'Любой посетитель (без входа)',
            ],
            [
                'login' => self::AUTH_PRINCIPAL,
                'display_name' => 'Любой авторизованный',
            ],
        ];

        $sorted = array_values($user_logins);
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($sorted as $login) {
            $audiences[] = [
                'login' => $login,
                'display_name' => $this->displayNameForLogin($login),
            ];
        }

        return $audiences;
    }

    public function isAllowedPreviewAudience(string $act_id, string $as_viewer): bool
    {
        $as_viewer = trim($as_viewer);
        if ($as_viewer === '') {
            return false;
        }

        foreach ($this->listPreviewAudiences($act_id) as $audience) {
            if ((string) $audience['login'] === $as_viewer) {
                return true;
            }
        }

        return false;
    }

    public function resolveViewerLogin(
        ?string $session_login,
        string $act_id,
        bool $front_view,
        bool $is_act_owner,
        ?string $as_viewer,
    ): ?string {
        $session_login = $session_login !== null ? $this->normalizeLogin($session_login) : '';
        $as_viewer = $as_viewer !== null ? trim($as_viewer) : '';

        if (! $front_view || ! $is_act_owner || $as_viewer === '') {
            return $session_login !== '' ? $session_login : null;
        }

        if (! $this->isAllowedPreviewAudience($act_id, $as_viewer)) {
            throw new \InvalidArgumentException('Недопустимый параметр as_viewer');
        }

        return $this->previewAudienceToViewerLogin($as_viewer, $session_login);
    }

    private function previewAudienceToViewerLogin(string $as_viewer, string $session_login): ?string
    {
        if ($as_viewer === self::PUBLIC_PRINCIPAL) {
            return null;
        }

        if ($as_viewer === self::AUTH_PRINCIPAL) {
            return $session_login !== '' ? $session_login : null;
        }

        return $as_viewer;
    }

    /**
     * @return 'shared_act'|'shared_block'|null
     */
    public function sharedAccessKind(?string $viewer_login, string $act_id, string $owner_login): ?string
    {
        if ($viewer_login === null || $viewer_login === '' || $viewer_login === $owner_login) {
            return null;
        }

        if ($this->can($viewer_login, $act_id, 'act', $act_id, 'read', false)) {
            return 'shared_act';
        }

        foreach ($this->listRows($act_id) as $block) {
            if ($this->can($viewer_login, $act_id, 'block', (string) $block['id'], 'read', false)) {
                return 'shared_block';
            }
        }

        return null;
    }

    /**
     * @return list<array{login: string, role: string, display_name: string}>
     */
    public function listSignerOptions(string $act_id, string $block_id): array
    {
        $this->ensureReady($act_id);
        $grants = $this->describeGrants($act_id, 'block', $block_id)['effective_grants'];
        $options = [];
        $seen = [];

        foreach ($grants as $grant) {
            $login = (string) ($grant['login'] ?? '');
            $role = (string) ($grant['role'] ?? 'viewer');
            if ($login === '' || $this->isPrincipalLogin($login)) {
                continue;
            }
            if (! $this->roleHasRead($role)) {
                continue;
            }

            $key = mb_strtolower($login);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $options[] = [
                'login' => $login,
                'role' => $role,
                'display_name' => (string) ($grant['display_name'] ?? $this->displayNameForLogin($login)),
            ];
        }

        usort($options, fn (array $a, array $b): int => strnatcasecmp(
            (string) $a['display_name'],
            (string) $b['display_name']
        ));

        return $options;
    }

    public function migrateLegacyVisibility(string $act_id): int
    {
        ActSqlite::make($act_id);
        $migrated = 0;
        $act = Act::find(trim($act_id));
        $owner_login = $this->getMetaValue($act_id, 'owner_login');

        if ($owner_login === null && $act?->owner) {
            $owner_login = (string) ($act->owner->username ?? '');
            if ($owner_login !== '') {
                $this->setMetaValue($act_id, 'owner_login', $owner_login);
            }
        }

        if ($this->listGrantsRaw($act_id, 'act', $act_id) === []) {
            $this->replaceGrants($act_id, 'act', $act_id, [
                ['login' => self::PUBLIC_PRINCIPAL, 'role' => 'viewer'],
            ], $owner_login ?? 'system', false);
        }

        $sqlite = ActSqlite::make($act_id);
        foreach ($sqlite->query('blocks')->orderBy('sort_order')->get() as $row) {
            $data = json_decode((string) $row->data, true);
            if (! is_array($data) || ! array_key_exists('visibility', $data)) {
                continue;
            }

            $visibility = is_array($data['visibility']) ? $data['visibility'] : [];
            $block_id = (string) $row->id;
            $grants = [];

            $published = ! array_key_exists('published', $visibility)
                || filter_var($visibility['published'], FILTER_VALIDATE_BOOLEAN);
            $users = is_array($visibility['users'] ?? null) ? $visibility['users'] : [];
            $audience = (string) ($visibility['audience'] ?? '');

            if ($published && $audience === 'users' && $users !== []) {
                foreach ($users as $user) {
                    $login = $this->loginFromLegacyUser($user);
                    if ($login !== '') {
                        $grants[] = ['login' => $login, 'role' => 'viewer'];
                    }
                }
            } elseif ($published && $users !== [] && $audience !== 'users') {
                foreach ($users as $user) {
                    $login = $this->loginFromLegacyUser($user);
                    if ($login !== '') {
                        $grants[] = ['login' => $login, 'role' => 'viewer'];
                    }
                }
            }

            if ($grants !== []) {
                $this->replaceGrants($act_id, 'block', $block_id, $grants, $owner_login ?? 'system', false);
                $migrated++;
            }

            if (($data['type'] ?? null) === 'checklist' && is_array($data['items'] ?? null)) {
                foreach ($data['items'] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $signer = is_array($item['signer'] ?? null) ? $item['signer'] : null;
                    $login = $this->loginFromLegacyUser($signer);
                    if ($login === '') {
                        continue;
                    }
                    $item_id = trim((string) ($item['id'] ?? ''));
                    if ($item_id === '') {
                        continue;
                    }
                    $resource_id = $block_id.':'.$item_id;
                    $existing = $this->listGrantsRaw($act_id, 'item', $resource_id);
                    if ($existing === []) {
                        $this->replaceGrants($act_id, 'item', $resource_id, [
                            ['login' => $login, 'role' => 'signer'],
                        ], $owner_login ?? 'system', false);
                    }
                }
            }

            unset($data['visibility']);
            if (($data['type'] ?? null) === 'checklist' && is_array($data['items'] ?? null)) {
                foreach ($data['items'] as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    if (is_array($item['signer'] ?? null)) {
                        $signer_login = $this->loginFromLegacyUser($item['signer']);
                        $data['items'][$index]['signer'] = $signer_login !== ''
                            ? ['login' => $signer_login, 'display_name' => $this->displayNameForLogin($signer_login)]
                            : null;
                    }
                    if (is_array($item['signature'] ?? null)) {
                        $sig_login = (string) ($item['signature']['login'] ?? '');
                        if ($sig_login === '' && isset($item['signature']['user_id'])) {
                            $user = User::find((int) $item['signature']['user_id']);
                            $sig_login = $user ? (string) ($user->username ?? '') : '';
                        }
                        $data['items'][$index]['signature'] = [
                            'login' => $sig_login,
                            'display_name' => (string) ($item['signature']['display_name'] ?? $this->displayNameForLogin($sig_login)),
                            'signed_at' => (string) ($item['signature']['signed_at'] ?? ''),
                        ];
                    }
                }
            }

            $block = [
                'id' => $block_id,
                'name' => (string) $row->name,
                'data' => $data,
                'sort_order' => $row->sort_order !== null ? (int) $row->sort_order : null,
                'created_at' => (string) $row->created_at,
                'updated_at' => Carbon::now('UTC')->toDateTimeString(),
            ];
            $block['hash'] = hash('sha256', $this->canonicalJson([
                'name' => $block['name'],
                'data' => $block['data'],
                'created_at' => $block['created_at'],
                'updated_at' => $block['updated_at'],
            ]));

            $sqlite->query('blocks')->where('id', $block_id)->update([
                'data' => $this->canonicalJson($block['data']),
                'updated_at' => $block['updated_at'],
                'hash' => $block['hash'],
            ]);
            $migrated++;
        }

        $this->setMetaValue($act_id, 'schema_version', (string) self::SCHEMA_VERSION);
        $this->syncOwnerCache($act_id);
        $this->rebuildViewerIndex($act_id);

        return $migrated;
    }

    /**
     * @return array{meta: array<string, string>, grants: list<array{resource_type: string, resource_id: string, login: string, role: string}>}
     */
    public function dumpForSnapshot(string $act_id): array
    {
        $this->ensureReady($act_id);
        $sqlite = ActSqlite::make($act_id);

        $meta = [];
        foreach ($sqlite->query('access_meta')->get() as $row) {
            $meta[(string) $row->key] = (string) $row->value;
        }

        $grants = [];
        foreach ($sqlite->query('access')->orderBy('resource_type')->orderBy('resource_id')->orderBy('login')->get() as $row) {
            $grants[] = [
                'resource_type' => (string) $row->resource_type,
                'resource_id' => (string) $row->resource_id,
                'login' => (string) $row->login,
                'role' => (string) $row->role,
            ];
        }

        return ['meta' => $meta, 'grants' => $grants];
    }

    /**
     * @param  array{meta?: array<string, string>, grants?: list<array{resource_type?: string, resource_id?: string, login?: string, role?: string}>}  $access
     */
    public function restoreFromSnapshot(string $act_id, array $access): void
    {
        ActSqlite::make($act_id);
        $sqlite = ActSqlite::make($act_id);

        $sqlite->connection()->transaction(function () use ($sqlite, $access): void {
            $sqlite->query('access')->delete();
            $sqlite->query('access_meta')->delete();

            foreach ($access['meta'] ?? [] as $key => $value) {
                $sqlite->query('access_meta')->insert([
                    'key' => (string) $key,
                    'value' => (string) $value,
                ]);
            }

            foreach ($access['grants'] ?? [] as $grant) {
                if (! is_array($grant)) {
                    continue;
                }
                $sqlite->query('access')->insert([
                    'resource_type' => $this->normalizeResourceType((string) ($grant['resource_type'] ?? '')),
                    'resource_id' => trim((string) ($grant['resource_id'] ?? '')),
                    'login' => $this->normalizePrincipal((string) ($grant['login'] ?? '')),
                    'role' => $this->normalizeRole((string) ($grant['role'] ?? 'viewer')),
                ]);
            }
        });

        $this->syncOwnerCache($act_id);
        $this->rebuildViewerIndex($act_id);
    }

    public function removeViewerIndex(string $act_id): void
    {
        DB::table('zen_act_viewer_index')->where('act_id', trim($act_id))->delete();
    }

    public function rebuildViewerIndex(string $act_id): int
    {
        $act_id = trim($act_id);
        $act = Act::find($act_id);
        if (! $act) {
            $this->removeViewerIndex($act_id);

            return 0;
        }

        $this->ensureReady($act_id);

        $owner_login = $this->getMetaValue($act_id, 'owner_login');
        if ($owner_login === null || $owner_login === '') {
            if ($act->owner) {
                $owner_login = (string) ($act->owner->username ?? '');
            }
        }

        $owner_login = $owner_login !== null ? $this->normalizeLogin($owner_login) : '';
        $created_at = $act->created_at
            ? $act->created_at->format('Y-m-d H:i:s')
            : null;

        /** @var array<string, string> $entries viewer_key => access */
        $entries = [];

        if ($owner_login !== '') {
            $this->assignViewerAccess($entries, $owner_login, 'owner');
        }

        foreach ($this->listGrantsRaw($act_id, 'act', $act_id) as $grant) {
            if (! $this->roleHasRead((string) $grant['role'])) {
                continue;
            }
            $this->assignViewerAccess($entries, (string) $grant['login'], 'shared_act');
        }

        foreach ($this->listRows($act_id) as $row) {
            $block_id = (string) $row->id;
            foreach ($this->listGrantsRaw($act_id, 'block', $block_id) as $grant) {
                if (! $this->roleHasRead((string) $grant['role'])) {
                    continue;
                }
                $this->assignViewerAccess($entries, (string) $grant['login'], 'shared_block');
            }
        }

        $sqlite = ActSqlite::make($act_id);
        foreach ($sqlite->query('access')->where('resource_type', 'item')->get() as $row) {
            if (! $this->roleHasRead((string) $row->role)) {
                continue;
            }
            $this->assignViewerAccess($entries, (string) $row->login, 'shared_block');
        }

        DB::table('zen_act_viewer_index')->where('act_id', $act_id)->delete();

        if ($entries === []) {
            return 0;
        }

        $now = Carbon::now('UTC')->toDateTimeString();
        $rows = [];
        foreach ($entries as $viewer_key => $access) {
            $rows[] = [
                'act_id' => $act_id,
                'viewer_key' => $viewer_key,
                'access' => $access,
                'owner_login' => $owner_login !== '' ? $owner_login : null,
                'created_at' => $created_at ?? $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('zen_act_viewer_index')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function assignViewerAccess(array &$entries, string $viewer_key, string $access): void
    {
        $viewer_key = $this->normalizePrincipal($viewer_key);
        if ($viewer_key === '' || $this->isPrincipalLogin($viewer_key)) {
            return;
        }

        $new_rank = self::ACCESS_PRIORITY[$access] ?? 99;
        $current = $entries[$viewer_key] ?? null;
        $current_rank = $current !== null ? (self::ACCESS_PRIORITY[$current] ?? 99) : 99;

        if ($new_rank < $current_rank) {
            $entries[$viewer_key] = $access;
        }
    }

    private function roleHasRead(string $role): bool
    {
        $role = $this->normalizeRole($role);

        return in_array('read', self::ROLE_CAPABILITIES[$role] ?? [], true);
    }

    public function syncOwnerCache(string $act_id): void
    {
        $owner_login = $this->getMetaValue($act_id, 'owner_login');
        if ($owner_login === null || $owner_login === '') {
            return;
        }

        $user = AuthApp::make()->findByLogin($owner_login);
        $act = Act::find(trim($act_id));
        if (! $act) {
            return;
        }

        $new_owner_id = $user ? (int) $user->id : null;
        if ((int) ($act->owner_id ?? 0) !== (int) ($new_owner_id ?? 0)) {
            $act->owner_id = $new_owner_id;
            $act->save();
        }
    }

    /**
     * @param  list<array{login: string, role: string}>  $grants
     */
    private function replaceGrants(
        string $act_id,
        string $resource_type,
        string $resource_id,
        array $grants,
        string $by_login,
        bool $log_change,
    ): void {
        $resource_type = $this->normalizeResourceType($resource_type);
        $resource_id = trim($resource_id);
        if ($resource_id === '') {
            throw new \InvalidArgumentException('resource_id обязателен');
        }

        $normalized = [];
        foreach ($grants as $grant) {
            if (! is_array($grant)) {
                continue;
            }
            $login = $this->normalizePrincipal((string) ($grant['login'] ?? ''));
            $role = $this->normalizeRole((string) ($grant['role'] ?? 'viewer'));
            if ($login === '') {
                continue;
            }
            if (! $this->isPrincipalLogin($login)) {
                $user = AuthApp::make()->findByLogin($login);
                if (! $user) {
                    throw new \InvalidArgumentException("Пользователь login={$login} не найден");
                }
            }
            $normalized[$login] = ['login' => $login, 'role' => $role];
        }

        $sqlite = ActSqlite::make($act_id);
        $sqlite->connection()->transaction(function () use ($sqlite, $resource_type, $resource_id, $normalized, $act_id, $by_login, $log_change): void {
            $sqlite->query('access')
                ->where('resource_type', $resource_type)
                ->where('resource_id', $resource_id)
                ->delete();

            foreach ($normalized as $grant) {
                $sqlite->query('access')->insert([
                    'resource_type' => $resource_type,
                    'resource_id' => $resource_id,
                    'login' => $grant['login'],
                    'role' => $grant['role'],
                ]);
            }

            if ($log_change) {
                $this->appendAccessLog($sqlite, $resource_type, $resource_id, array_values($normalized), $by_login);
            }
        });

        $this->rebuildViewerIndex($act_id);
    }

    /**
     * @param  list<array{login: string, role: string}>  $grants
     */
    private function appendAccessLog(
        ActSqlite $sqlite,
        string $resource_type,
        string $resource_id,
        array $grants,
        string $by_login,
    ): void {
        $previous = $sqlite->query('log')->orderByDesc('id')->first();
        $chain = $previous ? (string) $previous->hash : null;
        $created_at = Carbon::now('UTC')->toDateTimeString();
        $data = [
            'resource_type' => $resource_type,
            'resource_id' => $resource_id,
            'grants' => $grants,
            'by' => $by_login,
        ];
        $hash = ActMutationLog::hash([
            'action' => 'access_set',
            'block_id' => null,
            'data' => $data,
            'chain' => $chain,
            'created_at' => $created_at,
        ]);

        $sqlite->query('log')->insert([
            'action' => 'access_set',
            'block_id' => null,
            'data' => $this->canonicalJson($data),
            'hash' => $hash,
            'chain' => $chain,
            'created_at' => $created_at,
        ]);
    }

    /**
     * @return list<array{type: string, id: string}>
     */
    private function resourceChain(string $act_id, string $resource_type, string $resource_id): array
    {
        $resource_type = $this->normalizeResourceType($resource_type);
        $resource_id = trim($resource_id);
        $chain = [['type' => 'act', 'id' => $act_id]];

        if ($resource_type === 'act') {
            return $chain;
        }

        if ($resource_type === 'block') {
            $chain[] = ['type' => 'block', 'id' => $resource_id];

            return $chain;
        }

        if ($resource_type === 'item') {
            $parts = explode(':', $resource_id, 2);
            $block_id = trim($parts[0] ?? '');
            if ($block_id !== '') {
                $chain[] = ['type' => 'block', 'id' => $block_id];
            }
            $chain[] = ['type' => 'item', 'id' => $resource_id];

            return $chain;
        }

        throw new \InvalidArgumentException("Неизвестный resource_type={$resource_type}");
    }

    /**
     * @param  list<array{login: string, role: string}>  $grants
     */
    private function matchesGrants(?string $viewer_login, array $grants): bool
    {
        foreach ($grants as $grant) {
            if ($this->grantMatchesPrincipal($viewer_login, (string) $grant['login'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{login: string, role: string}>  $grants
     * @return list<string>
     */
    private function capabilitiesFromGrants(?string $viewer_login, array $grants): array
    {
        $capabilities = [];
        foreach ($grants as $grant) {
            if (! $this->grantMatchesPrincipal($viewer_login, (string) $grant['login'])) {
                continue;
            }
            $role = $this->normalizeRole((string) $grant['role']);
            $capabilities = array_merge($capabilities, self::ROLE_CAPABILITIES[$role] ?? []);
        }

        return array_values(array_unique($capabilities));
    }

    private function grantMatchesPrincipal(?string $viewer_login, string $principal): bool
    {
        if ($principal === self::PUBLIC_PRINCIPAL) {
            return true;
        }

        if ($principal === self::AUTH_PRINCIPAL) {
            return $viewer_login !== null && $viewer_login !== '';
        }

        return $viewer_login !== null
            && strcasecmp($viewer_login, $principal) === 0;
    }

    private function isOwnerBypass(?string $viewer_login, string $act_id, bool $front_view): bool
    {
        if ($front_view || $viewer_login === null || $viewer_login === '') {
            return false;
        }

        $owner_login = $this->getMetaValue($act_id, 'owner_login');

        return $owner_login !== null && strcasecmp($viewer_login, $owner_login) === 0;
    }

    private function normalizeResourceType(string $type): string
    {
        $type = strtolower(trim($type));
        if (! in_array($type, ['act', 'block', 'item'], true)) {
            throw new \InvalidArgumentException("Недопустимый resource_type={$type}");
        }

        return $type;
    }

    private function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        if (! in_array($role, self::VALID_ROLES, true)) {
            throw new \InvalidArgumentException("Недопустимая роль={$role}");
        }

        return $role;
    }

    private function normalizePrincipal(string $login): string
    {
        $login = trim($login);
        if ($login === self::PUBLIC_PRINCIPAL || $login === self::AUTH_PRINCIPAL) {
            return $login;
        }

        return $this->normalizeLogin($login);
    }

    private function normalizeLogin(string $login): string
    {
        return trim($login);
    }

    private function isPrincipalLogin(string $login): bool
    {
        return $login === self::PUBLIC_PRINCIPAL || $login === self::AUTH_PRINCIPAL;
    }

    private function getMetaValue(string $act_id, string $key): ?string
    {
        $row = ActSqlite::make($act_id)->query('access_meta')->where('key', $key)->first();

        return $row ? (string) $row->value : null;
    }

    private function setMetaValue(string $act_id, string $key, string $value): void
    {
        $sqlite = ActSqlite::make($act_id);
        $exists = $sqlite->query('access_meta')->where('key', $key)->exists();
        if ($exists) {
            $sqlite->query('access_meta')->where('key', $key)->update(['value' => $value]);
        } else {
            $sqlite->query('access_meta')->insert(['key' => $key, 'value' => $value]);
        }
    }

    /**
     * @return list<object>
     */
    private function listRows(string $act_id): array
    {
        if (! is_file(ActSqlite::make($act_id)->path())) {
            return [];
        }

        return ActSqlite::make($act_id)
            ->query('blocks')
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    private function loginFromLegacyUser(mixed $user): string
    {
        if (! is_array($user)) {
            return '';
        }

        $login = trim((string) ($user['login'] ?? ''));
        if ($login !== '') {
            return $login;
        }

        $id = (int) ($user['id'] ?? 0);
        if ($id <= 0) {
            return '';
        }

        $found = User::find($id);

        return $found ? (string) ($found->username ?? '') : '';
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
     * @param  list<array{login: string, role: string}>  $grants
     * @return list<array{login: string, role: string, display_name: string}>
     */
    private function enrichGrants(array $grants): array
    {
        $enriched = [];
        foreach ($grants as $grant) {
            $login = (string) ($grant['login'] ?? '');
            if ($login === '') {
                continue;
            }
            $enriched[] = [
                'login' => $login,
                'role' => (string) ($grant['role'] ?? 'viewer'),
                'display_name' => $this->displayNameForPrincipal($login),
            ];
        }

        return $enriched;
    }

    private function displayNameForPrincipal(string $login): string
    {
        if ($login === self::PUBLIC_PRINCIPAL) {
            return 'Любой посетитель';
        }

        if ($login === self::AUTH_PRINCIPAL) {
            return 'Любой авторизованный';
        }

        return $this->displayNameForLogin($login);
    }

    private function canonicalJson(mixed $data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }
}
