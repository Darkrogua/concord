<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groups = $request->user()->userGroups()->with('users')->orderBy('name')->get();

        return response()->json(['data' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_ids' => ['array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $group = $request->user()->userGroups()->create(['name' => $data['name']]);
        if (! empty($data['user_ids'])) {
            $group->users()->sync($data['user_ids']);
        }

        return response()->json(['data' => $group->load('users')], 201);
    }

    public function update(Request $request, UserGroup $userGroup): JsonResponse
    {
        abort_unless($userGroup->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'user_ids' => ['array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $userGroup->update(['name' => $data['name'] ?? $userGroup->name]);
        if (array_key_exists('user_ids', $data)) {
            $userGroup->users()->sync($data['user_ids']);
        }

        return response()->json(['data' => $userGroup->load('users')]);
    }

    public function destroy(Request $request, UserGroup $userGroup): JsonResponse
    {
        abort_unless($userGroup->user_id === $request->user()->id, 403);
        $userGroup->delete();

        return response()->json(['ok' => true]);
    }

    public function members(Request $request, UserGroup $userGroup): JsonResponse
    {
        abort_unless($userGroup->user_id === $request->user()->id, 403);

        return response()->json(['data' => $userGroup->users]);
    }
}
