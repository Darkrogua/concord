<?php namespace Zen\Chub\Classes\Patches;

use Exception;
use Illuminate\Support\Facades\DB;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Models\Base;

class Patch190320261500
{
    private const string BASE_CODE = 'uon-payments';

    # Dotpath: Zen.Chub.Classes.Patches.Patch190320261500.handle
    public function handle(): void
    {
        $base = Base::where('code', self::BASE_CODE)->first();
        if (!$base) {
            throw new Exception('База не найдена: ' . self::BASE_CODE);
        }

        $db_path = $base->getBasePath();
        if (!file_exists($db_path)) {
            return;
        }

        $sqlite = Sqlite::connect($db_path);
        if (!$sqlite->tableExists('records')) {
            return;
        }

        $records = DB::connection($sqlite->getConnectionName())
            ->table('records')
            ->selectRaw('rowid as _rowid, uid, order_id, request_data, created_at, is_primary')
            ->orderBy('created_at')
            ->orderBy('_rowid')
            ->get()
            ->map(function ($row) {
                return [
                    '_rowid' => intval($row->_rowid),
                    'uid' => (string) ($row->uid ?? ''),
                    'order_id' => (string) ($row->order_id ?? ''),
                    'request_data' => (string) ($row->request_data ?? ''),
                    'created_at' => $row->created_at ?? null,
                    'is_primary' => intval($row->is_primary ?? 0),
                ];
            })
            ->toArray();

        $this->backupRecords($records);

        $normalized = [];
        $seen = [];
        foreach ($records as $record) {
            $uid = $this->normalizeUid($record['uid']);
            if (!$uid) {
                continue;
            }
            if (isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;

            $record['uid'] = $uid;
            $normalized[] = $record;
        }

        DB::connection($sqlite->getConnectionName())->table('records')->delete();
        foreach ($normalized as $record) {
            DB::connection($sqlite->getConnectionName())->table('records')->insert([
                'uid' => $record['uid'],
                'order_id' => $record['order_id'],
                'request_data' => $record['request_data'],
                'created_at' => $record['created_at'],
                'is_primary' => $record['is_primary'],
            ]);
        }
    }

    private function normalizeUid(string $uid): string
    {
        $uid = trim($uid);
        if ($uid === '') {
            return '';
        }

        $parts = explode('-', $uid);
        if (count($parts) < 2) {
            return $uid;
        }

        return $parts[0] . '-' . $parts[1];
    }

    private function backupRecords(array $records): void
    {
        $backup_dir = Files::make()->defineFilePath(
            storage_path('chub/patches')
        );
        $backup_path = $backup_dir . '/uon-payments-uid-normalize-backup-' . now()->format('Ymd_His') . '.json';

        Transformers::make()->arrayToFile([
            'base_code' => self::BASE_CODE,
            'created_at' => now()->toDateTimeString(),
            'count' => count($records),
            'records' => $records,
        ], $backup_path);
    }
}
