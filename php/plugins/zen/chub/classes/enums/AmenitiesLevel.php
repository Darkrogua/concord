<?php namespace Zen\Chub\Classes\Enums;

enum AmenitiesLevel: string
{
    case FULL = 'full';
    case PARTIAL = 'partial';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::FULL => 'Со всеми удобствами',
            self::PARTIAL => 'С частичными удобствами',
            self::NONE => 'Без удобств',
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