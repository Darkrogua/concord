<?php namespace Zen\Chub\Classes\System;

/**
 * Пути и разбор code фронтенд-блока в теме Liner.
 *
 * code «header» → partials/header/header.htm
 * code «alerts/alert» → partials/alerts/alert.htm
 */
class BlockPaths
{
    protected string $theme_code;

    public function __construct(string $theme_code = 'liner')
    {
        $this->theme_code = $theme_code;
    }

    public static function make(string $theme_code = 'liner'): self
    {
        return new self($theme_code);
    }

    /**
     * @return array{group: string, name: string, partial_key: string}
     */
    public static function parseCode(string $code): array
    {
        $code = trim(str_replace('\\', '/', $code), '/');
        if ($code === '' || !preg_match('#^[a-z0-9_\-]+(?:/[a-z0-9_\-]+)?$#i', $code)) {
            throw new \InvalidArgumentException("Некорректный code блока: {$code}");
        }

        if (str_contains($code, '/')) {
            [$group, $name] = explode('/', $code, 2);
        } else {
            $group = $code;
            $name = $code;
        }

        return [
            'group' => $group,
            'name' => $name,
            'partial_key' => $group.'/'.$name,
        ];
    }

    public function partialKey(string $code): string
    {
        return self::parseCode($code)['partial_key'];
    }

    /**
     * Dom-id блока для атрибута uuid на корневом элементе (внешняя интеграция).
     * code «header» → header; «svg_ships/map_desktop» → svg_ships--map_desktop.
     */
    public function domId(string $code): string
    {
        $code = trim(str_replace('\\', '/', $code), '/');
        if ($code === '') {
            throw new \InvalidArgumentException('Некорректный code блока для domId');
        }

        return str_contains($code, '/') ? str_replace('/', '--', $code) : $code;
    }

    /**
     * Dom-id по ключу partial (group/name), с учётом code без слэша (header/header → header).
     */
    public function domIdFromPartialKey(string $partial_key): string
    {
        $partial_key = trim(str_replace('\\', '/', $partial_key), '/');
        if ($partial_key === '' || !preg_match('#^[a-z0-9_\-]+/[a-z0-9_\-]+$#i', $partial_key)) {
            throw new \InvalidArgumentException("Invalid partial key for domId: {$partial_key}");
        }

        [$group, $name] = explode('/', $partial_key, 2);

        return $this->domId($group === $name ? $group : $partial_key);
    }

    /**
     * Code реестра по ключу partial (group/name).
     */
    public function codeFromPartialKey(string $partial_key): string
    {
        $partial_key = trim(str_replace('\\', '/', $partial_key), '/');
        [$group, $name] = explode('/', $partial_key, 2);

        return $group === $name ? $group : $partial_key;
    }

    public function themeRootRelative(string $code): string
    {
        $parsed = self::parseCode($code);

        return 'themes/'.$this->theme_code.'/partials/'.$parsed['group'];
    }

    public function partialsDir(string $code): string
    {
        $parsed = self::parseCode($code);

        return themes_path($this->theme_code.'/partials/'.$parsed['group']);
    }

    public function mainHtmRelative(string $code): string
    {
        $parsed = self::parseCode($code);

        return $this->themeRootRelative($code).'/'.$parsed['name'].'.htm';
    }

    public function mainHtmAbsolute(string $code): string
    {
        $parsed = self::parseCode($code);

        return $this->partialsDir($code).'/'.$parsed['name'].'.htm';
    }

    public function descriptionRelative(string $code): string
    {
        $parsed = self::parseCode($code);

        return $this->themeRootRelative($code).'/'.$parsed['name'].'.md';
    }

    public function descriptionAbsolute(string $code): string
    {
        $parsed = self::parseCode($code);

        return $this->partialsDir($code).'/'.$parsed['name'].'.md';
    }

    public function metaJsonAbsolute(string $code): string
    {
        $parsed = self::parseCode($code);

        return $this->partialsDir($code).'/'.$parsed['name'].'.meta.json';
    }

    public function variantsJsonAbsolute(string $code): string
    {
        $parsed = self::parseCode($code);

        return $this->partialsDir($code).'/'.$parsed['name'].'.variants.json';
    }

