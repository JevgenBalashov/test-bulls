<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SheetTab extends Model
{
    protected $fillable = [
        'sheet_source_id',
        'tab_name',
        'header_row',
        'data_start_row',
        'is_active',
    ];

    protected $casts = [
        'header_row' => 'integer',
        'data_start_row' => 'integer',
        'is_active' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(SheetSource::class, 'sheet_source_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(SheetMapping::class);
    }
}
