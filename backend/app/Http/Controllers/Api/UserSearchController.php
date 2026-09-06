<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%");
            }))
            ->whereKeyNot($request->user()->id)
            ->with('signatures')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }
}
