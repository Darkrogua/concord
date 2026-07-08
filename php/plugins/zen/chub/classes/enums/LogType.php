<?php namespace Zen\Chub\Classes\Enums;

enum LogType: string
{
    case INFO = 'info';
    case ERROR = 'error';
    case DEBUG = 'debug';

    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Информация',
            self::ERROR => 'Ошибка',
            self::DEBUG => 'Отладка',
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
