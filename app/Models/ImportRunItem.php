<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRunItem extends Model
{
    protected $fillable = [
        'import_run_id',
        'sheet_mapping_id',
        'tab_name',
        'target_table',
        'status',
        'rows_read',
        'rows_written',
        'rows_skipped',
        'added_columns',
        'error_message',
    ];

    protected $casts = [
        'rows_read' => 'integer',
        'rows_written' => 'integer',
        'rows_skipped' => 'integer',
        'added_columns' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(SheetMapping::class, 'sheet_mapping_id');
    }
}
