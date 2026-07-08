<?php namespace Zen\Chub\Classes\System;

use File;

/**
 * Сканирует partials темы и генерирует auto-bundle SCSS/JS.
 */
class BlocksSync
{
    protected string $theme_code;

    protected string $theme_path;

    public function __construct(string $theme_code = 'liner')
    {
        $this->theme_code = $theme_code;
        $this->theme_path = themes_path($theme_code);
    }

    /**
     * @return array{blocks: int, scss: int, js: int}
     */
    public function run(): array
    {
        $blocks = $this->discoverBlocks();
        $this->validateBlocks($blocks);
        $scss_count = $this->writeScssBundle($blocks);
        $js_count = $this->writeJsBundle($blocks);

        return [
            'blocks' => count($blocks),
            'scss' => $scss_count,
            'js' => $js_count,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function discoverBlocks(): array
    {
        $partials_path = $this->theme_path.'/partials';
        if (!is_dir($partials_path)) {
            return [];
        }

        $blocks = [];
        foreach (File::allFiles($partials_path) as $file) {
            $basename = $file->getFilename();
            if (!str_ends_with($basename, '.meta.json')) {
                continue;
            }

            $name = substr($basename, 0, -strlen('.meta.json'));
            $group = $file->getRelativePath();
            if ($group === '' || str_contains($group, '..')) {
                continue;
            }

            $block_key = $group.'/'.$name;
            $meta_path = $file->getPathname();
            $meta = json_decode((string) file_get_contents($meta_path), true);
            if (!is_array($meta)) {
                throw new \RuntimeException("Invalid meta JSON: {$meta_path}");
            }

            $htm_path = $file->getPath().'/'.$name.'.htm';
            $meta = $this->normalizeBlockMeta($meta, $block_key);
            $blocks[] = [
                'key' => $block_key,
                'group' => $group,
                'name' => $name,
                'dir' => $file->getPath(),
                'meta_path' => $meta_path,
                'meta' => $meta,
                'htm_path' => $htm_path,
                'has_htm' => is_file($htm_path),
                'has_scss' => is_file($file->getPath().'/'.$name.'.scss'),
                'has_js' => is_file($file->getPath().'/'.$name.'.js'),
                'has_vue' => is_file($file->getPath().'/'.$name.'.vue'),
            ];
        }

        usort($blocks, function (array $a, array $b): int {
            $ta = (string) ($a['meta']['title'] ?? $a['key']);
            $tb = (string) ($b['meta']['title'] ?? $b['key']);

            return strcasecmp($ta, $tb);
        });

        return $blocks;
    }

    /**
     * @return array{ok: bool, checked: int, issues: list<array{key: string, dom_id: string, error: string}>}
     */
    public function lintDomIds(): array
    {
        $paths = BlockPaths::make($this->theme_code);
        $issues = [];
        $blocks = $this->discoverBlocks();

        foreach ($blocks as $block) {
            if (!$block['has_htm']) {
                $issues[] = [
                    'key' => (string) $block['key'],
                    'dom_id' => '',
                    'error' => 'Отсутствует .htm',
                ];

                continue;
            }

            $dom_id = $this->resolveBlockDomId($block, $paths);
            $htm = (string) file_get_contents($block['htm_path']);

            $has_dynamic = str_contains($htm, 'uuid="{{ block_dom_id }}"')
                || str_contains($htm, "uuid='{{ block_dom_id }}'");
            $has_literal = str_contains($htm, 'uuid="'.$dom_id.'"') || str_contains($htm, "uuid='{$dom_id}'");

            if (!$has_dynamic && !$has_literal) {
                $issues[] = [
                    'key' => (string) $block['key'],
                    'dom_id' => $dom_id,
                    'error' => 'Нет uuid="{{ block_dom_id }}" или uuid="'.$dom_id.'"',
                ];
            }
        }

        return [
            'ok' => $issues === [],
            'checked' => count($blocks),
            'issues' => $issues,
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    protected function validateBlocks(array $blocks): void
    {
        $paths = BlockPaths::make($this->theme_code);

        foreach ($blocks as $block) {
            if (!$block['has_htm']) {
                throw new \RuntimeException("Missing template for block {$block['key']}: {$block['htm_path']}");
            }

            $dom_id = $this->resolveBlockDomId($block, $paths);
            $htm = (string) file_get_contents($block['htm_path']);

            $has_dynamic = str_contains($htm, 'uuid="{{ block_dom_id }}"')
                || str_contains($htm, "uuid='{{ block_dom_id }}'");
            $has_literal = str_contains($htm, 'uuid="'.$dom_id.'"') || str_contains($htm, "uuid='{$dom_id}'");

            if (!$has_dynamic && !$has_literal) {
                throw new \RuntimeException(
                    "Block {$block['key']}: корневой элемент должен иметь uuid=\"{{ block_dom_id }}\" или uuid=\"{$dom_id}\""
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $block
     */
    protected function resolveBlockDomId(array $block, BlockPaths $paths): string
    {
        $meta = is_array($block['meta'] ?? null) ? $block['meta'] : [];
        if (($meta['schema'] ?? '') === BlockApp::EXPORT_SCHEMA) {
            $record = is_array($meta['record'] ?? null) ? $meta['record'] : [];
            $code = trim((string) ($record['code'] ?? ''));
            if ($code !== '') {
                return $paths->domId($code);
            }
        }

        return $paths->domIdFromPartialKey((string) $block['key']);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    protected function writeScssBundle(array $blocks): int
    {
        $lines = [
            '// AUTO-GENERATED by chub:blocks:sync — do not edit',
            '',
        ];
        $count = 0;

        foreach ($blocks as $block) {
            if (!$block['has_scss']) {
                continue;
            }
            $rel = '../../partials/'.$block['group'].'/'.$block['name'].'.scss';
            $lines[] = "@use '{$rel}';";
            $count++;
        }

        $target = $this->theme_path.'/resources/scss/_partials_auto.scss';
        File::put($target, implode("\n", $lines)."\n");

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    protected function writeJsBundle(array $blocks): int
    {
        $lines = [
            '// AUTO-GENERATED by chub:blocks:sync — do not edit',
            '',
        ];
        $count = 0;

        foreach ($blocks as $block) {
            if (!$block['has_js']) {
                continue;
            }
            $rel = '../../partials/'.$block['group'].'/'.$block['name'].'.js';
            $lines[] = "import '{$rel}'";
            $count++;
        }

        $target = $this->theme_path.'/resources/js/_partials_auto.js';
        File::put($target, implode("\n", $lines)."\n");

        return $count;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    protected function normalizeBlockMeta(array $meta, string $block_key): array
    {
        if (($meta['schema'] ?? '') !== BlockApp::EXPORT_SCHEMA) {
            return $meta;
        }

        $record = is_array($meta['record'] ?? null) ? $meta['record'] : [];
        $title = trim((string) ($record['name'] ?? ''));
        if ($title !== '') {
            $meta['title'] = $title;
        }

        $meta['partial'] = trim((string) ($record['code'] ?? $block_key)) ?: $block_key;

        return $meta;
    }
}
