<?php namespace Zen\Act\Classes\Support;

use Carbon\Carbon;
use Zen\Act\Classes\System\BlockApp;

/**
 * Локальный workbench: playground/{act_uuid}/{block_uuid}/fragment.html + meta.json
 */
class BlockWorkbench
{
    public const META_FORMAT_VERSION = 1;

    public static function make(): self
    {
        return new self();
    }

    public function playgroundRoot(): string
    {
        $configured = trim((string) env('ACT_PLAYGROUND_PATH', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim(base_path('../playground'), '/');
    }

    public function blockDir(string $act_id, string $block_id): string
    {
        $act_id = trim($act_id);
        $block_id = trim($block_id);

        return $this->playgroundRoot().'/'.$act_id.'/'.$block_id;
    }

    public function fragmentPath(string $act_id, string $block_id): string
    {
        return $this->blockDir($act_id, $block_id).'/fragment.html';
    }

    public function metaPath(string $act_id, string $block_id): string
    {
        return $this->blockDir($act_id, $block_id).'/meta.json';
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(string $act_id, string $block_id): array
    {
        $block = $this->requireWebappBlock($act_id, $block_id);
        $html = trim((string) ($block['data']['html'] ?? ''));

        $dir = $this->blockDir($act_id, $block_id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fragment_path = $this->fragmentPath($act_id, $block_id);
        file_put_contents($fragment_path, $html);

        $meta = $this->buildMeta($act_id, $block_id, $block, $html);
        Transformers::make()->arrayToFile($meta, $this->metaPath($act_id, $block_id));

        return [
            'act_id' => trim($act_id),
            'block_id' => trim($block_id),
            'directory' => $dir,
            'fragment_path' => $fragment_path,
            'meta_path' => $this->metaPath($act_id, $block_id),
            'bytes' => strlen($html),
            'block_hash' => (string) ($block['hash'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function push(string $act_id, string $block_id, bool $force = false): array
    {
        $act_id = trim($act_id);
        $block_id = trim($block_id);

        $fragment_path = $this->fragmentPath($act_id, $block_id);
        $meta_path = $this->metaPath($act_id, $block_id);

        if (! is_file($fragment_path)) {
            throw new \RuntimeException("Файл не найден: {$fragment_path} (сначала pull)");
        }

        if (! is_file($meta_path)) {
            throw new \RuntimeException("meta.json не найден: {$meta_path} (сначала pull)");
        }

        $meta = Transformers::make()->arrayFromFile($meta_path);
        if (! is_array($meta)) {
            throw new \RuntimeException('meta.json не читается или пуст');
        }

        $this->validateMeta($meta, $act_id, $block_id);

        $html = file_get_contents($fragment_path);
        if ($html === false) {
            throw new \RuntimeException("Не удалось прочитать {$fragment_path}");
        }

        $content_sha256 = hash('sha256', $html);
        $stored_content_sha256 = (string) ($meta['content_sha256'] ?? '');

        $live = $this->requireWebappBlock($act_id, $block_id);
        $live_hash = (string) ($live['hash'] ?? '');
        $meta_hash = (string) ($meta['block_hash'] ?? '');

        if ($meta_hash !== '' && $live_hash !== '' && $meta_hash !== $live_hash && ! $force) {
            throw new \RuntimeException(
                'block_hash в meta.json не совпадает с БД (блок меняли в SPA). Сделайте pull или push --force'
            );
        }

        if ($stored_content_sha256 !== '' && hash_equals($stored_content_sha256, $content_sha256)) {
            return [
                'act_id' => $act_id,
                'block_id' => $block_id,
                'skipped' => true,
                'reason' => 'fragment.html не изменился',
                'block_hash' => $live_hash,
            ];
        }

        $updated = BlockApp::make()->update($act_id, $block_id, [
            'data' => [
                'type' => 'webapp',
                'html' => $html,
            ],
        ], null);

        if ($updated === null) {
            throw new \RuntimeException("Блок id={$block_id} не найден в акте id={$act_id}");
        }

        $fresh_meta = $this->buildMeta($act_id, $block_id, $updated, $html);
        Transformers::make()->arrayToFile($fresh_meta, $meta_path);

        return [
            'act_id' => $act_id,
            'block_id' => $block_id,
            'skipped' => false,
            'block_hash' => (string) ($updated['hash'] ?? ''),
            'bytes' => strlen($html),
            'forced' => $force && $meta_hash !== $live_hash,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $act_id, string $block_id): array
    {
        $act_id = trim($act_id);
        $block_id = trim($block_id);

        $live = BlockApp::make()->show($act_id, $block_id, null);
        $fragment_path = $this->fragmentPath($act_id, $block_id);
        $meta_path = $this->metaPath($act_id, $block_id);

        $disk_exists = is_file($fragment_path) && is_file($meta_path);
        $meta = $disk_exists ? Transformers::make()->arrayFromFile($meta_path) : null;

        $live_hash = is_array($live) ? (string) ($live['hash'] ?? '') : null;
        $meta_hash = is_array($meta) ? (string) ($meta['block_hash'] ?? '') : null;

        $content_sha256 = null;
        $meta_content_sha256 = is_array($meta) ? (string) ($meta['content_sha256'] ?? '') : null;
        if (is_file($fragment_path)) {
            $raw = file_get_contents($fragment_path);
            if ($raw !== false) {
                $content_sha256 = hash('sha256', $raw);
            }
        }

        $hash_in_sync = $meta_hash !== null && $live_hash !== null && $meta_hash === $live_hash;
        $content_changed = $meta_content_sha256 !== null
            && $content_sha256 !== null
            && $meta_content_sha256 !== $content_sha256;

        return [
            'act_id' => $act_id,
            'block_id' => $block_id,
            'directory' => $this->blockDir($act_id, $block_id),
            'disk_exists' => $disk_exists,
            'db_exists' => is_array($live),
            'live_block_hash' => $live_hash,
            'meta_block_hash' => $meta_hash,
            'hash_in_sync' => $hash_in_sync,
            'content_sha256' => $content_sha256,
            'meta_content_sha256' => $meta_content_sha256,
            'content_changed' => $content_changed,
            'push_ready' => $disk_exists && ($hash_in_sync || $content_changed),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEntries(): array
    {
        $root = $this->playgroundRoot();
        if (! is_dir($root)) {
            return [];
        }

        $entries = [];
        $act_dirs = scandir($root);
        if ($act_dirs === false) {
            return [];
        }

        foreach ($act_dirs as $act_id) {
            if ($act_id === '.' || $act_id === '..') {
                continue;
            }

            $act_path = $root.'/'.$act_id;
            if (! is_dir($act_path)) {
                continue;
            }

            $block_dirs = scandir($act_path);
            if ($block_dirs === false) {
                continue;
            }

            foreach ($block_dirs as $block_id) {
                if ($block_id === '.' || $block_id === '..') {
                    continue;
                }

                $block_path = $act_path.'/'.$block_id;
                if (! is_dir($block_path)) {
                    continue;
                }

                if (! is_file($block_path.'/meta.json')) {
                    continue;
                }

                $meta = Transformers::make()->arrayFromFile($block_path.'/meta.json');
                $entries[] = [
                    'act_id' => $act_id,
                    'block_id' => $block_id,
                    'name' => is_array($meta) ? (string) ($meta['name'] ?? '') : '',
                    'type' => is_array($meta) ? (string) ($meta['type'] ?? '') : '',
                    'pulled_at' => is_array($meta) ? (string) ($meta['pulled_at'] ?? '') : '',
                    'directory' => $block_path,
                ];
            }
        }

        usort($entries, fn (array $a, array $b): int => strcmp($a['act_id'].$a['block_id'], $b['act_id'].$b['block_id']));

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireWebappBlock(string $act_id, string $block_id): array
    {
        $block = BlockApp::make()->show(trim($act_id), trim($block_id), null);
        if ($block === null) {
            throw new \RuntimeException("Блок id={$block_id} не найден в акте id={$act_id}");
        }

        $type = (string) ($block['data']['type'] ?? '');
        if ($type !== 'webapp') {
            throw new \RuntimeException("Блок id={$block_id} имеет тип \"{$type}\"; playground поддерживает только webapp");
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function buildMeta(string $act_id, string $block_id, array $block, string $html): array
    {
        return [
            'format_version' => self::META_FORMAT_VERSION,
            'act_id' => trim($act_id),
            'block_id' => trim($block_id),
            'name' => (string) ($block['name'] ?? ''),
            'type' => 'webapp',
            'pulled_at' => Carbon::now('UTC')->toIso8601String(),
            'block_hash' => (string) ($block['hash'] ?? ''),
            'content_sha256' => hash('sha256', $html),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function validateMeta(array $meta, string $act_id, string $block_id): void
    {
        if ((int) ($meta['format_version'] ?? 0) !== self::META_FORMAT_VERSION) {
            throw new \RuntimeException('Неподдерживаемый format_version в meta.json');
        }

        if (strcasecmp((string) ($meta['act_id'] ?? ''), trim($act_id)) !== 0) {
            throw new \RuntimeException('act_id в meta.json не совпадает с аргументом --act');
        }

        if (strcasecmp((string) ($meta['block_id'] ?? ''), trim($block_id)) !== 0) {
            throw new \RuntimeException('block_id в meta.json не совпадает с аргументом --block');
        }
    }
}
