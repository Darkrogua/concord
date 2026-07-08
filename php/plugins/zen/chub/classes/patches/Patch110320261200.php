<?php namespace Zen\Chub\Classes\Patches;

use Exception;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Models\Base;

class Patch110320261200
{
    private const string BASE_CODE = 'uon-payments';

    # Dotpath: Zen.Chub.Classes.Patches.Patch110320261200.handle
    public function handle(): void
    {
        $base = Base::where('code', self::BASE_CODE)->first();
        if (!$base) {
            throw new Exception('База не найдена: ' . self::BASE_CODE);
        }

        $backup = $this->backupRecords($base);
        $deduped_records = $this->dedupeByUid($backup['records']);

        $new_schema = $this->makeSchemaWithUniqueUid();
        $this->updateBasesDumpSchema($new_schema);
        $this->updateBaseModelSchema($base, $new_schema);

        # Пересоздаём sqlite файл, чтобы точно применить новую схему
        $base->clearBase();

        $this->restoreRecords($base, $deduped_records);
    }

    private function backupRecords(Base $base): array
    {
        $records = $this->fetchRecordsFromBase($base);

        $backup_dir = Files::make()->defineFilePath(
            storage_path('chub/patches')
        );
        $backup_path = $backup_dir . '/uon-payments-backup-' . now()->format('Ymd_His') . '.json';

        $payload = [
            'base_code' => self::BASE_CODE,
            'created_at' => now()->toDateTimeString(),
            'records' => $records,
            'count' => count($records),
        ];

        Transformers::make()->arrayToFile($payload, $backup_path);

        return $payload;
    }

    private function fetchRecordsFromBase(Base $base): array
    {
        $db_path = $base->getBasePath();
        if (!file_exists($db_path)) {
            return [];
        }

        $sqlite = Sqlite::connect($db_path);
        if (!$sqlite->tableExists('records')) {
            return [];
        }

        $rows = $sqlite->query('records')->get();
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'uid' => (string) ($row->uid ?? ''),
                'order_id' => (string) ($row->order_id ?? ''),
                'request_data' => (string) ($row->request_data ?? ''),
                'created_at' => $row->created_at ?? now()->toDateTimeString(),
                'is_primary' => intval($row->is_primary ?? 0),
            ];
        }

        return $data;
    }

    private function dedupeByUid(array $records): array
    {
        $seen_uids = [];
        $deduped = [];

        foreach ($records as $record) {
            $uid = trim((string) ($record['uid'] ?? ''));
            if ($uid === '') {
                continue;
            }

            if (isset($seen_uids[$uid])) {
                continue;
            }

            $seen_uids[$uid] = true;
            $deduped[] = $record;
        }

        return $deduped;
    }

    private function updateBasesDumpSchema(string $new_schema): void
    {
        $dump_path = base_path('plugins/zen/chub/data/bases/bases_data.json');
        $bases_data = Transformers::make()->arrayFromFile($dump_path);
        if (!$bases_data) {
            throw new Exception('Не удалось прочитать файл схем баз: ' . $dump_path);
        }

        foreach ($bases_data as &$base_data) {
            if (($base_data['code'] ?? null) === self::BASE_CODE) {
                $base_data['schema'] = $new_schema;
                $base_data['updated_at'] = now()->toDateTimeString();
                break;
            }
        }
        unset($base_data);

        Transformers::make()->arrayToFile($bases_data, $dump_path);
    }

    private function updateBaseModelSchema(Base $base, string $new_schema): void
    {
        $base->schema = $new_schema;
        $base->updated_at = now()->toDateTimeString();
        $base->save();
    }

    private function restoreRecords(Base $base, array $records): void
    {
        $sqlite = Sqlite::connect($base->getBasePath());
        if (empty($records)) {
            return;
        }

        foreach ($records as $record) {
            $sqlite->query('records')->insert([
                'uid' => $record['uid'],
                'order_id' => $record['order_id'],
                'request_data' => $record['request_data'],
                'created_at' => $record['created_at'],
                'is_primary' => intval($record['is_primary'] ?? 0),
            ]);
        }
    }

    private function makeSchemaWithUniqueUid(): string
    {
        return <<<'PHP'
<?php

$sqlite->createTable('records', function($table) {
    $table->string('uid')->unique();
    $table->string('order_id');
    $table->text('request_data');
    $table->timestamp('created_at');
    $table->smallInteger('is_primary')->default(0);
});
PHP;
    }
}
