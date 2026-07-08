<?php namespace Zen\Act\Classes\System;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Zen\Act\Classes\Support\ActSqlite;
use Zen\Act\Classes\Support\ActStorage;
use Zen\Act\Models\Act;

class TagsApp
{
    public const MAX_TAGS_PER_ACT = 20;

    public const MIN_TAG_LENGTH = 3;

    public const MAX_TAG_LENGTH = 30;

    public static function make(): self
    {
        return new self();
    }

    /**
     * @return list<string>
     */
    public function listForUser(string $act_id, string $login): array
    {
        $act_id = trim($act_id);
        $login = $this->normalizeLogin($login);
        if ($act_id === '' || $login === '') {
            return [];
        }

        AccessApp::make()->ensureReady($act_id);

        $rows = ActSqlite::make($act_id)
            ->query('user_tags')
            ->where('login', $login)
            ->orderBy('tag')
            ->pluck('tag');

        return array_values($rows->map(fn ($tag): string => (string) $tag)->all());
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    public function setForUser(string $act_id, string $login, array $tags): array
    {
        $act_id = trim($act_id);
        $login = $this->normalizeLogin($login);
        if ($act_id === '' || $login === '') {
            throw new \InvalidArgumentException('Требуется act_id и авторизация');
        }

        $access = AccessApp::make();
        $access->ensureReady($act_id);

        if (! $access->hasReadableGrant($login, $act_id)) {
            throw new \RuntimeException('Нет доступа к акту');
        }

        $normalized = $this->normalizeTagList($tags);
        if (count($normalized) > self::MAX_TAGS_PER_ACT) {
            throw new \InvalidArgumentException('Не более '.self::MAX_TAGS_PER_ACT.' тегов на акт');
        }

        $sqlite = ActSqlite::make($act_id);
        $connection = $sqlite->connection();
        $previous = $this->listForUser($act_id, $login);

        $connection->beginTransaction();
        try {
            $sqlite->query('user_tags')->where('login', $login)->delete();
            $now = Carbon::now('UTC')->toIso8601String();
            foreach ($normalized as $tag) {
                $sqlite->query('user_tags')->insert([
                    'login' => $login,
                    'tag' => $tag,
                    'created_at' => $now,
                ]);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        try {
            $this->syncPgIndex($act_id, $login, $normalized);
            $this->syncCacheAfterChange($login, $previous, $normalized);
        } catch (\Throwable $exception) {
            $connection->beginTransaction();
            try {
                $sqlite->query('user_tags')->where('login', $login)->delete();
                $now = Carbon::now('UTC')->toIso8601String();
                foreach ($previous as $tag) {
                    $sqlite->query('user_tags')->insert([
                        'login' => $login,
                        'tag' => $tag,
                        'created_at' => $now,
                    ]);
                }
                $connection->commit();
            } catch (\Throwable) {
                $connection->rollBack();
            }
            throw $exception;
        }

        $this->maybeSyncOwnerRestore($act_id, $login);

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function catalog(string $login, ?string $query = null): array
    {
        $login = $this->normalizeLogin($login);
        if ($login === '') {
            return [];
        }

        $tags = $this->readCache($login);
        $query = $query !== null ? trim($query) : '';
        if ($query === '') {
            return $tags;
        }

        $needle = mb_strtolower($query);

        return array_values(array_filter(
            $tags,
            fn (string $tag): bool => str_contains(mb_strtolower($tag), $needle)
        ));
    }

    /**
     * @return list<string>
     */
    public function dumpOwnerTagsForSnapshot(string $act_id): array
    {
        $act = Act::find(trim($act_id));
        if (! $act || $act->owner_id === null) {
            return [];
        }

        $owner = $act->owner;
        if (! $owner) {
            return [];
        }

        $owner_login = $this->normalizeLogin((string) ($owner->username ?? ''));
        if ($owner_login === '') {
            return [];
        }

        return $this->listForUser($act_id, $owner_login);
    }

    /**
     * @param  list<string>  $tags
     */
    public function restoreOwnerTags(string $act_id, string $owner_login, array $tags): void
    {
        $act_id = trim($act_id);
        $owner_login = $this->normalizeLogin($owner_login);
        if ($act_id === '' || $owner_login === '') {
            return;
        }

        AccessApp::make()->ensureReady($act_id);

        $normalized = [];
        foreach ($tags as $tag) {
            try {
                $normalized[] = $this->normalizeTag((string) $tag);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        $normalized = array_values(array_unique($normalized));
        if (count($normalized) > self::MAX_TAGS_PER_ACT) {
            $normalized = array_slice($normalized, 0, self::MAX_TAGS_PER_ACT);
        }

        $sqlite = ActSqlite::make($act_id);
        $previous = $this->listForUser($act_id, $owner_login);

        $sqlite->query('user_tags')->where('login', $owner_login)->delete();
        $now = Carbon::now('UTC')->toIso8601String();
        foreach ($normalized as $tag) {
            $sqlite->query('user_tags')->insert([
                'login' => $owner_login,
                'tag' => $tag,
                'created_at' => $now,
            ]);
        }

        $this->syncPgIndex($act_id, $owner_login, $normalized);
        $this->syncCacheAfterChange($owner_login, $previous, $normalized);
    }

    /**
     * @param  list<string>  $tags
     * @param  list<string>  $ops
     * @return list<string>|null null = фильтр не задан
     */
    public function resolveActIdsFromFilter(string $login, array $tags, array $ops): ?array
    {
        $login = $this->normalizeLogin($login);
        if ($login === '' || $tags === []) {
            return null;
        }

        $tags = $this->normalizeTagList($tags);
        if ($tags === []) {
            return [];
        }

        $expected_ops = count($tags) - 1;
        if (count($ops) !== $expected_ops) {
            throw new \InvalidArgumentException('Некорректное количество операторов tag_ops');
        }

        foreach ($ops as $op) {
            if (! in_array($op, ['or', 'and'], true)) {
                throw new \InvalidArgumentException('tag_ops: допустимы только or и and');
            }
        }

        $result = null;
        foreach ($tags as $index => $tag) {
            $act_ids = DB::table('zen_act_user_tags')
                ->where('viewer_login', $login)
                ->where('tag', $tag)
                ->pluck('act_id')
                ->map(fn ($id): string => (string) $id)
                ->all();
            $set = array_fill_keys($act_ids, true);

            if ($index === 0) {
                $result = $set;
                continue;
            }

            $op = $ops[$index - 1];
            if ($op === 'or') {
                $result = array_merge($result, $set);
            } else {
                $result = array_intersect_key($result, $set);
            }
        }

        return array_keys($result ?? []);
    }

    /**
     * @return array{acts: int, tags: int}
     */
    public function rebuildIndexForUser(string $login): array
    {
        $login = $this->normalizeLogin($login);
        if ($login === '') {
            return ['acts' => 0, 'tags' => 0];
        }

        DB::table('zen_act_user_tags')->where('viewer_login', $login)->delete();

        $act_ids = $this->discoverActIdsForUser($login);
        $all_tags = [];
        $rows = 0;

        foreach ($act_ids as $act_id) {
            try {
                AccessApp::make()->ensureReady($act_id);
            } catch (\Throwable) {
                continue;
            }

            $tags = $this->listForUser($act_id, $login);
            foreach ($tags as $tag) {
                DB::table('zen_act_user_tags')->insert([
                    'viewer_login' => $login,
                    'act_id' => $act_id,
                    'tag' => $tag,
                ]);
                $rows++;
                $all_tags[$tag] = true;
            }
        }

        $this->writeCache($login, array_keys($all_tags));

        return ['acts' => count($act_ids), 'tags' => $rows];
    }

    /**
     * @return array{users: int, rows: int}
     */
    public function rebuildAll(): array
    {
        DB::table('zen_act_user_tags')->truncate();

        $logins = $this->discoverAllTagLogins();
        $total_rows = 0;
        foreach ($logins as $login) {
            $stats = $this->rebuildIndexForUser($login);
            $total_rows += $stats['tags'];
        }

        return ['users' => count($logins), 'rows' => $total_rows];
    }

    public function normalizeTag(string $raw): string
    {
        $tag = trim($raw);
        $tag = preg_replace('/\s+/u', ' ', $tag) ?? $tag;
        if ($tag === '') {
            throw new \InvalidArgumentException('Тег не может быть пустым');
        }

        $first = mb_substr($tag, 0, 1);
        $rest = mb_substr($tag, 1);
        $tag = mb_strtoupper($first).$rest;

        $length = mb_strlen($tag);
        if ($length < self::MIN_TAG_LENGTH || $length > self::MAX_TAG_LENGTH) {
            throw new \InvalidArgumentException('Длина тега от '.self::MIN_TAG_LENGTH.' до '.self::MAX_TAG_LENGTH.' символов');
        }

        if (! preg_match('/^[\p{L}][\p{L}\p{N} \-]{2,29}$/u', $tag)) {
            throw new \InvalidArgumentException('Тег может содержать только буквы, цифры, пробел и дефис');
        }

        if (str_contains($tag, ',')) {
            throw new \InvalidArgumentException('Запятая в теге не допускается');
        }

        return $tag;
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    public function normalizeTagList(array $tags): array
    {
        $normalized = [];
        $seen = [];
        foreach ($tags as $raw) {
            $tag = $this->normalizeTag((string) $raw);
            $key = mb_strtolower($tag);
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException('Дубликат тега: '.$tag);
            }
            $seen[$key] = true;
            $normalized[] = $tag;
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncPgIndex(string $act_id, string $login, array $tags): void
    {
        DB::table('zen_act_user_tags')
            ->where('viewer_login', $login)
            ->where('act_id', $act_id)
            ->delete();

        foreach ($tags as $tag) {
            DB::table('zen_act_user_tags')->insert([
                'viewer_login' => $login,
                'act_id' => $act_id,
                'tag' => $tag,
            ]);
        }
    }

    /**
     * @param  list<string>  $previous
     * @param  list<string>  $next
     */
    private function syncCacheAfterChange(string $login, array $previous, array $next): void
    {
        $cache = $this->readCache($login);
        $cache_map = array_fill_keys($cache, true);

        foreach ($next as $tag) {
            $cache_map[$tag] = true;
        }

        foreach (array_diff($previous, $next) as $removed) {
            if (! $this->tagExistsForUser($login, $removed)) {
                unset($cache_map[$removed]);
            }
        }

        $updated = array_keys($cache_map);
        sort($updated, SORT_NATURAL | SORT_FLAG_CASE);
        $this->writeCache($login, $updated);
    }

    private function tagExistsForUser(string $login, string $tag): bool
    {
        return DB::table('zen_act_user_tags')
            ->where('viewer_login', $login)
            ->where('tag', $tag)
            ->exists();
    }

    private function maybeSyncOwnerRestore(string $act_id, string $login): void
    {
        $act = Act::find($act_id);
        if (! $act || $act->owner_id === null) {
            return;
        }

        $owner = $act->owner;
        if (! $owner) {
            return;
        }

        $owner_login = $this->normalizeLogin((string) ($owner->username ?? ''));
        if ($owner_login !== $login) {
            return;
        }

        ActApp::make()->syncRestoreFile($act_id);
    }

    /**
     * @return list<string>
     */
    private function readCache(string $login): array
    {
        $path = $this->cachePath($login);
        if (! is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || ! is_array($data['tags'] ?? null)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($tag): string => (string) $tag, $data['tags']),
            fn (string $tag): bool => $tag !== ''
        ));
    }

    /**
     * @param  list<string>  $tags
     */
    private function writeCache(string $login, array $tags): void
    {
        $path = $this->cachePath($login);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = json_encode([
            'tags' => array_values($tags),
            'updated_at' => Carbon::now('UTC')->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $tmp = $path.'.tmp';
        if (file_put_contents($tmp, $payload) === false) {
            throw new \RuntimeException('Не удалось записать tags_cache.json');
        }
        if (! rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Не удалось обновить tags_cache.json');
        }
    }

    private function cachePath(string $login): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $login) ?? $login;

        return storage_path('acts_users/'.$safe.'/tags_cache.json');
    }

    private function normalizeLogin(string $login): string
    {
        return trim($login);
    }

    /**
     * @return list<string>
     */
    private function discoverActIdsForUser(string $login): array
    {
        $from_pg = DB::table('zen_act_viewer_index')
            ->where('viewer_key', $login)
            ->pluck('act_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $from_storage = [];
        $root = storage_path('acts');
        if (is_dir($root)) {
            foreach (scandir($root) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $sqlite = ActStorage::make()->actDirectory($item).'/act.sqlite';
                if (! is_file($sqlite)) {
                    continue;
                }
                try {
                    $has = ActSqlite::make($item)
                        ->query('user_tags')
                        ->where('login', $login)
                        ->exists();
                    if ($has) {
                        $from_storage[] = $item;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        $ids = array_values(array_unique(array_merge($from_pg, $from_storage)));
        sort($ids);

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function discoverAllTagLogins(): array
    {
        $logins = [];
        $root = storage_path('acts');
        if (! is_dir($root)) {
            return [];
        }

        foreach (scandir($root) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $sqlite_path = ActStorage::make()->actDirectory($item).'/act.sqlite';
            if (! is_file($sqlite_path)) {
                continue;
            }
            try {
                $rows = ActSqlite::make($item)->query('user_tags')->select('login')->distinct()->get();
                foreach ($rows as $row) {
                    $login = $this->normalizeLogin((string) ($row->login ?? ''));
                    if ($login !== '') {
                        $logins[$login] = true;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $from_cache = storage_path('acts_users');
        if (is_dir($from_cache)) {
            foreach (scandir($from_cache) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                if (is_file($from_cache.'/'.$item.'/tags_cache.json')) {
                    $logins[$item] = true;
                }
            }
        }

        $result = array_keys($logins);
        sort($result);

        return $result;
    }
}
