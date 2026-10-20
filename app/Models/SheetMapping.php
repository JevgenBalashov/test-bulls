<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SheetMapping extends Model
{
    protected $fillable = [
        'sheet_tab_id',
        'target_table',
        'write_mode',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tab(): BelongsTo
    {
        return $this->belongsTo(SheetTab::class, 'sheet_tab_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(SheetMappingColumn::class)->orderBy('id');
    }

    public function uniqueKeyColumns(): HasMany
    {
        return $this->columns()->where('is_unique_key', true);
    }
}
