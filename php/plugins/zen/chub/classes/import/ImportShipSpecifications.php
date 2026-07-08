<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Models\ShipSpecification;
use Zen\Chub\Classes\Import\ComparisonsApp;
use Zen\Chub\Console\RefreshCommand;

class ImportShipSpecifications
{
    public static function make(): self
    {
        return new self();
    }

    # dp: Zen.Chub.Classes.Import.ImportShipSpecifications.handle
    public function handle()
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_specifications.php');
        MysqlConnection::make()->azimut74('mcmraak_rivercrs_techs')
            ->get()
            ->map(function ($record) {
                $current_id = ShipSpecification::create([
                    'name' => $record->name
                ])->id;

                ComparisonsApp::make()->add(
                    model_name: 'ShipSpecification',
                    source_id: $record->id,
                    current_id: $current_id
                );
            });
    }
}