<?php

namespace App\Models;

use App\Enums\VoteChoice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['section_id', 'user_id', 'signature_id', 'vote', 'comment'])]
class Vote extends Model
{
    protected function casts(): array
    {
        return [
            'vote' => VoteChoice::class,
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
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
