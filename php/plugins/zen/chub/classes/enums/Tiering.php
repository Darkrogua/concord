<?php namespace Zen\Chub\Classes\Enums;

enum Tiering: string
{
    case FLAT = 'flat';
    case TIERED = 'tiered';

    public function label(): string
    {
        return match ($this) {
            self::FLAT => 'Неярусное',
            self::TIERED => 'Ярусное',
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