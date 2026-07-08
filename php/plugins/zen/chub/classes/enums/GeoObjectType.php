<?php namespace Zen\Chub\Classes\Enums;

enum GeoObjectType: string
{
    case COUNTRY = 'country';
    case REGION = 'region';
    case CITY = 'city';
    case LANDMARK = 'landmark';
    case BERTH = 'berth';
    case PLACE = 'place';

    public function label(): string
    {
        return match ($this) {
            self::COUNTRY => 'Страна',
            self::REGION => 'Регион',
            self::CITY => 'Город',
            self::LANDMARK => 'Достопримечательность',
            self::BERTH => 'Причал',
            self::PLACE => 'Место',
        };
    }

    public function isMenuEligible(): bool
    {
        return match ($this) {
            self::COUNTRY, self::REGION, self::CITY => true,
            self::LANDMARK, self::BERTH, self::PLACE => false,
        };
    }

    public function isRouteEligible(): bool
    {
        return true;
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function labelByValue(?string $value): string
    {
        $type = $value ? self::tryFrom($value) : null;

        return $type ? $type->label() : self::PLACE->label();
    }
}
