<?php namespace Zen\Chub\Classes\Support;

use Carbon\Carbon;
use October\Rain\Parse\Yaml as YamlParser;
use Throwable;

/**
 * Transformers — утилита для преобразования данных
 */
class Transformers
{
    public static function make()
    {
        return new self();
    }

    /**
     * Декодирует JSON-строку в массив или объект
     */
    public function fromJson(?string $json): array|null
    {
        if ($json === null) {
            return null;
        }

        $trimmed = trim($json);
        if ($trimmed === '') {
            return null;
        }

        return json_decode($trimmed, true);
    }

    /**
     * Кодирует массив в JSON-строку
     */
    public function toJson(?array $data, bool $pretty_print = true, bool $no_slashes = false): string | null
    {
        if ($data === null) {
            return null;
        }

        $options = JSON_UNESCAPED_UNICODE;

        if ($pretty_print) {
            $options |= JSON_PRETTY_PRINT;
        }
        if ($no_slashes) {
            $options |= JSON_UNESCAPED_SLASHES;
        }

        return json_encode($data, $options);
    }

    /**
     * Сохраняет массив в файл.
     */
    public function arrayToFile(array $array, string $file_name): void
    {
        file_put_contents($file_name, $this->toJson($array, true));
    }

    /**
     * Читает массив из файла.
     */
    public function arrayFromFile(string $file_name): array | null
    {
        if (!file_exists($file_name)) {
            return null;
        }

        return $this->fromJson(file_get_contents($file_name));
    }

    /**
     * Декодирует YAML-строку в массив (Symfony YAML через October).
     */
    public function fromYaml(?string $yaml): ?array
    {
        if ($yaml === null) {
            return null;
        }

        $trimmed = trim($yaml);
        if ($trimmed === '') {
            return null;
        }

        try {
            $parser = new YamlParser();
            $result = $parser->parse($trimmed);

            return is_array($result) ? $result : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Кодирует массив в YAML-строку.
     *
     * @param array<string, mixed>|null $data
     * @param array<string, mixed> $options опции October Yaml::render (inline, exceptionOnInvalidType, objectSupport)
     */
    public function toYaml(?array $data, array $options = []): ?string
    {
        if ($data === null || $data === []) {
            return null;
        }

        try {
            $parser = new YamlParser();
            $yaml = $parser->render($data, array_merge([
                'inline' => 6,
                'exceptionOnInvalidType' => false,
                'objectSupport' => true,
            ], $options));

            $yaml = trim((string) $yaml);

            return $yaml === '' ? null : $yaml;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Разбирает markdown с опциональным YAML frontmatter (--- ... ---).
     *
     * @return array{frontmatter_raw: ?string, frontmatter_array: ?array, body: string}
     */
    public function parseMarkdownFrontmatter(string $content): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);

        if (!preg_match('/\A---\s*\n/', $normalized)) {
            return [
                'frontmatter_raw' => null,
                'frontmatter_array' => null,
                'body' => $content,
            ];
        }

        if (!preg_match('/\A---\s*\n([\s\S]*?)\n---\s*\n([\s\S]*)\z/', $normalized, $matches)) {
            return [
                'frontmatter_raw' => null,
                'frontmatter_array' => null,
                'body' => $content,
            ];
        }

        $yaml_raw = $matches[1];
        $body = $matches[2];
        $frontmatter_array = $this->fromYaml($yaml_raw);

        return [
            'frontmatter_raw' => $yaml_raw,
            'frontmatter_array' => $frontmatter_array,
            'body' => $body,
        ];
    }

    public function carbon(string $time)
    {
        return Carbon::parse($time);
    }
}
