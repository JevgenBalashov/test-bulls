<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetMappingColumn extends Model
{
    public const DATA_TYPES = [
        'string',
        'text',
        'integer',
        'decimal',
        'boolean',
        'date',
        'datetime',
    ];

    protected $fillable = [
        'sheet_mapping_id',
        'source_column',
        'target_column',
        'data_type',
        'is_nullable',
        'is_unique_key',
    ];

    protected $casts = [
        'is_nullable' => 'boolean',
        'is_unique_key' => 'boolean',
    ];

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(SheetMapping::class, 'sheet_mapping_id');
    }
}
