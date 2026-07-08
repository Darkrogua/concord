<?php namespace Zen\Chub\Classes\Import;

use SplFileObject;
use Zen\Chub\Classes\Enums\GeoObjectType;
use Zen\Chub\Classes\Business\GeoApp;
use Zen\Chub\Console\RefreshCommand;
use Zen\Chub\Models\GeoObject;

class ImportGeoObjects
{
    private const CSV_PATH = 'plugins/zen/chub/resources/geo/city_db.csv';
    private const SOURCE_CODE = 'city_db';

    /**
     * Локальный кэш для снижения количества запросов.
     * Ключ: object_type|parent_id|normalized_name
     */
    private array $geo_cache = [];

    public static function make(): self
    {
        return new self();
    }

    # dp: Zen.Chub.Classes.Import.ImportGeoObjects.handle
    public function handle(): void
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_geo_aliases.php');
        RefreshCommand::restartMigration('builder_table_create_zen_chub_geo_objects.php');

        $csv_path = base_path(self::CSV_PATH);
        if (!is_file($csv_path)) {
            $this->log("Файл не найден: {$csv_path}");
            return;
        }

        $reader = new SplFileObject($csv_path, 'r');
        $reader->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $reader->setCsvControl(',');

        $row_number = 0;
        $processed_rows = 0;

        foreach ($reader as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row_number++;

            // Первая строка — заголовок.
            if ($row_number === 1) {
                continue;
            }

            if ($this->isRowEmpty($row)) {
                continue;
            }

            $this->handleRecord($row);
            $processed_rows++;
        }

        $total_geo = GeoObject::count();
        $this->log("Импорт завершен. Строк обработано: {$processed_rows}. Гео-объектов: {$total_geo}.");
    }

    public function run(): void
    {
        $this->handle();
    }

    private function handleRecord(array $row): void
    {
        $postal_code = $this->cell($row, 1);
        $country = $this->cell($row, 2);
        $region = $this->cell($row, 5);
        $area = $this->cell($row, 7);
        $city = $this->cell($row, 9);
        $kladr_id = $this->cell($row, 12);
        $fias_id = $this->cell($row, 13);
        $timezone = $this->cell($row, 19);
        $lat = $this->floatCell($row, 20);
        $lon = $this->floatCell($row, 21);
        $population = $this->intCell($row, 22);

        $data = array_filter([
            'postal_code' => $postal_code,
            'kladr_id' => $kladr_id,
            'fias_id' => $fias_id,
            'population' => $population,
            'timezone' => $timezone,
        ], fn ($value) => $value !== null && $value !== '');

        $country_id = $this->handleGeoObject(
            name: $country,
            object_type: GeoObjectType::COUNTRY->value,
            lat: $lat,
            lon: $lon,
            data: $data
        );

        $region_id = $this->handleGeoObject(
            name: $region,
            object_type: GeoObjectType::REGION->value,
            parent_id: $country_id,
            lat: $lat,
            lon: $lon,
            data: $data
        );

        // В текущем enum нет отдельного "area", используем "place" как промежуточный уровень.
        $area_id = $this->handleGeoObject(
            name: $area,
            object_type: GeoObjectType::PLACE->value,
            parent_id: $region_id ?? $country_id,
            lat: $lat,
            lon: $lon,
            data: $data
        );

        $city_id = $this->handleGeoObject(
            name: $city,
            object_type: GeoObjectType::CITY->value,
            parent_id: $area_id ?? $region_id ?? $country_id,
            lat: $lat,
            lon: $lon,
            data: $data
        );

        if ($city_id && $kladr_id) {
            GeoApp::make()->attachAlias(
                geo_object_id: $city_id,
                source_code: self::SOURCE_CODE,
                source_id: $kladr_id,
                source_name: $city ?: null
            );
        }

        if ($city_id && $fias_id) {
            GeoApp::make()->attachAlias(
                geo_object_id: $city_id,
                source_code: self::SOURCE_CODE,
                source_id: $fias_id,
                source_name: $city ?: null
            );
        }
    }

    private function handleGeoObject(
        ?string $name,
        string $object_type,
        ?int $parent_id = null,
        ?float $lat = null,
        ?float $lon = null,
        array $data = []
    ): ?int {
        $name = $this->normalizeName($name);
        if (!$name) {
            return null;
        }

        $cache_key = $this->cacheKey($object_type, $parent_id, $name);
        if (isset($this->geo_cache[$cache_key])) {
            return $this->geo_cache[$cache_key];
        }

        $payload = [
            'name' => $name,
            'object_type' => $object_type,
            'parent_id' => $parent_id,
            'active' => 1,
        ];

        if ($lat !== null && $lon !== null) {
            $payload['lat'] = $lat;
            $payload['lon'] = $lon;
        }

        if ($data) {
            $payload['data'] = $data;
        }

        $geo = GeoApp::make()->resolveOrCreate($payload);
        $this->geo_cache[$cache_key] = (int) $geo->id;

        return (int) $geo->id;
    }

    private function cacheKey(string $object_type, ?int $parent_id, string $name): string
    {
        $parent = $parent_id ?? 0;
        $normalized = mb_strtolower(trim($name));

        return "{$object_type}|{$parent}|{$normalized}";
    }

    private function normalizeName(?string $value): ?string
    {
        $name = trim((string) $value);

        return $name === '' ? null : $name;
    }

    private function cell(array $row, int $index): ?string
    {
        if (!array_key_exists($index, $row)) {
            return null;
        }

        $value = trim((string) $row[$index]);

        return $value === '' ? null : $value;
    }

    private function floatCell(array $row, int $index): ?float
    {
        $value = $this->cell($row, $index);
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function intCell(array $row, int $index): ?int
    {
        $value = $this->cell($row, $index);
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function log(string $message): void
    {
        echo '[ImportGeoObjects] ' . $message . PHP_EOL;
    }
}
