<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Console\RefreshCommand;
use Zen\Chub\Models\Cabin;

class ImportCabins
{
    public static function make(): self
    {
        return new self();
    }

    public function handle()
    {
        $records = $this->createBatches();
        foreach ($records as $record) {
            $this->handleBatch($record);
        }
    }

    # Zen.Chub.Classes.Import.ImportCabins.createBatches
    public function createBatches()
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_cabins.php');
        $batches = [];
        MysqlConnection::make()->azimut74('mcmraak_rivercrs_motorships')
            ->get()
            ->map(function ($record) use (&$batches) {
                $data = Transformers::make()->fromJson($record->exist_rooms);

                if (!$data) {
                    return;
                }

                $batches[] = $data;
            });
        return $batches;
    }

    # Zen.Chub.Classes.Import.ImportCabins.handleBatch
    public function handleBatch(array $data)
    {
        foreach($data as $item) {
            $cabin_name = $item['n'];
            $grade_id = ComparisonsApp::make()->getCurrentId('Grade', $item['c']);

            $pivot_record = MysqlConnection::make()->azimut74('mcmraak_rivercrs_decks_pivot')
            ->where('cabin_id', $item['c'])
            ->first();

            if (!$pivot_record) {
                continue;
            }

            $deck_id = $pivot_record->deck_id;
            $deck_id = ComparisonsApp::make()->getCurrentId('Deck', $deck_id);

            if (!$grade_id || !$deck_id) {
                continue;
            }

            Cabin::create([
                'name' => $cabin_name,
                'grade_id' => $grade_id,
                'deck_id' => $deck_id
            ]);
            
        }
    }
}