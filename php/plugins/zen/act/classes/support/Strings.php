<?php namespace Zen\Act\Classes\Support;

use Str;

class Strings
{
    public static function make()
    {
        return new self();
    }

    /**
     * Сгенерировать токен с заданной длинной
     * @param int $length
     * @return string
     */
    public function createToken(int $length = 8): string
    {
        return strtolower(Str::random($length));
    }

    /**
     * Сгенерировать UUID
     * @return string
     */
    public function createUuid(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Преобразовать dotpath в [class, method]
     * @param string $dotpath
     * @return array
     */
    public function dotpathToHandler(string $dotpath): array
    {
        $method = \Str::afterLast($dotpath, '.');
        $handler = str_replace(".$method", '', $dotpath);
        $handler = str_replace(".", '\\', $handler);

        return [
            'class' => $handler,
            'method' => $method
        ];
    }
}