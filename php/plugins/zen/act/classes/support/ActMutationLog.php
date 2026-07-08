<?php namespace Zen\Act\Classes\Support;

class ActMutationLog
{
    /**
     * @param  array{action: string, block_id: ?string, data: mixed, chain: ?string, created_at: string}  $entry
     */
    public static function hash(array $entry): string
    {
        return hash('sha256', self::canonicalJson($entry));
    }

    public static function decodeData(string $json): mixed
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    public static function canonicalJson(mixed $data): string
    {
        return json_encode(
            self::normalizeForHash($data),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private static function normalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (self::isList($value)) {
            return array_map(fn ($item): mixed => self::normalizeForHash($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::normalizeForHash($item);
        }

        return $value;
    }

    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
