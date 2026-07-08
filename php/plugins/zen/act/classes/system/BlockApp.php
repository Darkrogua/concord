<?php namespace Zen\Act\Classes\System;

use Carbon\Carbon;
use Illuminate\Support\Str;
use RainLab\User\Models\User;
use Zen\Act\Classes\Support\ActMutationLog;
use Zen\Act\Classes\Support\ActAssets;
use Zen\Act\Classes\Support\ActSqlite;
use Zen\Act\Models\Act;

class BlockApp
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $act_id, ?int $owner_id = null): array
    {
        $this->requireAct($act_id, $owner_id);

        return $this->listRows($act_id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForViewer(string $act_id, ?string $viewer_login = null, bool $front_view = false): array
    {
        $this->requireAct($act_id);
        $blocks = $this->listRows($act_id);

        return AccessApp::make()->filterBlocksForViewer($act_id, $viewer_login, $blocks, $front_view);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listRows(string $act_id): array
    {
        $rows = ActSqlite::make($act_id)
            ->query('blocks')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return $rows->map(fn ($row): array => $this->blockFromRow($row))->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(string $act_id, string $block_id, ?int $owner_id = null): ?array
    {
        $this->requireAct($act_id, $owner_id);

        $row = ActSqlite::make($act_id)->query('blocks')->where('id', $block_id)->first();

        return $row ? $this->blockFromRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function showForViewer(string $act_id, string $block_id, ?string $viewer_login = null, bool $front_view = false): ?array
    {
        $this->requireAct($act_id);
        $block = $this->show($act_id, $block_id);

        if ($block === null) {
            return null;
        }

        if (! AccessApp::make()->can($viewer_login, $act_id, 'block', $block_id, 'read', $front_view)) {
            return null;
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(string $act_id, array $payload, ?int $owner_id = null): array
    {
        $this->requireAct($act_id, $owner_id);

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Поле name обязательно');
        }

        $block_id = trim((string) ($payload['id'] ?? ''));
        if ($block_id === '') {
            $block_id = (string) Str::uuid();
        }

        $data = $this->payloadData($payload, null, $act_id, $block_id);

        SnapshotApp::make()->assertIntegrity($act_id);

        $sqlite = ActSqlite::make($act_id);

        $block = $sqlite->connection()->transaction(function () use ($sqlite, $block_id, $name, $data): array {
            $existing = $sqlite->query('blocks')->where('id', $block_id)->exists();
            if ($existing) {
                throw new \InvalidArgumentException("Блок id={$block_id} уже существует");
            }

            $now = $this->now();
            $sort_order = $this->nextSortOrder($sqlite);
            $block = [
                'id' => $block_id,
                'name' => $name,
                'data' => $data,
                'sort_order' => $sort_order,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $block['hash'] = $this->blockHash($block);

            $sqlite->query('blocks')->insert($this->blockToRow($block));
            $this->appendLog($sqlite, 'create', $block_id, $block, $now);

            return $block;
        });

        SnapshotApp::make()->finalizeMutation($act_id, 'blocks', [
            'action' => 'create',
            'block_id' => $block_id,
        ]);

        return $block;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function update(string $act_id, string $block_id, array $payload, ?int $owner_id = null): ?array
    {
        $this->requireAct($act_id, $owner_id);

        SnapshotApp::make()->assertIntegrity($act_id);

        $sqlite = ActSqlite::make($act_id);

        $block = $sqlite->connection()->transaction(function () use ($sqlite, $block_id, $payload, $act_id): ?array {
            $row = $sqlite->query('blocks')->where('id', $block_id)->first();
            if (! $row) {
                return null;
            }

            $block = $this->blockFromRow($row);
            if (array_key_exists('name', $payload)) {
                $name = trim((string) $payload['name']);
                if ($name === '') {
                    throw new \InvalidArgumentException('Поле name не может быть пустым');
                }
                $block['name'] = $name;
            }
            if (array_key_exists('data', $payload)) {
                $block['data'] = $this->payloadData($payload, $block['data'] ?? null, $act_id, $block_id);
            }

            $block['updated_at'] = $this->now();
            $block['hash'] = $this->blockHash($block);

            $sqlite->query('blocks')->where('id', $block_id)->update($this->blockToRow($block));
            $this->appendLog($sqlite, 'update', $block_id, $block, (string) $block['updated_at']);

            return $block;
        });

        if ($block !== null) {
            SnapshotApp::make()->finalizeMutation($act_id, 'blocks', [
                'action' => 'update',
                'block_id' => $block_id,
            ]);
        }

        return $block;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function signChecklistItem(string $act_id, string $block_id, string $item_id, int $user_id): ?array
    {
        $this->requireAct($act_id);

        /** @var User|null $user */
        $user = User::query()->where('is_guest', false)->find($user_id);
        if (! $user) {
            throw new \RuntimeException('Пользователь не найден');
        }

        $viewer_login = (string) ($user->username ?? '');
        $item_resource_id = $block_id.':'.$item_id;
        $access = AccessApp::make();

        SnapshotApp::make()->assertIntegrity($act_id);

        $sqlite = ActSqlite::make($act_id);
        $preview_row = $sqlite->query('blocks')->where('id', $block_id)->first();
        if (! $preview_row) {
            return null;
        }

        $preview_block = $this->blockFromRow($preview_row);
        $preview_data = is_array($preview_block['data'] ?? null) ? $preview_block['data'] : [];
        if (($preview_data['type'] ?? null) !== 'checklist') {
            throw new \InvalidArgumentException('Блок не является чеклистом');
        }

        if (! $this->canSignChecklistItem($access, $viewer_login, $act_id, $block_id, $item_id, $preview_data)) {
            throw new \RuntimeException('Нет прав на подписание');
        }

        $block = $sqlite->connection()->transaction(function () use ($sqlite, $block_id, $item_id, $user, $viewer_login, $act_id): ?array {
            $row = $sqlite->query('blocks')->where('id', $block_id)->first();
            if (! $row) {
                return null;
            }

            $block = $this->blockFromRow($row);
            $data = is_array($block['data'] ?? null) ? $this->normalizeData($block['data'], $block['data'], $act_id, $block_id) : [];
            if (($data['type'] ?? null) !== 'checklist') {
                throw new \InvalidArgumentException('Блок не является чеклистом');
            }

            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            $found = false;
            foreach ($items as $index => $item) {
                if (! is_array($item) || (string) ($item['id'] ?? '') !== $item_id) {
                    continue;
                }

                $found = true;
                $signer_login = trim((string) ($item['signer']['login'] ?? ''));
                if ($signer_login !== '' && strcasecmp($signer_login, $viewer_login) !== 0) {
                    throw new \RuntimeException('Этот пункт назначен другому пользователю');
                }
                if (is_array($item['signature'] ?? null)) {
                    throw new \RuntimeException('Пункт уже подписан');
                }

                $items[$index]['signature'] = [
                    'login' => $viewer_login,
                    'display_name' => $this->userDisplayName($user),
                    'signed_at' => Carbon::now('UTC')->toIso8601String(),
                ];
                break;
            }

            if (! $found) {
                throw new \RuntimeException('Пункт чеклиста не найден');
            }

            $data['items'] = $items;
            $block['data'] = $this->normalizeData($data, $data, $act_id, $block_id);
            $block['updated_at'] = $this->now();
            $block['hash'] = $this->blockHash($block);

            $sqlite->query('blocks')->where('id', $block_id)->update($this->blockToRow($block));
            $this->appendLog($sqlite, 'sign_checklist_item', $block_id, $block, (string) $block['updated_at']);

            return $block;
        });

        if ($block !== null) {
            SnapshotApp::make()->finalizeMutation($act_id, 'blocks', [
                'action' => 'sign_checklist_item',
                'block_id' => $block_id,
            ]);
        }

        return $block;
    }

    /**
     * @return array{deleted: bool, id: string}
     */
    public function delete(string $act_id, string $block_id, ?int $owner_id = null): array
    {
        $this->requireAct($act_id, $owner_id);

        SnapshotApp::make()->assertIntegrity($act_id);

        $sqlite = ActSqlite::make($act_id);

        $result = $sqlite->connection()->transaction(function () use ($sqlite, $block_id): array {
            $row = $sqlite->query('blocks')->where('id', $block_id)->first();
            if (! $row) {
                return ['deleted' => false, 'id' => $block_id];
            }

            $block = $this->blockFromRow($row);
            $sqlite->query('blocks')->where('id', $block_id)->delete();
            $this->appendLog($sqlite, 'delete', $block_id, $block, $this->now());

            return ['deleted' => true, 'id' => $block_id];
        });

        if ($result['deleted']) {
            SnapshotApp::make()->finalizeMutation($act_id, 'blocks', [
                'action' => 'delete',
                'block_id' => $block_id,
            ]);
        }

        return $result;
    }

    /**
     * @param  list<string>  $block_ids
     * @return array{blocks: list<array<string, mixed>>, order: list<string>}
     */
    public function reorder(string $act_id, array $block_ids, ?int $owner_id = null): array
    {
        $this->requireAct($act_id, $owner_id);

        $normalized_ids = [];
        foreach ($block_ids as $block_id) {
            $id = trim((string) $block_id);
            if ($id === '') {
                throw new \InvalidArgumentException('block_ids не может содержать пустые значения');
            }
            if (in_array($id, $normalized_ids, true)) {
                throw new \InvalidArgumentException('block_ids содержит дубликаты');
            }
            $normalized_ids[] = $id;
        }

        $sqlite = ActSqlite::make($act_id);
        $existing_ids = $sqlite->query('blocks')->orderBy('sort_order')->orderBy('created_at')->pluck('id')->map(fn ($id): string => (string) $id)->all();

        if ($normalized_ids === []) {
            if ($existing_ids !== []) {
                throw new \InvalidArgumentException('block_ids должен содержать все блоки акта');
            }

            return ['blocks' => [], 'order' => []];
        }

        sort($existing_ids);
        $sorted_requested = $normalized_ids;
        sort($sorted_requested);
        if ($existing_ids !== $sorted_requested) {
            throw new \InvalidArgumentException('block_ids должен содержать полный набор блоков акта');
        }

        SnapshotApp::make()->assertIntegrity($act_id);

        $sqlite->connection()->transaction(function () use ($sqlite, $normalized_ids): void {
            foreach ($normalized_ids as $index => $block_id) {
                $sqlite->query('blocks')->where('id', $block_id)->update([
                    'sort_order' => $index + 1,
                ]);
            }

            $this->appendReorderLog($sqlite, $normalized_ids, $this->now());
        });

        SnapshotApp::make()->finalizeMutation($act_id, 'blocks', [
            'action' => 'reorder',
            'order' => $normalized_ids,
        ]);

        return [
            'blocks' => $this->list($act_id),
            'order' => $normalized_ids,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function restoreBlocksFromSnapshot(string $act_id, array $blocks, ?int $owner_id = null): void
    {
        $this->requireAct($act_id, $owner_id);

        $sqlite = ActSqlite::make($act_id);

        $sqlite->connection()->transaction(function () use ($sqlite, $blocks): void {
            $sqlite->query('blocks')->delete();
            $sqlite->query('log')->delete();

            $now = $this->now();
            $position = 1;
            foreach ($blocks as $rawBlock) {
                if (! is_array($rawBlock)) {
                    continue;
                }

                $block = [
                    'id' => trim((string) ($rawBlock['id'] ?? '')),
                    'name' => trim((string) ($rawBlock['name'] ?? '')),
                    'data' => $rawBlock['data'] ?? null,
                    'sort_order' => $position,
                    'created_at' => (string) ($rawBlock['created_at'] ?? $now),
                    'updated_at' => (string) ($rawBlock['updated_at'] ?? $now),
                ];

                if ($block['id'] === '' || $block['name'] === '') {
                    continue;
                }

                $block['hash'] = $this->blockHash($block);
                $sqlite->query('blocks')->insert($this->blockToRow($block));
                $this->appendLog($sqlite, 'restore', $block['id'], $block, (string) $block['updated_at']);
                $position++;
            }
        });
    }

    public function replayLogToHead(string $act_id, int $log_head_id, ?int $owner_id = null): void
    {
        $this->requireAct($act_id, $owner_id);

        $sqlite = ActSqlite::make($act_id);

        $sqlite->connection()->transaction(function () use ($sqlite, $log_head_id): void {
            $sqlite->query('blocks')->delete();

            /** @var array<string, array<string, mixed>> $blocks_state */
            $blocks_state = [];
            $last_order = [];

            $rows = $sqlite->query('log')->where('id', '<=', $log_head_id)->orderBy('id')->get();
            foreach ($rows as $row) {
                $action = (string) $row->action;
                $block_id = $row->block_id !== null ? (string) $row->block_id : null;
                $data = $this->decodeData((string) $row->data);
                if (! is_array($data)) {
                    continue;
                }

                if ($action === 'reorder' && is_array($data['order'] ?? null)) {
                    $last_order = array_values(array_map('strval', $data['order']));

                    continue;
                }

                $block = is_array($data['block'] ?? null) ? $data['block'] : null;
                if ($block_id === null || $block === null) {
                    continue;
                }

                if ($action === 'delete') {
                    unset($blocks_state[$block_id]);

                    continue;
                }

                if (in_array($action, ['create', 'update', 'restore', 'sign_checklist_item'], true)) {
                    $blocks_state[$block_id] = $block;
                }
            }

            $position = 1;
            if ($last_order !== []) {
                foreach ($last_order as $ordered_id) {
                    if (! isset($blocks_state[$ordered_id])) {
                        continue;
                    }
                    $block = $blocks_state[$ordered_id];
                    $block['sort_order'] = $position;
                    $block['hash'] = $this->blockHash($block);
                    $sqlite->query('blocks')->insert($this->blockToRow($block));
                    unset($blocks_state[$ordered_id]);
                    $position++;
                }
            }

            uasort($blocks_state, function (array $a, array $b): int {
                $order_a = $a['sort_order'] ?? PHP_INT_MAX;
                $order_b = $b['sort_order'] ?? PHP_INT_MAX;
                if ($order_a !== $order_b) {
                    return $order_a <=> $order_b;
                }

                return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
            });

            foreach ($blocks_state as $block) {
                $block['sort_order'] = $position;
                $block['hash'] = $this->blockHash($block);
                $sqlite->query('blocks')->insert($this->blockToRow($block));
                $position++;
            }
        });
    }

    public function reanchorLogChain(string $act_id): void
    {
        $sqlite = ActSqlite::make($act_id);
        $rows = $sqlite->query('log')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return;
        }

        $previous_hash = null;
        foreach ($rows as $row) {
            $data = ActMutationLog::decodeData((string) $row->data);
            $hash = ActMutationLog::hash([
                'action' => (string) $row->action,
                'block_id' => $row->block_id !== null ? (string) $row->block_id : null,
                'data' => is_array($data) ? $data : [],
                'chain' => $previous_hash,
                'created_at' => (string) $row->created_at,
            ]);

            $sqlite->query('log')->where('id', (int) $row->id)->update([
                'chain' => $previous_hash,
                'hash' => $hash,
            ]);

            $previous_hash = $hash;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForSnapshot(string $act_id): array
    {
        $sqlitePath = ActSqlite::make($act_id)->path();
        if (! is_file($sqlitePath)) {
            return [];
        }

        try {
            return $this->list($act_id);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $act_id, ?int $owner_id = null): array
    {
        $this->requireAct($act_id, $owner_id);

        $sqlite = ActSqlite::make($act_id);
        $errors = [];

        $blocks_count = 0;
        foreach ($sqlite->query('blocks')->orderBy('sort_order')->orderBy('created_at')->get() as $row) {
            $blocks_count++;
            $block = $this->blockFromRow($row);
            $expected = $this->blockHash($block);
            if (! hash_equals($expected, (string) $block['hash'])) {
                $errors[] = [
                    'type' => 'block_hash',
                    'block_id' => (string) $block['id'],
                    'expected' => $expected,
                    'actual' => (string) $block['hash'],
                ];
            }
        }

        $previous_hash = null;
        $log_count = 0;
        foreach ($sqlite->query('log')->orderBy('id')->get() as $row) {
            $log_count++;
            $chain = $row->chain !== null ? (string) $row->chain : null;
            if ($chain !== $previous_hash) {
                $errors[] = [
                    'type' => 'log_chain',
                    'log_id' => (int) $row->id,
                    'expected' => $previous_hash,
                    'actual' => $chain,
                ];
            }

            $data = ActMutationLog::decodeData((string) $row->data);
            $expected = ActMutationLog::hash([
                'action' => (string) $row->action,
                'block_id' => $row->block_id !== null ? (string) $row->block_id : null,
                'data' => $data,
                'chain' => $chain,
                'created_at' => (string) $row->created_at,
            ]);

            if (! hash_equals($expected, (string) $row->hash)) {
                $errors[] = [
                    'type' => 'log_hash',
                    'log_id' => (int) $row->id,
                    'expected' => $expected,
                    'actual' => (string) $row->hash,
                ];
            }

            $previous_hash = (string) $row->hash;
        }

        return [
            'ok' => $errors === [],
            'act_id' => $act_id,
            'db_path' => $sqlite->path(),
            'blocks_count' => $blocks_count,
            'log_count' => $log_count,
            'head_hash' => $previous_hash,
            'errors' => $errors,
        ];
    }

    private function requireAct(string $act_id, ?int $owner_id = null): Act
    {
        $act = Act::find(trim($act_id));
        if (! $act) {
            throw new \RuntimeException("Акт id={$act_id} не найден");
        }

        if ($owner_id !== null) {
            $login = $this->loginFromUserId($owner_id);
            if ($login === null || ! AccessApp::make()->can($login, $act_id, 'act', $act_id, 'write', false)) {
                throw new \RuntimeException('Нет доступа к акту');
            }
        }

        return $act;
    }

    private function loginFromUserId(?int $user_id): ?string
    {
        if ($user_id === null) {
            return null;
        }

        $user = User::find($user_id);

        return $user ? (string) ($user->username ?? '') : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return mixed
     */
    private function payloadData(array $payload, mixed $existing_data = null, ?string $act_id = null, ?string $block_id = null): mixed
    {
        $data = $payload['data'] ?? [];
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        return $this->normalizeData($data, $existing_data, $act_id, $block_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function blockFromRow(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'data' => $this->decodeData((string) $row->data),
            'sort_order' => $row->sort_order !== null ? (int) $row->sort_order : null,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
            'hash' => (string) $row->hash,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function blockToRow(array $block): array
    {
        return [
            'id' => (string) $block['id'],
            'name' => (string) $block['name'],
            'data' => $this->canonicalJson($block['data']),
            'sort_order' => array_key_exists('sort_order', $block) && $block['sort_order'] !== null
                ? (int) $block['sort_order']
                : null,
            'created_at' => (string) $block['created_at'],
            'updated_at' => (string) $block['updated_at'],
            'hash' => (string) $block['hash'],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function blockHash(array $block): string
    {
        return hash('sha256', $this->canonicalJson([
            'name' => $block['name'] ?? '',
            'data' => $block['data'] ?? null,
            'created_at' => $block['created_at'] ?? null,
            'updated_at' => $block['updated_at'] ?? null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function logHash(array $entry): string
    {
        return ActMutationLog::hash($entry);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function appendLog(ActSqlite $sqlite, string $action, ?string $block_id, array $block, string $created_at): void
    {
        $previous = $sqlite->query('log')->orderByDesc('id')->first();
        $chain = $previous ? (string) $previous->hash : null;
        $data = ['block' => $block];
        $hash = ActMutationLog::hash([
            'action' => $action,
            'block_id' => $block_id,
            'data' => $data,
            'chain' => $chain,
            'created_at' => $created_at,
        ]);

        $sqlite->query('log')->insert([
            'action' => $action,
            'block_id' => $block_id,
            'data' => $this->canonicalJson($data),
            'hash' => $hash,
            'chain' => $chain,
            'created_at' => $created_at,
        ]);
    }

    /**
     * @param  list<string>  $block_ids
     */
    private function appendReorderLog(ActSqlite $sqlite, array $block_ids, string $created_at): void
    {
        $previous = $sqlite->query('log')->orderByDesc('id')->first();
        $chain = $previous ? (string) $previous->hash : null;
        $data = ['order' => $block_ids];
        $hash = ActMutationLog::hash([
            'action' => 'reorder',
            'block_id' => null,
            'data' => $data,
            'chain' => $chain,
            'created_at' => $created_at,
        ]);

        $sqlite->query('log')->insert([
            'action' => 'reorder',
            'block_id' => null,
            'data' => $this->canonicalJson($data),
            'hash' => $hash,
            'chain' => $chain,
            'created_at' => $created_at,
        ]);
    }

    private function nextSortOrder(ActSqlite $sqlite): int
    {
        $max = $sqlite->query('blocks')->max('sort_order');

        return ((int) $max) + 1;
    }

    private function now(): string
    {
        return Carbon::now('UTC')->toDateTimeString();
    }

    private function decodeData(string $json): mixed
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    private function canonicalJson(mixed $data): string
    {
        $normalized = $this->normalizeForHash($data);

        return json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isList($value)) {
            return array_map(fn ($item): mixed => $this->normalizeForHash($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeForHash($item);
        }

        return $value;
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function normalizeData(mixed $data, mixed $existing_data = null, ?string $act_id = null, ?string $block_id = null): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        if (array_key_exists('visibility', $data)) {
            unset($data['visibility']);
        }

        if (($data['type'] ?? null) === 'checklist' && $act_id !== null && $block_id !== null) {
            $data = $this->normalizeChecklistData($data, is_array($existing_data) ? $existing_data : null, $act_id, $block_id);
        }

        if (($data['type'] ?? null) === 'gallery' && $act_id !== null && $block_id !== null) {
            $data = $this->normalizeGalleryData($data, $act_id, $block_id);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $existing_data
     * @return array<string, mixed>
     */
    private function normalizeChecklistData(array $data, ?array $existing_data, string $act_id, string $block_id): array
    {
        $data['type'] = 'checklist';
        $data['description'] = trim((string) ($data['description'] ?? ''));

        $allowed_logins = [];
        foreach (AccessApp::make()->listSignerOptions($act_id, $block_id) as $option) {
            $allowed_logins[strtolower((string) $option['login'])] = $option;
        }

        $existing_by_id = [];
        if (($existing_data['type'] ?? null) === 'checklist' && is_array($existing_data['items'] ?? null)) {
            foreach ($existing_data['items'] as $item) {
                if (is_array($item) && (string) ($item['id'] ?? '') !== '') {
                    $existing_by_id[(string) $item['id']] = $item;
                }
            }
        }

        $items = [];
        $seen = [];
        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $raw_item) {
            if (! is_array($raw_item)) {
                continue;
            }

            $id = trim((string) ($raw_item['id'] ?? ''));
            if ($id === '') {
                $id = (string) Str::uuid();
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $existing = $existing_by_id[$id] ?? null;
            $existing_signature = is_array($existing['signature'] ?? null) ? $existing['signature'] : null;
            $text = trim((string) ($raw_item['text'] ?? ''));
            $signer = $this->normalizeChecklistSigner($raw_item['signer'] ?? null, $allowed_logins);

            if ($existing_signature !== null) {
                $existing_text = trim((string) ($existing['text'] ?? ''));
                $existing_signer_login = strtolower(trim((string) ($existing['signer']['login'] ?? '')));
                $raw_signer_login = strtolower(trim((string) ($raw_item['signer']['login'] ?? '')));
                if ($text !== $existing_text || ($raw_signer_login !== '' && $raw_signer_login !== $existing_signer_login)) {
                    throw new \InvalidArgumentException('Подписанный пункт чеклиста нельзя изменять');
                }

                $signer = is_array($existing['signer'] ?? null) ? $existing['signer'] : $signer;
            }

            $items[] = [
                'id' => $id,
                'text' => $text,
                'signer' => $signer,
                'signature' => $existing_signature,
            ];
        }

        foreach ($existing_by_id as $id => $existing) {
            if (is_array($existing['signature'] ?? null) && ! isset($seen[$id])) {
                throw new \InvalidArgumentException('Подписанный пункт чеклиста нельзя удалить');
            }
        }

        $data['items'] = $items;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeGalleryData(array $data, string $act_id, string $block_id): array
    {
        $data['type'] = 'gallery';
        $assets = ActAssets::make();
        $items = [];
        $seen = [];
        $sort = 0;

        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $raw_item) {
            if (! is_array($raw_item)) {
                continue;
            }

            $id = trim((string) ($raw_item['id'] ?? ''));
            if ($id === '') {
                $id = (string) Str::uuid();
            }

            if (isset($seen[$id])) {
                continue;
            }

            if (! $assets->imageExists($act_id, $block_id, $id)) {
                continue;
            }

            $seen[$id] = true;
            $path = $assets->resolvePath($act_id, $block_id, $id);
            $size_bytes = $path !== null ? (int) filesize($path) : (int) ($raw_item['size_bytes'] ?? 0);
            $width = isset($raw_item['width']) ? (int) $raw_item['width'] : null;
            $height = isset($raw_item['height']) ? (int) $raw_item['height'] : null;

            if ($path !== null) {
                $image_size = @getimagesize($path);
                if (is_array($image_size)) {
                    $width = isset($image_size[0]) ? (int) $image_size[0] : $width;
                    $height = isset($image_size[1]) ? (int) $image_size[1] : $height;
                }
            }

            $filename = trim((string) ($raw_item['filename'] ?? ''));
            if ($filename === '') {
                $filename = $id;
            }

            $mime = trim((string) ($raw_item['mime'] ?? ''));
            if ($mime === '' && $path !== null) {
                $mime = $assets->mimeForPath($path);
            }

            $items[] = [
                'id' => $id,
                'filename' => $filename,
                'mime' => $mime,
                'size_bytes' => $size_bytes,
                'width' => $width,
                'height' => $height,
                'alt' => trim((string) ($raw_item['alt'] ?? '')),
                'active' => filter_var($raw_item['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => array_key_exists('sort_order', $raw_item)
                    ? (int) $raw_item['sort_order']
                    : $sort,
            ];
            $sort++;
        }

        usort($items, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['id'], $b['id']));

        if (count($items) > ActAssets::MAX_ITEMS_PER_BLOCK) {
            $items = array_slice($items, 0, ActAssets::MAX_ITEMS_PER_BLOCK);
        }

        $data['items'] = array_values($items);

        return $data;
    }

    /**
     * @param  array<string, array{login: string, role: string, display_name: string}>  $allowed_logins
     * @return array{login: string, display_name: string}|null
     */
    private function normalizeChecklistSigner(mixed $signer, array $allowed_logins): ?array
    {
        if (! is_array($signer)) {
            return null;
        }

        $login = trim((string) ($signer['login'] ?? ''));
        if ($login === '') {
            return null;
        }

        $key = strtolower($login);
        if (! isset($allowed_logins[$key])) {
            return null;
        }

        $allowed = $allowed_logins[$key];

        return [
            'login' => (string) $allowed['login'],
            'display_name' => (string) $allowed['display_name'],
        ];
    }

    private function userDisplayName(User $user): string
    {
        if ($user->name !== null && trim((string) $user->name) !== '') {
            return trim((string) $user->name);
        }

        return (string) ($user->username ?? 'User');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function canSignChecklistItem(
        AccessApp $access,
        string $viewer_login,
        string $act_id,
        string $block_id,
        string $item_id,
        array $data,
    ): bool {
        if ($access->can($viewer_login, $act_id, 'item', $block_id.':'.$item_id, 'sign', false)
            || $access->can($viewer_login, $act_id, 'block', $block_id, 'sign', false)) {
            return true;
        }

        $signer_login = $this->checklistItemSignerLogin($data, $item_id);
        if ($signer_login === '' || strcasecmp($signer_login, $viewer_login) !== 0) {
            return false;
        }

        return $access->can($viewer_login, $act_id, 'block', $block_id, 'read', false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checklistItemSignerLogin(array $data, string $item_id): string
    {
        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (! is_array($item) || (string) ($item['id'] ?? '') !== $item_id) {
                continue;
            }

            return trim((string) ($item['signer']['login'] ?? ''));
        }

        return '';
    }
}
