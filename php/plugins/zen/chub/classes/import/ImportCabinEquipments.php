<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Models\CabinEquipment;
use Zen\Chub\Classes\Import\ComparisonsApp;
use Zen\Chub\Console\RefreshCommand;

class ImportCabinEquipments
{
    public static function make(): self
    {
        return new self();
    }

    # dp: Zen.Chub.Classes.Import.ImportCabinEquipments.handle
    public function handle()
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_cabin_equipments.php');

        $records = MysqlConnection::make()->azimut74('mcmraak_rivercrs_incabin')->get();

        foreach ($records as $record) {
            $current_id = CabinEquipment::create([
                'name' => $record->name,
            ])->id;

            ComparisonsApp::make()->add(
                model_name: 'CabinEquipment',
                source_id: $record->id,
                current_id: $current_id
            );
        }
    }
}