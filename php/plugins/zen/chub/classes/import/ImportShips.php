<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Models\Ship;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Models\ShipInclusion;
use Zen\Chub\Models\ShipSurcharge;
use DB;
use Zen\Chub\Console\RefreshCommand;

class ImportShips
{
    public static function make(): self
    {
        return new self();
    }

    # dp: Zen.Chub.Classes.Import.ImportShips.handle
    public function handle()
    {
        # Перезапустить экземпляры и связи для "В стоимость входит"
        RefreshCommand::restartMigration('builder_table_create_zen_chub_inclusions.php');
        RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_inclusion_pivot.php');

        # Перезапустить экземпляры и связи для "Дополнительно оплачивается"
        RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_surcharge.php');
        RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_surcharge_pivot.php');
        
        RefreshCommand::restartMigration('builder_table_create_zen_chub_ships.php');
        BasesApp::connect('azimut74-ships')->clear();

        MysqlConnection::make()->azimut74('mcmraak_rivercrs_motorships')
            ->get()
            ->map(function ($record) {

                $insert_data = (array) $record;
                unset($insert_data['id']);

                BasesApp::connect('azimut74-ships')
                    ->query()
                    ->insert($insert_data);

                $current_id = Ship::create([
                    'name' => $record->name,
                    'description' => $record->desc,
                    'meta_title' => $record->metatitle,
                    'meta_description' => $record->metadesc,
                    'ship_class_id' => ($record->status_id) ? $record->status_id : 5,
                ])->id;

                $this->handleInclusions($record, $current_id);
                $this->handleSurcharges($record, $current_id);

                ComparisonsApp::make()->add(
                    model_name: 'Ship',
                    source_id: $record->id,
                    current_id: $current_id
                );
            });

    }

    private function handleSurcharges(object $record, int $current_id)
    {
        $field = $record->add_b;
        preg_match_all('/<li>(.*?)<\/li>/s', $field, $matches);
        $lines = array_map('trim', $matches[1]);
        $lines = array_map(function($record) {
            return preg_replace('/[,.;]$/', '', $record);
        }, $lines);

        foreach ($lines as $sort_order => $name) {
            $this->addSurcharge(
                $name,
                $current_id,
                $sort_order
            );
        }
    }

    private function addSurcharge(
        string $name,
        int $ship_id,
        int $sort_order = 0
    ): void {
        $surcharge = ShipSurcharge::firstOrCreate(['name' => $name]);
        Ship::find($ship_id)
            ->surcharges()
            ->syncWithoutDetaching([$surcharge->id => ['sort_order' => $sort_order]]);
    }

    private function handleInclusions(object $record, int $current_id)
    {
        $field = $record->add_a;
        preg_match_all('/<li>(.*?)<\/li>/s', $field, $matches);
        $lines = array_map('trim', $matches[1]);
        $lines = array_map(function($record) {
            return preg_replace('/[,.;]$/', '', $record);
        }, $lines);

        foreach ($lines as $sort_order => $name) {
            $this->addInclusion(
                $name,
                $current_id,
                $sort_order
            );
        }
    }

    private function addInclusion(
        string $name,
        int $ship_id,
        int $sort_order = 0
    ): void {
        $inclusion = ShipInclusion::firstOrCreate(['name' => $name]);
        Ship::find($ship_id)
            ->inclusions()
            ->syncWithoutDetaching([$inclusion->id => ['sort_order' => $sort_order]]);
    }
}