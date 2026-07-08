<?php namespace Zen\Chub\Dto;

use Zen\Chub\Models\Ship;
use Zen\Chub\Models\ShipAlias;
use Zen\Chub\Models\Provider;
use Zen\Chub\Models\Grade;
use Zen\Chub\Models\GradeAlias;
use Zen\Chub\Models\Deck;
use Zen\Chub\Models\DeckAlias;
use Zen\Chub\Exceptions\AllowedDtoException;
use Zen\Chub\Classes\Business\GeoApp;
use Zen\Chub\Classes\Enums\CheckinPointType;
use Zen\Chub\Models\GeoObject;
use Zen\Chub\Models\Checkin;
use DB;

class CheckinDto
{
    private string $provider_code;
    private int $provider_id;
    private int $provider_checkin_id;
    private string $date_start;
    private string $date_end;
    private array $waybill;
    private array $geo_objects_cache = [];

    private array $provider_ship = [
        'id' => null,
        'name' => null,
    ];

    private array $ship = [
        'id' => null,
        'name' => null,
    ];

    private array $grades = []; # Кеш добавленных категорий кают
    private array $decks = []; # Кеш добавленных палуб
    private array $prices = []; # Коллекция цен

    # Создание DTO c базовыми данными (Эти данные должны быть введены в начале)
    public static function make(
        string $provider_code,
        int $provider_checkin_id,
        string $date_start,
        string $date_end,
    ): self {
        $dto = new self();
        $dto->provider_code = $provider_code;
        $dto->provider_id = $dto->definePrividerId($provider_code);
        $dto->provider_checkin_id = $provider_checkin_id;
        $dto->date_start = trim($date_start);
        $dto->date_end = trim($date_end);
        $dto->waybill = [];
        return $dto;
    }

    private function definePrividerId(string $provider_code): int
    {
        return Provider::legacyToCurrent($provider_code);
    }

    public function resolveShip(string $provider_ship_name, int $provider_ship_id)
    {
        if ($this->provider_ship['id']) {
            return;
        }

        // Проверить алиас, убедится что теплоход не в исключениях, иначе выкинуть исключение
        $this->provider_ship['id'] = $provider_ship_id;
        $this->provider_ship['name'] = $provider_ship_name;
        $alias = $this->checkAlias();

        if (!$alias) {
            $ship = $this->findShipByProviderName($provider_ship_name);
            if (!$ship) {
                $ship = Ship::create([
                    'name' => $provider_ship_name,
                    'ship_class_id' => 1,
                ]);
            }

            ShipAlias::create([
                'provider_id' => $this->provider_id,
                'target_id' => $ship->id,
                'source_name' => $provider_ship_name,
                'source_uid' => $provider_ship_id
            ]);

        } else {
            $ship = Ship::find(intval($alias->target_id));
        }
        $this->ship['id'] = intval($ship->id);
        $this->ship['name'] = (string) $ship->name;
    }

    private function checkAlias(): ?object
    {
        $alias = ShipAlias::where('provider_id', $this->provider_id)
            ->where('source_uid', $this->provider_ship['id'])
            ->first();
        
        if ($alias) {
            if (!$alias->active) {
                throw new AllowedDtoException(
                    'Теплоход помечен как неактивный',
                    [
                        'provider_code' => $this->provider_code,
                        'provider_id' => $this->provider_id,
                        'provider_ship_id' => $this->provider_ship['id'],
                        'provider_ship_name' => $this->provider_ship['name'],
                        'provider_checkin_id' => $this->provider_checkin_id,
                    ]
                );
            }
        }

        return $alias;
    }

    private function findShipByProviderName(string $provider_ship_name): ?Ship
    {
        $normalized_name = $this->normalizeShipName($provider_ship_name);
        if ($normalized_name === '') {
            return null;
        }

        $ship = Ship::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%' . $normalized_name . '%'])
            ->first();
        if ($ship) {
            return $ship;
        }

        $tokens = preg_split('/\s+/u', $normalized_name) ?: [];
        if (count($tokens) >= 2) {
            $ship = Ship::query()
                ->whereRaw('LOWER(name) LIKE ?', ['%' . $tokens[0] . '%'])
                ->whereRaw('LOWER(name) LIKE ?', ['%' . $tokens[1] . '%'])
                ->first();
            if ($ship) {
                return $ship;
            }
        }

        return null;
    }

    private function normalizeShipName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['"', '«', '»'], '', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    public function addWaybillPoint(
        string $town_name, # Название города
        bool $is_important = false, # Важная точка
        ?string $point_type = null, # Тип точки (departure/arrival/transit/important)
        ?string $arrival_at = null, # Дата/время прибытия
        ?string $departure_at = null, # Дата/время отправления
        ?string $note = null, # Примечание по точке
        ?bool $is_highlight = null, # Явная подсветка (приоритетнее is_important)
    ) {
        $geo_object = $this->getGeoObject($town_name);
        $this->waybill[] = [
            'geo_object_id' => $geo_object->id,
            'is_important' => $is_important,
            'point_type' => $point_type,
            'arrival_at' => $arrival_at,
            'departure_at' => $departure_at,
            'note' => $note,
            'is_highlight' => $is_highlight,
        ];
    }