    public function variantsJsonRelative(string $code): string
    {
        $parsed = self::parseCode($code);

        return 'themes/'.$this->theme_code.'/partials/'.$parsed['group'].'/'.$parsed['name'].'.variants.json';
    }

    public function variantHtmAbsolute(string $code, string $variant_code): string
    {
        $parsed = self::parseCode($code);

        return $this->partialsDir($code).'/'.$parsed['name'].'.'.$variant_code.'.htm';
    }

    public function demoJsonAbsolute(string $code, ?string $variant_code = null): string
    {
        $parsed = self::parseCode($code);
        if ($variant_code === null || $variant_code === '' || $variant_code === 'default') {
            return $this->partialsDir($code).'/'.$parsed['name'].'.json';
        }

        return $this->partialsDir($code).'/'.$parsed['name'].'.'.$variant_code.'.json';
    }

    public function demoJsonRelative(string $code, ?string $variant_code = null): string
    {
        $parsed = self::parseCode($code);
        if ($variant_code === null || $variant_code === '' || $variant_code === 'default') {
            return 'themes/'.$this->theme_code.'/partials/'.$parsed['group'].'/'.$parsed['name'].'.json';
        }

        return 'themes/'.$this->theme_code.'/partials/'.$parsed['group'].'/'.$parsed['name'].'.'.$variant_code.'.json';
    }

