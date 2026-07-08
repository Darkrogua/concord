<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Dto\CheckinDto;
use Zen\Chub\Models\Provider;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Classes\Parsers\Volga\VolgaParser;

class CheckinDtoDevelop
{
    # http://axis/chub.api/Dev.CheckinDtoDevelop:test
    public function test()
    {
        $checkin_dto = CheckinDto::make(
            provider_code: 'volga',
            provider_checkin_id: 18423,
            date_start: '2026-05-15 18:00:00',
            date_end: '2026-05-18 09:00:00',
        );

        # Добавление корабля
        $checkin_dto->resolveShip(
            provider_ship_name: 'ПАВЕЛ БАЖОВ',
            provider_ship_id: 4,
        );

        # Добавление точек маршрута
        $checkin_dto->addWaybillPoint('Саратов');
        $checkin_dto->addWaybillPoint('Москва');
        $checkin_dto->addWaybillPoint('Тамбов');
        $checkin_dto->addWaybillPoint('Москва');
        $checkin_dto->addWaybillPoint('Саратов');

        # Добавление цен с резолвингом сущностей
        #
        # 1. Категория каюты (резолвинг)
        # 2. Палуба (резолвинг)
        # 3. Местность - places_value (int)
        # 4. Тип цены - tariff_id (enum)
        # 5. Валюта - price_currency (enum)
        # 6. Значение цены - price_value (decimal(10.2))

        
    }

    # http://axis/chub.api/Dev.CheckinDtoDevelop:realParserVolga
    public function realParserVolga()
    {
        VolgaParser::make()->prodTransit([
            'cruise_id' => 1709,
            'date_start' => "2026-05-29 14:00:00",
            'date_end' => "2026-06-03 16:30:00",
            'ship_id' => 2,
        ]);
    }

    # http://axis/chub.api/Dev.CheckinDtoDevelop:removeBatchRecords
    # Схема records: uid (unique), order_id, request_data, created_at, is_primary — без id.
    public function removeBatchRecords()
    {
        $app = BasesApp::connect('uon-payments');

        $uids = $app->query('records')
            ->orderByDesc('created_at')
            ->limit(3)
            ->pluck('uid')
            ->all();

        if ($uids === []) {
            return 0;
        }

        return $app->query('records')->whereIn('uid', $uids)->delete();
    }
}