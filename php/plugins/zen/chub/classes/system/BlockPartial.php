<?php namespace Zen\Chub\Classes\System;

/**
 * Единая точка монтирования partial-блока темы Liner (data + variant + dom_id).
 */
class BlockPartial
{
    /**
     * Сбрасывает унаследованный от родительского partial `data`, если это не payload текущего блока.
     */
    public static function forgetDataIfMissing(object $partial, string $markerKey): void
    {
        if (!self::hasParam($partial, 'data')) {
            return;
        }

        $data = self::readArray($partial, 'data');
        if (array_key_exists($markerKey, $data)) {
            return;
        }

        if ($partial instanceof \ArrayObject) {
            unset($partial['data']);

            return;
        }

        if ($partial instanceof \ArrayAccess) {
            $partial->offsetUnset('data');
        }
    }

    /**
     * @param object $partial OctoberCMS partial controller ($this в onStart partial)
     */
    public static function mount(object $partial, string $code, ?string $theme_code = null): void
    {
        $paths = BlockPaths::make($theme_code ?? 'liner');
        $partial_key = $paths->partialKey($code);

        $partial['block_dom_id'] = $paths->domId($code);

        $variant = self::readString($partial, 'variant', 'default');
        $partial['variant'] = $variant;

        $explicit_data = self::hasParam($partial, 'data');
        $data = $explicit_data ? self::readArray($partial, 'data') : [];

        if (!$explicit_data) {
            $demo_id = self::readString($partial, 'demo', 'default');
            $data = self::loadDemoData($paths, $code, $partial_key, $variant, $demo_id);
        }

        $partial['data'] = $data;
        self::unpack($partial, $data, !$explicit_data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadDemoData(
        BlockPaths $paths,
        string $code,
        string $partial_key,
        string $variant,
        string $demo_id
    ): array {
        $data = $paths->readDemoData($code, $variant, $demo_id);
        if ($data !== []) {
            return $data;
        }

        try {
            return BlockData::get($partial_key, $demo_id);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function unpack(object $partial, array $data, bool $only_missing): void
    {
        foreach ($data as $key => $value) {
            if ($only_missing && $partial->offsetExists($key)) {
                continue;
            }

            $partial[$key] = $value;
        }
    }

    private static function hasParam(object $partial, string $key): bool
    {
        return $partial instanceof \ArrayAccess && $partial->offsetExists($key);
    }

    private static function readString(object $partial, string $key, string $default = ''): string
    {
        if (!self::hasParam($partial, $key)) {
            return $default;
        }

        $value = trim((string) $partial->offsetGet($key));

        return $value !== '' ? $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private static function readArray(object $partial, string $key): array
    {
        if (!self::hasParam($partial, $key)) {
            return [];
        }

        $value = $partial->offsetGet($key);

        return is_array($value) ? $value : [];
    }

    private static function readBool(object $partial, string $key): bool
    {
        if (!self::hasParam($partial, $key)) {
            return false;
        }

        return (bool) $partial->offsetGet($key);
    }

    /**
     * Разворачивает элементы вида {"demo":"default"} в полные данные дочернего partial (Store Book / demo JSON).
     *
     * @param list<mixed> $items
     * @return list<mixed>
     */
    public static function expandDemoOnlyItems(array $items, string $child_code, ?string $theme_code = null): array
    {
        $paths = BlockPaths::make($theme_code ?? 'liner');
        $partial_key = $paths->partialKey($child_code);
        $expanded = [];

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['demo'])) {
                $expanded[] = $item;

                continue;
            }

            $demo_id = trim((string) $item['demo']);
            if ($demo_id === '') {
                $demo_id = 'default';
            }

            if (count($item) === 1) {
                try {
                    $expanded[] = BlockData::get($partial_key, $demo_id);
                } catch (\Throwable) {
                    $expanded[] = $item;
                }

                continue;
            }

            $expanded[] = $item;
        }

        return $expanded;
    }
}
