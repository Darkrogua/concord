<?php

namespace App\Models;

use App\Enums\AgreementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'description',
    'author_id',
    'signature_id',
    'project_id',
    'deadline',
    'publish_date',
    'status',
    'is_approved',
    'completed_at',
    'public_token',
])]
class Agreement extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'publish_date' => 'datetime',
            'completed_at' => 'datetime',
            'status' => AgreementStatus::class,
            'is_approved' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Agreement $agreement): void {
            if (! $agreement->public_token) {
                $agreement->public_token = Str::random(40);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function votes(): HasManyThrough
    {
        return $this->hasManyThrough(Vote::class, Section::class);
    }

    public function scopeNotTrashedStatus(Builder $query): Builder
    {
        return $query->where('status', '!=', AgreementStatus::Deleted);
    }
}
