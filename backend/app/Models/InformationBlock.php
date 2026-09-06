<?php

namespace App\Models;

use App\Enums\BlockType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['section_id', 'type', 'title', 'content', 'order'])]
class InformationBlock extends Model
{
    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'content' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(StoredFile::class, 'information_block_id');
    }
}
