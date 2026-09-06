<?php

namespace App\Services;

use App\Models\InformationBlock;
use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FileService
{
    public function upload(InformationBlock $block, UploadedFile $file): StoredFile
    {
        if ($file->getSize() > 50 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Файл не больше 50 МБ.']);
        }

        $path = $file->store("blocks/{$block->id}", 'local');

        $stored = StoredFile::query()->create([
            'information_block_id' => $block->id,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
        ]);

        Log::info('file.uploaded', [
            'event' => 'file.uploaded',
            'file_id' => $stored->id,
            'block_id' => $block->id,
            'name' => $stored->name,
            'size' => $stored->size,
        ]);

        return $stored;
    }

    public function delete(StoredFile $file): void
    {
        Log::info('file.deleted', [
            'event' => 'file.deleted',
            'file_id' => $file->id,
            'path' => $file->path,
        ]);
        Storage::disk('local')->delete($file->path);
        $file->delete();
    }
}
