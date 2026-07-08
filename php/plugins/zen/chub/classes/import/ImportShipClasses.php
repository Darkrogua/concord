<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Models\ShipClass;
use Zen\Chub\Console\RefreshCommand;

class ImportShipClasses
{
    public static function make(): self
    {
        return new self();
    }

    # dp: Zen.Chub.Classes.Import.ImportShipClasses.handle
    public function handle()
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_classes.php');
        MysqlConnection::make()->azimut74('mcmraak_rivercrs_ship_statuses')
            ->get()
            ->map(function ($record) {
                ShipClass::create([
                    'name' => $record->name,
                    'description' => $record->desc,
                ]);
            });
    }
}