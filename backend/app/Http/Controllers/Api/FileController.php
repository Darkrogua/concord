<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InformationBlock;
use App\Models\StoredFile;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(private FileService $files) {}

    public function store(Request $request, InformationBlock $informationBlock): JsonResponse
    {
        $this->authorize('update', $informationBlock->section);
        $request->validate(['file' => ['required', 'file', 'max:51200']]);

        return response()->json(['data' => $this->files->upload($informationBlock, $request->file('file'))], 201);
    }

    public function download(StoredFile $storedFile): StreamedResponse
    {
        $storedFile->load('block.section.agreement');
        $this->authorize('view', $storedFile->block->section);

        return Storage::disk('local')->download($storedFile->path, $storedFile->name);
    }

    public function destroy(StoredFile $storedFile): JsonResponse
    {
        $storedFile->load('block.section');
        $this->authorize('update', $storedFile->block->section);
        $this->files->delete($storedFile);

        return response()->json(['ok' => true]);
    }
}
