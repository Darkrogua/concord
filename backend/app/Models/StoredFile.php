<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['information_block_id', 'name', 'path', 'size', 'mime_type'])]
class StoredFile extends Model
{
    protected $table = 'files';

    public function block(): BelongsTo
    {
        return $this->belongsTo(InformationBlock::class, 'information_block_id');
    }
}
