<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    protected $fillable = [
        'sheet_source_id',
        'status',
        'started_at',
        'finished_at',
        'rows_read',
        'rows_written',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'rows_read' => 'integer',
        'rows_written' => 'integer',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(SheetSource::class, 'sheet_source_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImportRunItem::class);
    }
}
