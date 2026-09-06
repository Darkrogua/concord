<?php

namespace App\Models;

use App\Enums\CompletionCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'agreement_id',
    'name',
    'order',
    'completion_condition',
    'show_results_before_vote',
    'participants_see_each_other',
    'is_approved',
    'approved_at',
])]
class Section extends Model
{
    protected function casts(): array
    {
        return [
            'show_results_before_vote' => 'boolean',
            'participants_see_each_other' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'completion_condition' => CompletionCondition::class,
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SectionParticipant::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, SectionParticipant::class, 'section_id', 'id', 'id', 'user_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(InformationBlock::class)->orderBy('order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