    private function getGeoObject($town_name): GeoObject
    {
        if ($this->geo_objects_cache[$town_name] ?? null) {
            return $this->geo_objects_cache[$town_name];
        }
        $geo_object = GeoApp::make()->resolveTownByNameOrCreate($town_name);
        $this->geo_objects_cache[$town_name] = $geo_object;
        return $geo_object;
    }

    public function resolvePrice(
        string $provider_grade_name, # Название категории каюты от провайдера (внешнего источника)
        string $provider_grade_uid, # Идентификатор категории каюты от провайдера
        int $places_main_qnt, # Количество основных мест
        int $places_extra_qnt, # Количество дополнительных мест
        ?string $provider_deck_name, # Имя палубы провайдера
        ?string $provider_deck_uid, # Идентификатор палубы провайдера
        int $places_value, # Местность каюты
        int $price_value, # Цена
        int $tariff_id = 1, # Тариф
        string $price_currency = 'RUB', # Валюта цены
    ) {
        $grade_id = $this->resolveGrade(
            provider_grade_name: $provider_grade_name,
            provider_grade_uid: $provider_grade_uid,
            places_main_qnt: $places_main_qnt,
            places_extra_qnt: $places_extra_qnt
        );

        if ($provider_deck_uid) {
            $deck_id = $this->resolveDeck(
                provider_deck_name: $provider_deck_name,
                provider_deck_uid: $provider_deck_uid,
            );
        } else {
            $deck_id = 1;
        }

        $price_record = [
            'grade_id' => $grade_id,
            'deck_id' => $deck_id,
            'places_value' => $places_value,
            'price_value' => $price_value,
            'tariff_id' => $tariff_id,
            'price_currency' => $price_currency
        ];

        $key = join('.', [
            $grade_id,
            $deck_id,
            $places_value,
            $tariff_id,
            $price_currency
        ]);

        if (!isset($this->prices[$key])) {
            $this->prices[$key] = $price_record;
        }
    }

    private function resolveGrade(
        string $provider_grade_name,
        string $provider_grade_uid,
        int $places_main_qnt,
        int $places_extra_qnt,
    ): int {
        $provider_grade_uid = trim($provider_grade_uid);
        if ($provider_grade_uid === '') {
            throw new AllowedDtoException(
                'Пустой идентификатор категории каюты провайдера',
                [
                    'provider_code' => $this->provider_code,
                    'provider_id' => $this->provider_id,
                    'provider_checkin_id' => $this->provider_checkin_id,
                    'provider_grade_name' => $provider_grade_name,
                ]
            );
        }

        if (!$this->ship['id']) {
            throw new AllowedDtoException(
                'Нельзя резолвить категорию каюты без теплохода',
                [
                    'provider_code' => $this->provider_code,
                    'provider_id' => $this->provider_id,
                    'provider_checkin_id' => $this->provider_checkin_id,
                    'provider_grade_name' => $provider_grade_name,
                    'provider_grade_uid' => $provider_grade_uid,
                ]
            );
        }

        if (isset($this->grades[$provider_grade_uid])) {
            return intval($this->grades[$provider_grade_uid]['id'] ?? 0);
        }

        $alias = $this->checkGradeAlias($provider_grade_uid, $provider_grade_name);
        if ($alias) {
            $grade = Grade::find(intval($alias->target_id));
        } else {
            $grade = $this->findGradeByProviderData(
                provider_grade_name: $provider_grade_name,
                places_main_qnt: $places_main_qnt,
                places_extra_qnt: $places_extra_qnt
            );

            if (!$grade) {
                $grade = Grade::create([
                    'name' => $provider_grade_name,
                    'ship_id' => intval($this->ship['id']),
                    'places_main_qnt' => max(0, $places_main_qnt),
                    'places_extra_qnt' => max(0, $places_extra_qnt),
                    'active' => 1,
                ]);
            }

            GradeAlias::addAlias(
                target_id: intval($grade->id),
                provider_id: $this->provider_id,
                source_uid: $provider_grade_uid,
                source_name: $provider_grade_name,
                data: [
                    'ship_id' => intval($this->ship['id']),
                    'ship_name' => (string) $this->ship['name'],
                    'places_main_qnt' => $places_main_qnt,
                    'places_extra_qnt' => $places_extra_qnt,
                ]
            );
        }

        if (!$grade) {
            throw new AllowedDtoException(
                'Не удалось сопоставить категорию каюты',
                [
                    'provider_code' => $this->provider_code,
                    'provider_id' => $this->provider_id,
                    'provider_checkin_id' => $this->provider_checkin_id,
                    'provider_grade_name' => $provider_grade_name,
                    'provider_grade_uid' => $provider_grade_uid,
                    'ship_id' => intval($this->ship['id']),
                ]
            );
        }

        $this->grades[$provider_grade_uid] = [
            'id' => intval($grade->id),
            'name' => (string) $grade->name,
            'places_main_qnt' => intval($grade->places_main_qnt ?? 0),
            'places_extra_qnt' => intval($grade->places_extra_qnt ?? 0),
        ];

        return intval($grade->id);
    }