    public function readDemoJsonRaw(string $code, ?string $variant_code = null): string
    {
        $path = $this->demoJsonAbsolute($code, $variant_code);
        if (!is_file($path)) {
            return '';
        }

        $content = file_get_contents($path);

        return $content === false ? '' : $content;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function writeDemoJsonRaw(string $code, ?string $variant_code, string $json_content): void
    {
        $normalized = $this->normalizeDemoJsonDocument($json_content);
        $path = $this->demoJsonAbsolute($code, $variant_code);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function readDemoData(string $code, string $presentation_variant, string $demo_id): array
    {
        $demo_id = trim($demo_id) !== '' ? trim($demo_id) : 'default';
        $paths_to_try = [];
        $presentation_variant = trim($presentation_variant);

        if ($presentation_variant !== '' && $presentation_variant !== 'default') {
            $paths_to_try[] = $this->demoJsonAbsolute($code, $presentation_variant);
        }

        $paths_to_try[] = $this->demoJsonAbsolute($code, null);

        foreach ($paths_to_try as $path) {
            if (!is_file($path)) {
                continue;
            }

            $all = json_decode((string) file_get_contents($path), true);
            if (!is_array($all) || !array_key_exists($demo_id, $all)) {
                continue;
            }

            $demo = $all[$demo_id];

            return is_array($demo) ? $demo : [];
        }

        return [];
    }

    /**
     * Слоты demo JSON: базовый {name}.json и {name}.{variant}.json.
     *
     * @return list<array{variant_code: ?string, filename: string, relative: string, absolute: string, exists: bool, fallback_filename: ?string}>
     */
    public function listDemoJsonSlots(string $code): array
    {
        $parsed = self::parseCode($code);
        $slots = [];

        $base_absolute = $this->demoJsonAbsolute($code, null);
        $slots[] = [
            'variant_code' => null,
            'filename' => $parsed['name'].'.json',
            'relative' => $this->demoJsonRelative($code, null),
            'absolute' => $base_absolute,
            'exists' => is_file($base_absolute),
            'fallback_filename' => null,
        ];

        foreach ($this->discoverDemoJsonVariantCodes($code) as $variant_code) {
            $absolute = $this->demoJsonAbsolute($code, $variant_code);
            $slots[] = [
                'variant_code' => $variant_code,
                'filename' => $parsed['name'].'.'.$variant_code.'.json',
                'relative' => $this->demoJsonRelative($code, $variant_code),
                'absolute' => $absolute,
                'exists' => true,
                'fallback_filename' => $parsed['name'].'.json',
            ];
        }

        return $slots;
    }

    /**
     * Id demo-сценариев в базовом {name}.json.
     *
     * @return list<string>
     */
    public function listDemoScenarioIds(string $code): array
    {
        $path = $this->demoJsonAbsolute($code, null);
        if (!is_file($path)) {
            return ['default'];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || $data === []) {
            return ['default'];
        }

        $ids = [];
        foreach ($data as $demo_id => $payload) {
            if (is_string($demo_id) && $demo_id !== '' && is_array($payload)) {
                $ids[] = $demo_id;
            }
        }

        sort($ids);

        return $ids !== [] ? $ids : ['default'];
    }

    /**
     * Коды блоков для import-all: partials/{group}/{name}.htm + meta или json.
     *
     * @return list<string>
     */
    public function discoverImportableCodes(): array
    {
        $root = $this->partialsRoot();
        if (!is_dir($root)) {
            return [];
        }

        $codes = [];

        foreach (scandir($root) ?: [] as $group_entry) {
            if ($group_entry === '.' || $group_entry === '..') {
                continue;
            }

            $group_dir = $root.'/'.$group_entry;
            if (!is_dir($group_dir)) {
                continue;
            }

            foreach (scandir($group_dir) ?: [] as $file_entry) {
                if (!str_ends_with($file_entry, '.htm')) {
                    continue;
                }

                $name = substr($file_entry, 0, -4);
                if ($name === '' || str_contains($name, '.')) {
                    continue;
                }

                $has_meta = is_file($group_dir.'/'.$name.'.meta.json');
                $has_json = is_file($group_dir.'/'.$name.'.json');
                if (!$has_meta && !$has_json) {
                    continue;
                }

                $codes[] = $name === $group_entry ? $group_entry : $group_entry.'/'.$name;
            }
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }

    /**
     * @return list<string>
     */
    public function discoverDemoJsonVariantCodes(string $code): array
    {
        $parsed = self::parseCode($code);
        $dir = $this->partialsDir($code);
        if (!is_dir($dir)) {
            return [];
        }

        $prefix = $parsed['name'].'.';
        $codes = [];

        foreach (scandir($dir) ?: [] as $file_entry) {
            if ($file_entry === '.' || $file_entry === '..') {
                continue;
            }

            if (!str_starts_with($file_entry, $prefix) || !str_ends_with($file_entry, '.json')) {
                continue;
            }

            $variant_code = substr($file_entry, strlen($prefix), -5);
            if ($variant_code === '' || str_contains($variant_code, '.')) {
                continue;
            }

            if (in_array($variant_code, ['meta', 'variants'], true)) {
                continue;
            }

            $codes[] = $variant_code;
        }

        sort($codes);

        return $codes;
    }

    /**
     * @return list<string>
     */
    private function collectPresentationVariantCodes(string $code): array
    {
        $codes = [];

        foreach ($this->readVariantsJson($code) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $variant_code = trim((string) ($row['code'] ?? ''));
            if ($variant_code !== '') {
                $codes[] = $variant_code;
            }
        }

        foreach ($this->discoverVariantHtmCodes($code) as $variant_code) {
            $codes[] = $variant_code;
        }

        foreach ($this->discoverDemoJsonVariantCodes($code) as $variant_code) {
            $codes[] = $variant_code;
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function normalizeDemoJsonDocument(string $json_content): string
    {
        $json_content = trim($json_content);
        if ($json_content === '') {
            throw new \InvalidArgumentException('JSON не может быть пустым');
        }

        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Некорректный JSON: '.json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Корень demo JSON должен быть объектом');
        }

        foreach ($data as $demo_id => $demo_payload) {
            if (!is_string($demo_id) || trim($demo_id) === '') {
                throw new \InvalidArgumentException('Ключи demo JSON должны быть непустыми строками');
            }

            if (!is_array($demo_payload)) {
                throw new \InvalidArgumentException('Demo «'.$demo_id.'» должен быть объектом');
            }
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }

    /**
     * Ключ partial для варианта представления (не путать с дочерними partial вида name_suffix).
     */
    public function resolveVariantPartialKey(string $code, string $variant_code): string
    {
        $parsed = self::parseCode($code);
        if ($variant_code === '' || $variant_code === 'default') {
            return $parsed['partial_key'];
        }

        if (is_file($this->variantHtmAbsolute($code, $variant_code))) {
            return $parsed['group'].'/'.$parsed['name'].'.'.$variant_code;
        }

        return $parsed['partial_key'];
    }

    public function partialKeyToAbsolute(string $partial_key): string
    {
        $partial_key = trim(str_replace('\\', '/', $partial_key), '/');
        if ($partial_key === '' || !preg_match('#^[a-z0-9_\-]+(?:/[a-z0-9_\-\.]+)?$#i', $partial_key)) {
            throw new \InvalidArgumentException("Некорректный partial key: {$partial_key}");
        }

        return themes_path($this->theme_code.'/partials/'.$partial_key.'.htm');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readVariantsJson(string $code): array
    {
        $path = $this->variantsJsonAbsolute($code);
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return [];
        }

        $variants = $data['variants'] ?? $data;

        return is_array($variants) ? $variants : [];
    }

    /**
     * Коды вариантов из файлов {name}.{variant}.htm (без default).
     *
     * @return list<string>
     */
    public function discoverVariantHtmCodes(string $code): array
    {
        $parsed = self::parseCode($code);
        $dir = $this->partialsDir($code);
        if (!is_dir($dir)) {
            return [];
        }

        $prefix = $parsed['name'].'.';
        $codes = [];

        foreach (scandir($dir) ?: [] as $file_entry) {
            if ($file_entry === '.' || $file_entry === '..') {
                continue;
            }

            if (!str_starts_with($file_entry, $prefix) || !str_ends_with($file_entry, '.htm')) {
                continue;
            }

            $variant_code = substr($file_entry, strlen($prefix), -4);
            if ($variant_code === '' || str_contains($variant_code, '.')) {
                continue;
            }

            $codes[] = $variant_code;
        }

        sort($codes);

        return $codes;
    }

    public function relativeFromThemeRoot(string $absolute_path): string
    {
        $theme_root = themes_path($this->theme_code);
        $absolute_path = str_replace('\\', '/', $absolute_path);
        $theme_root = str_replace('\\', '/', $theme_root);

        if (!str_starts_with($absolute_path, $theme_root.'/')) {
            throw new \InvalidArgumentException("Файл вне темы {$this->theme_code}: {$absolute_path}");
        }

        return 'themes/'.$this->theme_code.'/'.substr($absolute_path, strlen($theme_root) + 1);
    }

    public function readDescription(string $code): string
    {
        $path = $this->descriptionAbsolute($code);
        if (!is_file($path)) {
            return '';
        }

        $content = file_get_contents($path);

        return $content === false ? '' : $content;
    }

    public function writeDescription(string $code, string $markdown): void
    {
        $path = $this->descriptionAbsolute($code);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $markdown);
    }

    public function partialsRoot(): string
    {
        return themes_path($this->theme_code.'/partials');
    }

    public function metaJsonRelative(string $code): string
    {
        $parsed = self::parseCode($code);

        return 'themes/'.$this->theme_code.'/partials/'.$parsed['group'].'/'.$parsed['name'].'.meta.json';
    }

    /**
     * Абсолютные пути ко всем *.meta.json в partials/{group}/ (без рекурсии).
     *
     * @return list<string>
     */
    public function scanMetaJsonFiles(): array
    {
        $root = $this->partialsRoot();
        if (!is_dir($root)) {
            return [];
        }

        $paths = [];

        foreach (scandir($root) ?: [] as $group_entry) {
            if ($group_entry === '.' || $group_entry === '..') {
                continue;
            }

            $group_dir = $root.'/'.$group_entry;
            if (!is_dir($group_dir)) {
                continue;
            }

            foreach (scandir($group_dir) ?: [] as $file_entry) {
                if ($file_entry === '.' || $file_entry === '..') {
                    continue;
                }

                if (!str_ends_with($file_entry, '.meta.json')) {
                    continue;
                }

                $absolute = $group_dir.'/'.$file_entry;
                if (is_file($absolute)) {
                    $paths[] = $absolute;
                }
            }
        }

        sort($paths);

        return $paths;
    }

    public static function foldersDataPath(): string
    {
        return base_path('plugins/zen/chub/data/blocks/folders_data.json');
    }
}
