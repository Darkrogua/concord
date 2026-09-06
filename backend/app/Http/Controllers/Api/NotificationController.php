<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json([
            'data' => $items->items(),
            'unread' => UserNotification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function markRead(Request $request, UserNotification $userNotification): JsonResponse
    {
        abort_unless($userNotification->user_id === $request->user()->id, 403);
        $userNotification->update(['read_at' => now()]);

        return response()->json(['data' => $userNotification]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
