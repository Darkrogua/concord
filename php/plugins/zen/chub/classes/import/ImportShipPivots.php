<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Models\ShipAmenity;
use Zen\Chub\Models\ShipSpecification;
use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Models\Ship;
use DB;
use Zen\Chub\Console\RefreshCommand;

class ImportShipPivots
{
    public static function make(): self
    {
        return new self();
    }

    # dp: Zen.Chub.Classes.Import.ImportShipPivots.handle
    public function handle()
    {
        $this->importAmenities();
        $this->importSpecifications();
    }

    public function importAmenities() {

        RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_amenity_pivot.php');

        $records = MysqlConnection::make()->azimut74('mcmraak_rivercrs_onboard_pivot')->get();
        $grouped = $records->groupBy('motorship_id');

        foreach ($grouped as $source_ship_id => $ship_records) {
            $ship_id = ComparisonsApp::make()->getCurrentId('Ship', $source_ship_id);

            $ship = Ship::find($ship_id);

            foreach ($ship_records as $sort_order => $record) {
                $amenity_id = ComparisonsApp::make()->getCurrentId('ShipAmenity', $record->onboard_id);
                $amenity = ShipAmenity::find($amenity_id);

                $ship->amenities()
                    ->syncWithoutDetaching([$amenity->id => ['sort_order' => $sort_order]]);
            }
        }
    }

    public function importSpecifications(): void
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_specification_pivot.php');

        $records = MysqlConnection::make()->azimut74('mcmraak_rivercrs_techs_pivot')->get();
        $grouped = $records->groupBy('motorship_id');

        foreach ($grouped as $source_ship_id => $ship_records) {
            $ship_id = ComparisonsApp::make()->getCurrentId('Ship', $source_ship_id);
            $ship = Ship::find($ship_id);

            foreach ($ship_records as $sort_order => $record) {
                $specification_id = ComparisonsApp::make()->getCurrentId('ShipSpecification', $record->tech_id);
                $specification = ShipSpecification::find($specification_id);

                $ship->specifications()->syncWithoutDetaching([
                    $specification->id => [
                        'value' => $record->value ?? '',
                        'sort_order' => $sort_order,
                    ],
                ]);
            }
        }
    }
}