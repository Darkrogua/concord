<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\System\ProcessApp;

class ImportAllData
{
    public static function make(): self
    {
        return new self();
    }

    # http://axis/chub.api/Dev.ImportDevelop:import
    public function handle()
    {
        return;
        ImportShipClasses::make()->handle();
        ImportShipSpecifications::make()->handle();
        ImportShipAmenity::make()->handle();
        ImportShips::make()->handle();
        ImportShipPivots::make()->handle();
        ImportDecks::make()->handle();
        ImportCabinEquipments::make()->handle();
        ImportGrades::make()->handle();
        ImportCabins::make()->handle();
        ImportGeoObjects::make()->handle();

        # ImportShipImages::make()->handle(); # Это только в консоли
        # php artisan chub:patch ImportShipImages
    }

    # dp: Zen.Chub.Classes.Import.ImportAllData.importOrchestrator
    public function importOrchestrator(): array
    {
        return [
            'ImportShipClasses',        # Перенос классов теплоходов
            'ImportShipSpecifications', # Перенос технических характеристик
            'ImportShipAmenity',        # Перенос "На борту имеется" (ShipAmenity)
            'ImportShips',              # Импорт теплоходов (Ships)
            'ImportShipPivots',         # Сводные данные (Amenities+Specifications)
            'ImportDecks',              # Импорт палуб
            'ImportCabinEquipments',    # Перенос - CabinEquipment (Оборудование каюты)
            'ImportGrades',             # Перенос категорий кают (Grades)
            'ImportCabins',             # Перенос кают (Cabin)
            'ImportGeoObjects',         # Перенос гео-объектов
        ];
    }

    # dp: Zen.Chub.Classes.Import.ImportAllData.run
    public function run(string|array|null $value = null): void
    {
        $process_code = is_array($value) ? (string) ($value['code'] ?? '') : (string) $value;
        $process_code = trim($process_code);
        if ($process_code === '') {
            return;
        }

        $process_app = ProcessApp::make();
        $process_app->runScheduleProcess($process_code);
        $process_app->waitForStreamCompletion($process_code);
    }
}