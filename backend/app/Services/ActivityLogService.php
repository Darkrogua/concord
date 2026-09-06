<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ActivityLogService
{
    public function log(?User $user, string $action, Model $model, array $changes = []): ActivityLog
    {
        $entry = ActivityLog::query()->create([
            'user_id' => $user?->id,
            'signature_id' => $user?->activeSignature()?->id,
            'action' => $action,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);

        Log::info('audit.'.$action, [
            'event' => 'audit.'.$action,
            'user_id' => $user?->id,
            'model' => $model::class,
            'model_id' => $model->getKey(),
            'changes' => $changes ?: null,
        ]);

        return $entry;
    }
}
