<?php namespace Zen\Chub\Classes\System;

/**
 * Демо-данные и метаданные блоков темы Liner (partials/{group}/{name}.json / .meta.json).
 */
class BlockData
{
    protected static string $theme_code = 'liner';

    /**
     * @return array<string, mixed>
     */
    public static function meta(string $block_key): array
    {
        $path = self::resolveMetaPath($block_key);
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Block meta not found: {$block_key} ({$path})");
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid block meta JSON: {$path}");
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $block_key, string $demo_id = 'default'): array
    {
        $path = self::resolveDataPath($block_key);
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Block data not found: {$block_key} ({$path})");
        }

        $all = json_decode((string) file_get_contents($path), true);
        if (!is_array($all)) {
            throw new \RuntimeException("Invalid block data JSON: {$path}");
        }

        if (!array_key_exists($demo_id, $all)) {
            throw new \InvalidArgumentException("Demo \"{$demo_id}\" not found in {$path}");
        }

        $demo = $all[$demo_id];
        if (!is_array($demo)) {
            throw new \RuntimeException("Demo \"{$demo_id}\" must be an object in {$path}");
        }

        return $demo;
    }

    /**
     * ID demo-сценария из аргументов {% partial ... demo='...' %} (не из URL param()).
     */
    public static function partialDemoId(object $partial, string $default = 'default'): string
    {
        $demo_id = '';
        if ($partial instanceof \ArrayAccess && $partial->offsetExists('demo')) {
            $demo_id = trim((string) $partial->offsetGet('demo'));
        }

        return $demo_id !== '' ? $demo_id : $default;
    }

    public static function themePartialsPath(?string $theme_code = null): string
    {
        $code = $theme_code ?? self::$theme_code;

        return themes_path($code.'/partials');
    }

    public static function resolveMetaPath(string $block_key, ?string $theme_code = null): string
    {
        return self::resolveBlockFilePath($block_key, 'meta.json', $theme_code);
    }

    public static function resolveDataPath(string $block_key, ?string $theme_code = null): string
    {
        return self::resolveBlockFilePath($block_key, 'json', $theme_code);
    }

    public static function resolveBlockFilePath(string $block_key, string $suffix, ?string $theme_code = null): string
    {
        $block_key = trim(str_replace('\\', '/', $block_key), '/');
        if ($block_key === '' || !preg_match('#^[a-z0-9_\-]+/[a-z0-9_\-]+$#i', $block_key)) {
            throw new \InvalidArgumentException("Invalid block key: {$block_key}");
        }

        [$group, $name] = explode('/', $block_key, 2);

        return self::themePartialsPath($theme_code).'/'.$group.'/'.$name.'.'.$suffix;
    }
}
