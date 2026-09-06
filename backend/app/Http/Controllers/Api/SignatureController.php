<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Signature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SignatureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->signatures()->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $signature = $request->user()->signatures()->create([
            'name' => $data['name'],
            'is_active' => $request->user()->signatures()->count() === 0,
        ]);

        return response()->json(['data' => $signature], 201);
    }

    public function update(Request $request, Signature $signature): JsonResponse
    {
        $this->authorizeSignature($request, $signature);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $signature->update($data);

        return response()->json(['data' => $signature]);
    }

    public function destroy(Request $request, Signature $signature): JsonResponse
    {
        $this->authorizeSignature($request, $signature);

        if ($request->user()->signatures()->count() <= 1) {
            throw ValidationException::withMessages(['signature' => 'Нельзя удалить последнюю подпись.']);
        }

        $wasActive = $signature->is_active;
        $signature->delete();

        if ($wasActive) {
            $request->user()->signatures()->first()?->update(['is_active' => true]);
        }

        return response()->json(['ok' => true]);
    }

    public function activate(Request $request, Signature $signature): JsonResponse
    {
        $this->authorizeSignature($request, $signature);

        $request->user()->signatures()->update(['is_active' => false]);
        $signature->update(['is_active' => true]);

        return response()->json(['data' => $signature]);
    }

    private function authorizeSignature(Request $request, Signature $signature): void
    {
        abort_unless($signature->user_id === $request->user()->id, 403);
    }
}
