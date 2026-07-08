<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Models\Grade;
use Zen\Chub\Models\GradeAlias;
use Zen\Chub\Classes\Import\ComparisonsApp;
use Zen\Chub\Classes\Enums\Tiering;
use Zen\Chub\Console\RefreshCommand;
use Zen\Chub\Models\Provider;

class ImportGrades
{
    public static function make(): self
    {
        return new self();
    }

    # Zen.Chub.Classes.Import.ImportGrades.handle
    public function handle(): void
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_grade_aliases.php');
        RefreshCommand::restartMigration('builder_table_create_zen_chub_grades.php');

        $records = MysqlConnection::make()
            ->azimut74('mcmraak_rivercrs_cabins')
            ->orderBy('order')
            ->get();
        
        foreach ($records as $record) {
            $current_id = Grade::create([
                'name' => $record->category,
                'ship_id' => ComparisonsApp::make()->getCurrentId('Ship', $record->motorship_id),
                'places_main_qnt' => $record->places_main_count,
                'places_extra_qnt' => $record->places_extra_count,
                'rooms_count' => $record->rooms_count,
                'tiering' => $record->bed_id === 1 ? Tiering::TIERED : Tiering::FLAT,
                'area_meters' => $record->space,
            ])->id;

            ComparisonsApp::make()->add(
                model_name: 'Grade',
                source_id: $record->id,
                current_id: $current_id
            );

            $eds_codes = [
                'waterway',
                'infoflot',
                'germes',
                'gama',
                'volga',
            ];

            foreach ($eds_codes as $eds_code) {
                if ($record->{$eds_code . '_id'}) {
                    $provider_id = Provider::legacyToCurrent($eds_code);
                    $source_uid = $record->{$eds_code . '_id'};
                    $source_name = $record->{$eds_code . '_name'};
                    GradeAlias::addAlias($current_id, $provider_id, $source_uid, $source_name);
                }
            }
        }
    }
}