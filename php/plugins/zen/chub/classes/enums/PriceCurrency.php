<?php namespace Zen\Chub\Classes\Enums;

enum PriceCurrency: string
{
    case RUB = 'RUB';
    case USD = 'USD';
    case EUR = 'EUR';

    public function label(): string
    {
        return match ($this) {
            self::RUB => 'Российский рубль',
            self::USD => 'Доллар США',
            self::EUR => 'Евро',
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
