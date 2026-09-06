<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgreementGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgreementGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->agreementGroups()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
        ]);

        $group = $request->user()->agreementGroups()->create([
            'name' => $data['name'],
            'filters' => $data['filters'] ?? [],
            'is_default' => false,
        ]);

        return response()->json(['data' => $group], 201);
    }

    public function update(Request $request, AgreementGroup $agreementGroup): JsonResponse
    {
        abort_unless($agreementGroup->user_id === $request->user()->id, 403);
        abort_if($agreementGroup->is_default, 422, 'Системную группу нельзя менять.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
        ]);
        $agreementGroup->update($data);

        return response()->json(['data' => $agreementGroup]);
    }

    public function destroy(Request $request, AgreementGroup $agreementGroup): JsonResponse
    {
        abort_unless($agreementGroup->user_id === $request->user()->id, 403);
        abort_if($agreementGroup->is_default, 422, 'Системную группу нельзя удалить.');
        $agreementGroup->delete();

        return response()->json(['ok' => true]);
    }
}
