<?php namespace Zen\Act\Classes\Support;

use Zen\Act\Classes\System\AccessApp;
use Zen\Act\Classes\System\SnapshotApp;
use Zen\Act\Models\Act;

/**
 * Файловое хранилище актов: storage/acts/{uuid}/current.json + act.sqlite.
 */
class ActStorage
{
    public static function make(): self
    {
        return new self();
    }

    public function actDirectory(string $actId): string
    {
        return storage_path('acts/'.$actId);
    }

    public function currentFilePath(string $actId): string
    {
        return $this->actDirectory($actId).'/current.json';
    }

    /** @deprecated use currentFilePath */
    public function restoreFilePath(string $actId): string
    {
        return $this->currentFilePath($actId);
    }

    /**
     * @return list<string> UUID каталогов с current.json или legacy restore.json
     */
    public function listActDirectoriesWithCurrent(): array
    {
        $root = storage_path('acts');
        if (! is_dir($root)) {
            return [];
        }

        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }

        $ids = [];
        foreach ($entries as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $dir = $root.'/'.$item;
            if (! is_dir($dir)) {
                continue;
            }

            if (is_file($dir.'/current.json') || is_file($dir.'/restore.json')) {
                $ids[] = $item;
            }
        }

        sort($ids);

        return $ids;
    }

    /** @deprecated use listActDirectoriesWithCurrent */
    public function listActDirectoriesWithRestore(): array
    {
        return $this->listActDirectoriesWithCurrent();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readCurrentFile(string $actId): ?array
    {
        $current = $this->readSnapshotFile($this->currentFilePath($actId));
        if ($current !== null) {
            return $current;
        }

        $legacy = $this->actDirectory($actId).'/restore.json';

        return $this->readSnapshotFile($legacy);
    }

    /** @deprecated use readCurrentFile */
    public function readRestoreFile(string $actId): ?array
    {
        return $this->readCurrentFile($actId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function writeCurrentFile(string $actId, array $payload): void
    {
        $dir = $this->actDirectory($actId);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $this->currentFilePath($actId);
        $tmp = $path.'.tmp';
        Transformers::make()->arrayToFile($payload, $tmp);
        rename($tmp, $path);

        $legacy = $dir.'/restore.json';
        if (is_file($legacy)) {
            unlink($legacy);
        }
    }

    /** @deprecated use writeCurrentFile */
    public function writeRestoreFile(string $actId, array $payload): void
    {
        $this->writeCurrentFile($actId, $payload);
    }

    public function removeCurrentFile(string $actId): void
    {
        foreach ([$this->currentFilePath($actId), $this->actDirectory($actId).'/restore.json'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @deprecated use removeCurrentFile */
    public function removeRestoreFile(string $actId): void
    {
        $this->removeCurrentFile($actId);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array{meta?: array<string, string>, grants?: list<array<string, mixed>>}  $access
     * @param  list<string>  $owner_user_tags
     * @return array<string, mixed>
     */
    public function snapshotFromAct(Act $act, array $blocks = [], array $access = [], array $owner_user_tags = []): array
    {
        return ActEnvelope::make()->build($act, $blocks, $access, $owner_user_tags);
    }

    public function writeInitialState(Act $act): int
    {
        AccessApp::make()->dumpForSnapshot((string) $act->id);

        return SnapshotApp::make()->appendInitialCheckpoint((string) $act->id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readSnapshotFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $data = Transformers::make()->arrayFromFile($path);

        return is_array($data) ? $data : null;
    }

    public function removeActDirectory(string $actId): void
    {
        $dir = $this->actDirectory($actId);
        if (! is_dir($dir)) {
            return;
        }

        $this->removeDirectoryRecursive($dir);
    }

    public function removeLegacyStatesDirectory(string $actId): void
    {
        $statesRoot = $this->actDirectory($actId).'/states';
        if (! is_dir($statesRoot)) {
            return;
        }

        $this->removeDirectoryRecursive($statesRoot);
    }

    private function removeDirectoryRecursive(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursive($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
