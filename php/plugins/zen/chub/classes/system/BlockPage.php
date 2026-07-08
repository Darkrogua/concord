<?php namespace Zen\Chub\Classes\System;

/**
 * Хелпер страниц: подготовка data для вызова partial-блоков.
 */
class BlockPage
{
    /**
     * Загружает demo-сценарий в переменную страницы и возвращает имя переменной.
     *
     * @return string имя Twig-переменной (…_data)
     */
    public static function bindDemo(object $page, string $code, string $demo_id = 'default', ?string $suffix = null): string
    {
        $var_name = self::dataVarName($code, $suffix);
        $partial_key = BlockPaths::make()->partialKey($code);
        $page[$var_name] = BlockData::get($partial_key, $demo_id);

        return $var_name;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function bind(object $page, string $code, array $data, ?string $suffix = null): string
    {
        $var_name = self::dataVarName($code, $suffix);
        $page[$var_name] = $data;

        return $var_name;
    }

    public static function dataVarName(string $code, ?string $suffix = null): string
    {
        $base = str_replace('-', '_', BlockPaths::make()->domId($code));

        if ($suffix !== null && $suffix !== '') {
            $suffix = preg_replace('/[^a-z0-9_]+/i', '_', $suffix) ?? 'extra';

            return "{$base}_{$suffix}_data";
        }

        return "{$base}_data";
    }
}