    private function checkGradeAlias(
        string $provider_grade_uid,
        string $provider_grade_name
    ): ?GradeAlias {
        $alias = GradeAlias::where('provider_id', $this->provider_id)
            ->where('source_uid', $provider_grade_uid)
            ->whereHas('grade', function($query) {
                $query->where('ship_id', intval($this->ship['id']));
            })
            ->first();

        if ($alias && !$alias->active) {
            throw new AllowedDtoException(
                'Категория каюты помечена как неактивная',
                [
                    'provider_code' => $this->provider_code,
                    'provider_id' => $this->provider_id,
                    'provider_checkin_id' => $this->provider_checkin_id,
                    'provider_grade_name' => $provider_grade_name,
                    'provider_grade_uid' => $provider_grade_uid,
                    'ship_id' => intval($this->ship['id']),
                ]
            );
        }

        return $alias;
    }

    private function findGradeByProviderData(
        string $provider_grade_name,
        int $places_main_qnt,
        int $places_extra_qnt
    ): ?Grade {
        $normalized_name = $this->normalizeShipName($provider_grade_name);
        $query = Grade::query()
            ->where('ship_id', intval($this->ship['id']));

        if ($normalized_name !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . $normalized_name . '%']);
        }

        $grade = $query
            ->where('places_main_qnt', max(0, $places_main_qnt))
            ->where('places_extra_qnt', max(0, $places_extra_qnt))
            ->first();
        if ($grade) {
            return $grade;
        }

        if ($normalized_name !== '') {
            return Grade::query()
                ->where('ship_id', intval($this->ship['id']))
                ->whereRaw('LOWER(name) LIKE ?', ['%' . $normalized_name . '%'])
                ->first();
        }

        return null;
    }

    public function resolveDeck(
        ?string $provider_deck_name = null,
        ?string $provider_deck_uid = null,
    ): int {
        $provider_deck_uid = trim((string) $provider_deck_uid);
        $provider_deck_name = trim((string) $provider_deck_name);

        if ($provider_deck_uid === '' && $provider_deck_name === '') {
            return $this->resolveDefaultDeckId();
        }

        $cache_key = $provider_deck_uid !== ''
            ? 'uid:' . $provider_deck_uid
            : 'name:' . $this->normalizeShipName($provider_deck_name);
        if (isset($this->decks[$cache_key])) {
            return intval($this->decks[$cache_key]['id'] ?? 0);
        }

        $alias = $this->checkDeckAlias($provider_deck_uid, $provider_deck_name);
        if ($alias) {
            $deck = Deck::find(intval($alias->target_id));
        } else {
            $deck = $this->findDeckByProviderName($provider_deck_name);
            if (!$deck) {
                $deck_name = $provider_deck_name !== '' ? $provider_deck_name : 'Палуба без имени';
                $deck = Deck::create([
                    'name' => $deck_name,
                    'active' => 1,
                ]);
            }

            if ($provider_deck_uid !== '') {
                DeckAlias::create([
                    'provider_id' => $this->provider_id,
                    'target_id' => intval($deck->id),
                    'source_name' => $provider_deck_name,
                    'source_uid' => $provider_deck_uid,
                    'data' => json_encode([
                        'provider_code' => $this->provider_code,
                        'ship_id' => intval($this->ship['id'] ?? 0),
                        'ship_name' => (string) ($this->ship['name'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE),
                    'active' => 1,
                ]);
            }
        }

        if (!$deck) {
            throw new AllowedDtoException(
                'Не удалось сопоставить палубу',
                [
                    'provider_code' => $this->provider_code,
                    'provider_id' => $this->provider_id,
                    'provider_checkin_id' => $this->provider_checkin_id,
                    'provider_deck_name' => $provider_deck_name,
                    'provider_deck_uid' => $provider_deck_uid,
                ]
            );
        }

        $this->decks[$cache_key] = [
            'id' => intval($deck->id),
            'name' => (string) $deck->name,
        ];

        return intval($deck->id);
    }

    private function checkDeckAlias(
        ?string $provider_deck_uid = null,
        ?string $provider_deck_name = null
    ): ?DeckAlias {
        $provider_deck_uid = trim((string) $provider_deck_uid);
        $provider_deck_name = trim((string) $provider_deck_name);

        if ($provider_deck_uid === '') {
            return null;
        }

        $alias = DeckAlias::where('provider_id', $this->provider_id)
            ->where('source_uid', $provider_deck_uid)
            ->first();

        if ($alias && !$alias->active) {
            throw new AllowedDtoException(
                'Палуба помечена как неактивная',
                [
                    'provider_code' => $this->provider_code,
                    'provider_id' => $this->provider_id,
                    'provider_checkin_id' => $this->provider_checkin_id,
                    'provider_deck_name' => $provider_deck_name,
                    'provider_deck_uid' => $provider_deck_uid,
                ]
            );
        }

        return $alias;
    }

    private function findDeckByProviderName(?string $provider_deck_name = null): ?Deck
    {
        $normalized_name = $this->normalizeShipName((string) $provider_deck_name);
        if ($normalized_name === '') {
            return null;
        }

        return Deck::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%' . $normalized_name . '%'])
            ->first();
    }

    private function resolveDefaultDeckId(): int
    {
        $cache_key = 'default:any';
        if (isset($this->decks[$cache_key])) {
            return intval($this->decks[$cache_key]['id'] ?? 0);
        }

        $deck = Deck::query()->where('name', 'Любая')->first();
        if (!$deck) {
            $deck = Deck::create([
                'name' => 'Любая',
                'description' => 'Системная палуба для цен без привязки к конкретной палубе',
                'active' => 1,
            ]);
        }

        $this->decks[$cache_key] = [
            'id' => intval($deck->id),
            'name' => (string) $deck->name,
        ];

        return intval($deck->id);
    }

    public function push()
    {
        $checkin = Checkin::where('provider_id', $this->provider_id)
            ->where('source_id', $this->provider_checkin_id)
            ->first();

        if (!$checkin) {
            $checkin = Checkin::create([
                'provider_id' => $this->provider_id,
                'source_id' => $this->provider_checkin_id,
                'ship_id' => $this->ship['id'],
                'date_start' => $this->date_start,
                'date_end' => $this->date_end,
            ]);
        } else {
            $checkin->fill([
                'ship_id' => $this->ship['id'],
                'date_start' => $this->date_start,
                'date_end' => $this->date_end,
            ]);
        }

        $checkin->route_points_repeater = $this->buildRoutePointsRepeaterPayload();
        $checkin->save();

        DB::table('zen_chub_prices')
           ->where('checkin_id', $checkin->id)
           ->delete();

        $prices_batch = [];
        $now = now()->toDateTimeString();
        foreach ($this->prices as $price_record) {
            $prices_batch[] = [
                'provider_id' => $this->provider_id,
                'checkin_id' => $checkin->id,
                'grade_id' => $price_record['grade_id'],
                'deck_id' => $price_record['deck_id'],
                'tariff_id' => $price_record['tariff_id'],
                'places_value' => $price_record['places_value'],
                'price_currency' => $price_record['price_currency'],
                'price_value' => $price_record['price_value'],
                'created_at' => $now
            ];
        }

        DB::table('zen_chub_prices')->insert($prices_batch);

        return $checkin;
    }

    private function buildRoutePointsRepeaterPayload(): array
    {
        $items = [];
        $last_index = max(0, count($this->waybill) - 1);

        foreach ($this->waybill as $index => $point) {
            $geo_object_id = intval($point['geo_object_id'] ?? 0);
            if ($geo_object_id <= 0) {
                continue;
            }

            $default_point_type = CheckinPointType::TRANSIT->value;
            if ($index === 0) {
                $default_point_type = CheckinPointType::DEPARTURE->value;
            } elseif ($index === $last_index) {
                $default_point_type = CheckinPointType::ARRIVAL->value;
            }

            if (!empty($point['is_important'])) {
                $default_point_type = CheckinPointType::IMPORTANT->value;
            }

            $point_type = trim((string) ($point['point_type'] ?? ''));
            if (!CheckinPointType::tryFrom($point_type)) {
                $point_type = $default_point_type;
            }

            $is_highlight = $point['is_highlight'] ?? null;
            if ($is_highlight === null) {
                $is_highlight = !empty($point['is_important']) ? 1 : 0;
            } else {
                $is_highlight = !empty($is_highlight) ? 1 : 0;
            }

            $items[] = [
                'geo_object_id' => $geo_object_id,
                'point_type' => $point_type,
                'arrival_at' => !empty($point['arrival_at']) ? (string) $point['arrival_at'] : null,
                'departure_at' => !empty($point['departure_at']) ? (string) $point['departure_at'] : null,
                'is_highlight' => $is_highlight,
                'note' => isset($point['note']) ? trim((string) $point['note']) : null,
            ];
        }

        return $items;
    }
}
