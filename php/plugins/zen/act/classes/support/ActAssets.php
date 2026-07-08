<?php namespace Zen\Act\Classes\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Zen\Act\Classes\System\ActApp;
use Zen\Act\Classes\System\BlockApp;

/**
 * Изображения галереи: storage/acts/{act_id}/assets/images/{block_id}/{image_id}.ext
 */
class ActAssets
{
    public const MAX_FILE_BYTES = 10 * 1024 * 1024;

    public const MAX_ITEMS_PER_BLOCK = 50;

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
    ];

    /** @var array<string, string> */
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public static function make(): self
    {
        return new self();
    }

    public function imagesRoot(string $act_id): string
    {
        return ActStorage::make()->actDirectory($act_id).'/assets/images';
    }

    public function blockImagesDir(string $act_id, string $block_id): string
    {
        return $this->imagesRoot($act_id).'/'.$block_id;
    }

    public function imageUrl(string $act_id, string $block_id, string $image_id): string
    {
        return '/act.assets/'.rawurlencode($act_id).'/'.rawurlencode($block_id).'/'.rawurlencode($image_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function upload(string $act_id, string $block_id, UploadedFile $file, int $owner_id): array
    {
        ActApp::make()->requireOwnedActPublic($owner_id, $act_id);

        $block = BlockApp::make()->show($act_id, $block_id, $owner_id);
        if ($block === null) {
            throw new \RuntimeException('Блок не найден');
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        if (($data['type'] ?? null) !== 'gallery') {
            throw new \InvalidArgumentException('Блок не является фотогалереей');
        }

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if (count($items) >= self::MAX_ITEMS_PER_BLOCK) {
            throw new \InvalidArgumentException('Достигнут лимит изображений в галерее ('.self::MAX_ITEMS_PER_BLOCK.')');
        }

        $mime = $this->detectMime($file);
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Недопустимый формат изображения');
        }

        if ($file->getSize() > self::MAX_FILE_BYTES) {
            throw new \InvalidArgumentException('Файл превышает лимит 10 МБ');
        }

        $image_id = (string) Str::uuid();
        $ext = self::MIME_TO_EXT[$mime] ?? 'bin';
        $dir = $this->blockImagesDir($act_id, $block_id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $original_name = trim((string) ($file->getClientOriginalName() ?? ''));
        if ($original_name === '') {
            $original_name = $image_id.'.'.$ext;
        }

        $path = $dir.'/'.$image_id.'.'.$ext;
        $file->move($dir, $image_id.'.'.$ext);

        $width = null;
        $height = null;
        $size = @getimagesize($path);
        if (is_array($size)) {
            $width = isset($size[0]) ? (int) $size[0] : null;
            $height = isset($size[1]) ? (int) $size[1] : null;
        }

        return [
            'id' => $image_id,
            'filename' => $original_name,
            'mime' => $mime,
            'size_bytes' => (int) filesize($path),
            'width' => $width,
            'height' => $height,
            'url' => $this->imageUrl($act_id, $block_id, $image_id),
        ];
    }

    public function resolvePath(string $act_id, string $block_id, string $image_id): ?string
    {
        $image_id = trim($image_id);
        if ($image_id === '' || ! $this->isUuid($image_id)) {
            return null;
        }

        $dir = $this->blockImagesDir($act_id, $block_id);
        if (! is_dir($dir)) {
            return null;
        }

        $prefix = $image_id.'.';
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (str_starts_with($entry, $prefix)) {
                $path = $dir.'/'.$entry;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    public function imageExists(string $act_id, string $block_id, string $image_id): bool
    {
        return $this->resolvePath($act_id, $block_id, $image_id) !== null;
    }

    public function mimeForPath(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    /**
     * @return array<string, array<string, true>>
     */
    public function collectReferencedImageIds(string $act_id): array
    {
        $referenced = [];
        foreach (BlockApp::make()->list($act_id) as $block) {
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            if (($data['type'] ?? null) !== 'gallery') {
                continue;
            }

            $block_id = (string) ($block['id'] ?? '');
            if ($block_id === '') {
                continue;
            }

            if (! isset($referenced[$block_id])) {
                $referenced[$block_id] = [];
            }

            foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = trim((string) ($item['id'] ?? ''));
                if ($id !== '' && $this->isUuid($id)) {
                    $referenced[$block_id][$id] = true;
                }
            }
        }

        return $referenced;
    }

    /**
     * @return array{deleted_files: int, deleted_dirs: int}
     */
    public function reconcileImages(string $act_id): array
    {
        $referenced = $this->collectReferencedImageIds($act_id);
        $root = $this->imagesRoot($act_id);
        $deleted_files = 0;
        $deleted_dirs = 0;

        if (! is_dir($root)) {
            return ['deleted_files' => 0, 'deleted_dirs' => 0];
        }

        foreach (scandir($root) ?: [] as $block_entry) {
            if ($block_entry === '.' || $block_entry === '..') {
                continue;
            }

            $block_dir = $root.'/'.$block_entry;
            if (! is_dir($block_dir)) {
                continue;
            }

            $block_refs = $referenced[$block_entry] ?? [];

            foreach (scandir($block_dir) ?: [] as $file_entry) {
                if ($file_entry === '.' || $file_entry === '..') {
                    continue;
                }

                $file_path = $block_dir.'/'.$file_entry;
                if (! is_file($file_path)) {
                    continue;
                }

                $image_id = strtok($file_entry, '.');
                if ($image_id === false || $image_id === '' || ! isset($block_refs[$image_id])) {
                    unlink($file_path);
                    $deleted_files++;
                }
            }

            if ($this->isDirectoryEmpty($block_dir)) {
                rmdir($block_dir);
                $deleted_dirs++;
            }
        }

        if ($this->isDirectoryEmpty($root)) {
            rmdir($root);
            $deleted_dirs++;
            $assets_dir = dirname($root);
            if (is_dir($assets_dir) && $this->isDirectoryEmpty($assets_dir)) {
                rmdir($assets_dir);
                $deleted_dirs++;
            }
        }

        return [
            'deleted_files' => $deleted_files,
            'deleted_dirs' => $deleted_dirs,
        ];
    }

    private function detectMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '') {
            throw new \InvalidArgumentException('Не удалось прочитать загруженный файл');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new \RuntimeException('Не удалось определить тип файла');
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (! is_string($mime) || $mime === '') {
            throw new \InvalidArgumentException('Не удалось определить тип файла');
        }

        return $mime;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private function isDirectoryEmpty(string $dir): bool
    {
        if (! is_dir($dir)) {
            return true;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                return false;
            }
        }

        return true;
    }
}
