<?php namespace Zen\Chub\Classes\Enums;

enum CheckinPointType: string
{
    case DEPARTURE = 'departure';
    case ARRIVAL = 'arrival';
    case TRANSIT = 'transit';
    case IMPORTANT = 'important';

    public function label(): string
    {
        return match ($this) {
            self::DEPARTURE => 'Точка отправления',
            self::ARRIVAL => 'Точка прибытия',
            self::TRANSIT => 'Промежуточная точка',
            self::IMPORTANT => 'Важная точка',
        };
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
