<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'signature_id', 'action', 'model_type', 'model_id', 'changes', 'ip_address', 'user_agent', 'created_at'])]
class ActivityLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }
}
