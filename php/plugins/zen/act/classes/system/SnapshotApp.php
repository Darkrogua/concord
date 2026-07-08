<?php namespace Zen\Act\Classes\System;

use Carbon\Carbon;
use RainLab\User\Models\User;
use Zen\Act\Classes\Exceptions\ActIntegrityException;
use Zen\Act\Classes\Support\ActEnvelope;
use Zen\Act\Classes\Support\ActAssets;
use Zen\Act\Classes\Support\ActSqlite;
use Zen\Act\Classes\Support\ActStorage;
use Zen\Act\Models\Act;

class SnapshotApp
{
    public const CURRENT_FORMAT_VERSION = 2;

    public static function make(): self
    {
        return new self();
    }

    /**
     * @throws ActIntegrityException
     */
    public function assertIntegrity(string $act_id): void
    {
        $verify = BlockApp::make()->verify($act_id);
        if ($verify['ok'] ?? false) {
            return;
        }

        $errors = is_array($verify['errors'] ?? null) ? $verify['errors'] : [];
        if ($this->errorsAreRepairableLogHashes($errors)) {
            BlockApp::make()->reanchorLogChain($act_id);
            $verify = BlockApp::make()->verify($act_id);
            if ($verify['ok'] ?? false) {
                return;
            }
            $errors = is_array($verify['errors'] ?? null) ? $verify['errors'] : [];
        }

        throw new ActIntegrityException(
            'Проверка целостности акта не пройдена',
            $errors
        );
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    private function errorsAreRepairableLogHashes(array $errors): bool
    {
        if ($errors === []) {
            return false;
        }

        foreach ($errors as $error) {
            if (($error['type'] ?? '') !== 'log_hash') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function finalizeMutation(string $act_id, string $scope, array $context = []): int
    {
        $this->assertIntegrity($act_id);

        return $this->appendAfterMutation($act_id, $scope, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function appendAfterMutation(string $act_id, string $scope, array $context = []): int
    {
        $act = Act::find(trim($act_id));
        if (! $act) {
            throw new \RuntimeException("Акт id={$act_id} не найден");
        }

        $log_head_id = $this->logHeadId($act_id);
        $envelope = $this->buildEnvelope($act);
        $envelope_hash = ActEnvelope::make()->hash($envelope);
        $payload = $this->buildDeltaPayload($scope, $context, $log_head_id);
        $payload_json = ActEnvelope::make()->canonicalJson($payload);
        $created_at = Carbon::now('UTC')->toIso8601String();

        $sqlite = ActSqlite::make($act_id);
        $previous = $sqlite->query('snapshots')->orderByDesc('id')->first();
        $prev_hash = $previous ? (string) $previous->hash : null;
        $hash = $this->snapshotHash([
            'envelope_hash' => $envelope_hash,
            'kind' => 'delta',
            'scope' => $scope,
            'payload' => $payload,
            'log_head_id' => $log_head_id,
            'created_at' => $created_at,
            'prev_hash' => $prev_hash,
        ]);

        $snapshot_id = (int) $sqlite->query('snapshots')->insertGetId([
            'created_at' => $created_at,
            'kind' => 'delta',
            'scope' => $scope,
            'payload' => $payload_json,
            'log_head_id' => $log_head_id,
            'envelope_hash' => $envelope_hash,
            'prev_hash' => $prev_hash,
            'hash' => $hash,
            'size_bytes' => strlen($payload_json),
        ]);

        ActApp::make()->syncCurrentFile($act_id);

        return $snapshot_id;
    }

    public function appendInitialCheckpoint(string $act_id): int
    {
        return $this->appendCheckpoint($act_id, 'checkpoint', 'all', []);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function appendCheckpoint(string $act_id, string $kind, string $scope, array $context = []): int
    {
        $act = Act::find(trim($act_id));
        if (! $act) {
            throw new \RuntimeException("Акт id={$act_id} не найден");
        }

        $log_head_id = $this->logHeadId($act_id);
        $access = AccessApp::make()->dumpForSnapshot($act_id);
        $owner_tags = TagsApp::make()->dumpOwnerTagsForSnapshot($act_id);
        $payload = [
            'act_meta' => $this->actMetaFromAct($act),
            'access' => $access,
            'owner_user_tags' => array_values($owner_tags),
        ];
        if ($context !== []) {
            $payload = array_merge($payload, $context);
        }

        return $this->insertSnapshotRow($act_id, $kind, $scope, $payload, $log_head_id, $act);
    }

    /**
     * @return list<array{snapshot_id: int, snapshot_key: string, display_index: int, timestamp: string, created_at: string, kind: string, scope: string, size_bytes: int}>
     */
    public function list(string $act_id): array
    {
        $sqlite = ActSqlite::make($act_id);
        if (! $this->hasSnapshotsTable($act_id)) {
            return [];
        }

        $rows = $sqlite->query('snapshots')->orderByDesc('id')->get();
        $items = [];
        $index = 1;
        foreach ($rows as $row) {
            $created_at = (string) $row->created_at;
            $items[] = [
                'snapshot_id' => (int) $row->id,
                'snapshot_key' => (string) $row->id,
                'display_index' => $index,
                'timestamp' => $this->compactTimestamp($created_at),
                'created_at' => $created_at,
                'kind' => (string) $row->kind,
                'scope' => (string) $row->scope,
                'size_bytes' => (int) $row->size_bytes,
            ];
            $index++;
        }

        return $items;
    }

    public function count(string $act_id): int
    {
        if (! $this->hasSnapshotsTable($act_id)) {
            return 0;
        }

        return (int) ActSqlite::make($act_id)->query('snapshots')->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(string $act_id, int $snapshot_id, ?int $owner_id = null): array
    {
        if ($owner_id !== null) {
            ActApp::make()->requireOwnedActPublic($owner_id, $act_id);
        }

        $sqlite = ActSqlite::make($act_id);
        $row = $sqlite->query('snapshots')->where('id', $snapshot_id)->first();
        if (! $row) {
            throw new \RuntimeException('Снимок не найден');
        }

        $this->assertIntegrity($act_id);

        $state = $this->resolveStateAtSnapshot($act_id, (int) $row->id);
        $this->applyResolvedState($act_id, $state, $owner_id);

        $this->assertIntegrity($act_id);
        $assets_reconcile = ActAssets::make()->reconcileImages($act_id);
        $restore_id = $this->appendAfterMutation($act_id, 'all', ['action' => 'restore', 'from_snapshot_id' => $snapshot_id]);

        return [
            'restored_from' => $snapshot_id,
            'snapshot_key' => (string) $snapshot_id,
            'snapshot_id' => $restore_id,
            'act' => ActApp::make()->actCard(Act::find($act_id)),
            'assets_reconcile' => $assets_reconcile,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeRange(string $act_id, int $from_index, int $to_index, ?int $owner_id = null): array
    {
        if ($owner_id !== null) {
            ActApp::make()->requireOwnedActPublic($owner_id, $act_id);
        }

        if ($from_index < 1 || $to_index < 1) {
            throw new \InvalidArgumentException('Номера версий должны быть >= 1');
        }
        if ($from_index > $to_index) {
            throw new \InvalidArgumentException('from_index не может быть больше to_index');
        }

        $items = $this->list($act_id);
        if ($items === []) {
            throw new \RuntimeException('Нет версий для слияния');
        }

        if ($from_index > count($items) || $to_index > count($items)) {
            throw new \InvalidArgumentException('Диапазон версий выходит за пределы списка');
        }

        $target = $items[$to_index - 1];
        $oldest_in_range = $items[$from_index - 1];
        $target_id = (int) $target['snapshot_id'];
        $ids_to_remove = [];
        for ($i = $from_index - 1; $i <= $to_index - 1; $i++) {
            $ids_to_remove[] = (int) $items[$i]['snapshot_id'];
        }

        $state = $this->resolveStateAtSnapshot($act_id, $target_id);
        $payload = [
            'act_meta' => $state['act_meta'],
            'access' => $state['access'],
            'owner_user_tags' => $state['owner_user_tags'],
            'merged_from' => $ids_to_remove,
            'merged_count' => count($ids_to_remove),
        ];

        $sqlite = ActSqlite::make($act_id);
        $sqlite->connection()->transaction(function () use ($sqlite, $ids_to_remove): void {
            $sqlite->query('snapshots')->whereIn('id', $ids_to_remove)->delete();
        });

        $act = Act::find($act_id);
        if (! $act) {
            throw new \RuntimeException("Акт id={$act_id} не найден");
        }

        $merged_id = $this->insertSnapshotRowFromState(
            $act_id,
            'merged',
            'all',
            $payload,
            $state['log_head_id'],
            (string) $oldest_in_range['created_at'],
            $state
        );

        $prune = $this->pruneLog($act_id);
        $assets_reconcile = ActAssets::make()->reconcileImages($act_id);

        return [
            'merged_id' => $merged_id,
            'removed_ids' => $ids_to_remove,
            'removed_count' => count($ids_to_remove),
            'log_prune' => $prune,
            'assets_reconcile' => $assets_reconcile,
            'snapshots_count' => $this->count($act_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pruneLog(string $act_id): array
    {
        $sqlite = ActSqlite::make($act_id);
        $min_log_id = $sqlite->query('snapshots')
            ->whereNotNull('log_head_id')
            ->min('log_head_id');

        $min_log_id = $min_log_id !== null ? (int) $min_log_id : 0;
        if ($min_log_id <= 1) {
            return ['pruned' => 0, 'min_log_id' => $min_log_id];
        }

        $deleted = $sqlite->query('log')->where('id', '<', $min_log_id)->delete();
        BlockApp::make()->reanchorLogChain($act_id);

        $sqlite->query('access_meta')->updateOrInsert(
            ['key' => 'log_anchor_id'],
            ['value' => (string) $min_log_id]
        );

        return [
            'pruned' => (int) $deleted,
            'min_log_id' => $min_log_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    public function importLegacyJson(string $act_id, array $legacy, string $created_at, string $kind = 'checkpoint'): int
    {
        $act = Act::find($act_id);
        if (! $act) {
            throw new \RuntimeException("Акт id={$act_id} не найден");
        }

        $payload = [
            'act_meta' => [
                'name' => (string) ($legacy['name'] ?? $act->name),
                'description' => array_key_exists('description', $legacy) ? $legacy['description'] : $act->description,
                'owner_id' => array_key_exists('owner_id', $legacy) ? $legacy['owner_id'] : $act->owner_id,
                'activate_at' => $legacy['activate_at'] ?? null,
                'stop_at' => $legacy['stop_at'] ?? null,
            ],
            'access' => is_array($legacy['access'] ?? null) ? $legacy['access'] : AccessApp::make()->dumpForSnapshot($act_id),
            'owner_user_tags' => is_array($legacy['owner_user_tags'] ?? null) ? array_values($legacy['owner_user_tags']) : [],
            'blocks' => is_array($legacy['blocks'] ?? null) ? $legacy['blocks'] : [],
            'legacy_import' => true,
        ];

        $log_head_id = $this->logHeadId($act_id);

        return $this->insertSnapshotRow($act_id, $kind, 'all', $payload, $log_head_id, $act, $created_at);
    }

    public function logHeadId(string $act_id): ?int
    {
        $sqlitePath = ActSqlite::make($act_id)->path();
        if (! is_file($sqlitePath)) {
            return null;
        }

        $max = ActSqlite::make($act_id)->query('log')->max('id');

        return $max !== null ? (int) $max : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEnvelope(Act $act): array
    {
        $blocks = BlockApp::make()->listForSnapshot((string) $act->id);
        $access = AccessApp::make()->dumpForSnapshot((string) $act->id);
        $owner_tags = TagsApp::make()->dumpOwnerTagsForSnapshot((string) $act->id);

        return ActEnvelope::make()->build($act, $blocks, $access, $owner_tags);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insertSnapshotRow(
        string $act_id,
        string $kind,
        string $scope,
        array $payload,
        ?int $log_head_id,
        Act $act,
        ?string $created_at = null,
    ): int {
        $created_at = $created_at ?? Carbon::now('UTC')->toIso8601String();
        $envelope = $this->buildEnvelope($act);
        $envelope_hash = ActEnvelope::make()->hash($envelope);
        $payload_json = ActEnvelope::make()->canonicalJson($payload);

        $sqlite = ActSqlite::make($act_id);
        $previous = $sqlite->query('snapshots')->orderByDesc('id')->first();
        $prev_hash = $previous ? (string) $previous->hash : null;
        $hash = $this->snapshotHash([
            'envelope_hash' => $envelope_hash,
            'kind' => $kind,
            'scope' => $scope,
            'payload' => $payload,
            'log_head_id' => $log_head_id,
            'created_at' => $created_at,
            'prev_hash' => $prev_hash,
        ]);

        return (int) $sqlite->query('snapshots')->insertGetId([
            'created_at' => $created_at,
            'kind' => $kind,
            'scope' => $scope,
            'payload' => $payload_json,
            'log_head_id' => $log_head_id,
            'envelope_hash' => $envelope_hash,
            'prev_hash' => $prev_hash,
            'hash' => $hash,
            'size_bytes' => strlen($payload_json),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{act_meta: array<string, mixed>, access: array<string, mixed>, owner_user_tags: list<string>, log_head_id: ?int}  $state
     */
    private function insertSnapshotRowFromState(
        string $act_id,
        string $kind,
        string $scope,
        array $payload,
        ?int $log_head_id,
        string $created_at,
        array $state,
    ): int {
        $envelope_hash = hash('sha256', ActEnvelope::make()->canonicalJson([
            'act_meta' => $state['act_meta'],
            'access' => $state['access'],
            'owner_user_tags' => $state['owner_user_tags'],
            'log_head_id' => $log_head_id,
        ]));
        $payload_json = ActEnvelope::make()->canonicalJson($payload);

        $sqlite = ActSqlite::make($act_id);
        $previous = $sqlite->query('snapshots')->orderByDesc('id')->first();
        $prev_hash = $previous ? (string) $previous->hash : null;
        $hash = $this->snapshotHash([
            'envelope_hash' => $envelope_hash,
            'kind' => $kind,
            'scope' => $scope,
            'payload' => $payload,
            'log_head_id' => $log_head_id,
            'created_at' => $created_at,
            'prev_hash' => $prev_hash,
        ]);

        return (int) $sqlite->query('snapshots')->insertGetId([
            'created_at' => $created_at,
            'kind' => $kind,
            'scope' => $scope,
            'payload' => $payload_json,
            'log_head_id' => $log_head_id,
            'envelope_hash' => $envelope_hash,
            'prev_hash' => $prev_hash,
            'hash' => $hash,
            'size_bytes' => strlen($payload_json),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildDeltaPayload(string $scope, array $context, ?int $log_head_id): array
    {
        if ($scope === 'blocks') {
            return array_merge([
                'log_head_id' => $log_head_id,
            ], $context);
        }

        if ($scope === 'all') {
            return $context;
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function snapshotHash(array $entry): string
    {
        return hash('sha256', ActEnvelope::make()->canonicalJson($entry));
    }

    /**
     * @return array{act_meta: array<string, mixed>, access: array<string, mixed>, owner_user_tags: list<string>, log_head_id: ?int, blocks: list<array<string, mixed>>}
     */
    private function resolveStateAtSnapshot(string $act_id, int $snapshot_id): array
    {
        $sqlite = ActSqlite::make($act_id);
        $target = $sqlite->query('snapshots')->where('id', $snapshot_id)->first();
        if (! $target) {
            throw new \RuntimeException('Снимок не найден');
        }

        $base = $sqlite->query('snapshots')
            ->where('id', '<=', $snapshot_id)
            ->whereIn('kind', ['checkpoint', 'merged'])
            ->orderByDesc('id')
            ->first();

        if ($base === null) {
            $base = $sqlite->query('snapshots')->where('id', '<=', $snapshot_id)->orderBy('id')->first();
        }

        if ($base === null) {
            throw new \RuntimeException('Базовый снимок не найден');
        }

        $payload = $this->decodePayload((string) $base->payload);
        $state = [
            'act_meta' => is_array($payload['act_meta'] ?? null) ? $payload['act_meta'] : $this->defaultActMeta($act_id),
            'access' => is_array($payload['access'] ?? null) ? $payload['access'] : AccessApp::make()->dumpForSnapshot($act_id),
            'owner_user_tags' => is_array($payload['owner_user_tags'] ?? null) ? array_values($payload['owner_user_tags']) : [],
            'log_head_id' => $target->log_head_id !== null ? (int) $target->log_head_id : null,
            'blocks' => is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [],
        ];

        $deltas = $sqlite->query('snapshots')
            ->where('id', '>', (int) $base->id)
            ->where('id', '<=', $snapshot_id)
            ->orderBy('id')
            ->get();

        foreach ($deltas as $delta) {
            if (in_array((string) $delta->kind, ['checkpoint', 'merged'], true)) {
                $delta_payload = $this->decodePayload((string) $delta->payload);
                if (is_array($delta_payload['act_meta'] ?? null)) {
                    $state['act_meta'] = array_merge($state['act_meta'], $delta_payload['act_meta']);
                }
                if (is_array($delta_payload['access'] ?? null)) {
                    $state['access'] = $delta_payload['access'];
                }
                if (is_array($delta_payload['owner_user_tags'] ?? null)) {
                    $state['owner_user_tags'] = array_values($delta_payload['owner_user_tags']);
                }
                if (is_array($delta_payload['blocks'] ?? null) && $delta_payload['blocks'] !== []) {
                    $state['blocks'] = $delta_payload['blocks'];
                }
            } else {
                $delta_payload = $this->decodePayload((string) $delta->payload);
                $scope = (string) $delta->scope;
                if ($scope === 'act' && is_array($delta_payload['patch'] ?? null)) {
                    $state['act_meta'] = $this->applyActPatch($state['act_meta'], $delta_payload['patch']);
                } elseif ($scope === 'access' && is_array($delta_payload['access'] ?? null)) {
                    $state['access'] = $delta_payload['access'];
                } elseif ($scope === 'tags' && is_array($delta_payload['owner_user_tags'] ?? null)) {
                    $state['owner_user_tags'] = array_values($delta_payload['owner_user_tags']);
                }
            }

            if ($delta->log_head_id !== null) {
                $state['log_head_id'] = (int) $delta->log_head_id;
            }
        }

        return $state;
    }

    /**
     * @param  array{act_meta: array<string, mixed>, access: array<string, mixed>, owner_user_tags: list<string>, log_head_id: ?int, blocks: list<array<string, mixed>>}  $state
     */
    private function applyResolvedState(string $act_id, array $state, ?int $owner_id): void
    {
        $act = Act::find($act_id);
        if (! $act) {
            throw new \RuntimeException("Акт id={$act_id} не найден");
        }

        $meta = $state['act_meta'];
        $act->name = (string) ($meta['name'] ?? $act->name);
        $act->description = array_key_exists('description', $meta)
            ? ($meta['description'] !== null ? (string) $meta['description'] : null)
            : $act->description;
        if (array_key_exists('owner_id', $meta)) {
            $act->owner_id = $meta['owner_id'] !== null ? (int) $meta['owner_id'] : null;
        }
        $act->activate_at = array_key_exists('activate_at', $meta) ? ActApp::make()->parseDateOrNullPublic($meta['activate_at']) : $act->activate_at;
        $act->stop_at = array_key_exists('stop_at', $meta) ? ActApp::make()->parseDateOrNullPublic($meta['stop_at']) : $act->stop_at;
        $act->save();

        if ($state['blocks'] !== [] && ($state['log_head_id'] === null || $this->isLegacyBlocksPayload($state['blocks']))) {
            BlockApp::make()->restoreBlocksFromSnapshot($act_id, $state['blocks'], $owner_id);
        } elseif ($state['log_head_id'] !== null) {
            BlockApp::make()->replayLogToHead($act_id, $state['log_head_id'], $owner_id);
        }

        AccessApp::make()->restoreFromSnapshot($act_id, $state['access']);

        $owner_login = null;
        if ($act->owner_id !== null) {
            $owner = $act->owner ?? User::find((int) $act->owner_id);
            if ($owner) {
                $owner_login = (string) ($owner->username ?? '');
            }
        }
        if ($owner_login !== null && trim($owner_login) !== '') {
            TagsApp::make()->restoreOwnerTags($act_id, $owner_login, $state['owner_user_tags']);
        }

        ActApp::make()->syncCurrentFile($act_id);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function isLegacyBlocksPayload(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (is_array($block) && array_key_exists('hash', $block)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultActMeta(string $act_id): array
    {
        $act = Act::find($act_id);
        if (! $act) {
            return ['name' => ''];
        }

        return $this->actMetaFromAct($act);
    }

    /**
     * @return array<string, mixed>
     */
    private function actMetaFromAct(Act $act): array
    {
        return [
            'name' => (string) ($act->name ?? ''),
            'description' => $act->description !== null ? (string) $act->description : null,
            'owner_id' => $act->owner_id !== null ? (int) $act->owner_id : null,
            'activate_at' => $act->activate_at?->toIso8601String(),
            'stop_at' => $act->stop_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function applyActPatch(array $meta, array $patch): array
    {
        foreach ($patch as $field => $change) {
            if (is_array($change) && array_key_exists('to', $change)) {
                $meta[$field] = $change['to'];
            }
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $json): array
    {
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    private function compactTimestamp(string $iso): string
    {
        try {
            return Carbon::parse($iso)->utc()->format('YmdHis');
        } catch (\Throwable) {
            return '';
        }
    }

    private function hasSnapshotsTable(string $act_id): bool
    {
        $sqlitePath = ActSqlite::make($act_id)->path();

        return is_file($sqlitePath);
    }
}
