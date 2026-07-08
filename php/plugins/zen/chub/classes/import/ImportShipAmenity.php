<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Models\ShipAmenity;
use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Console\RefreshCommand;

class ImportShipAmenity
{
    public static function make(): self
    {
        return new self();
    }

    # dp: Zen.Chub.Classes.Import.ImportShipAmenity.handle
    public function handle()
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_amenities.php');
        MysqlConnection::make()->azimut74('mcmraak_rivercrs_onboard')
            ->get()
            ->map(function ($record) {
                $current_id = ShipAmenity::create([
                    'name' => $record->name
                ])->id;

                ComparisonsApp::make()->add(
                    model_name: 'ShipAmenity',
                    source_id: $record->id,
                    current_id: $current_id
                );
            });
    }

}