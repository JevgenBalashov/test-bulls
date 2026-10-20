<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SheetSource extends Model
{
    protected $fillable = [
        'name',
        'spreadsheet_id',
        'is_active',
        'last_imported_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_imported_at' => 'datetime',
    ];

    public function tabs(): HasMany
    {
        return $this->hasMany(SheetTab::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ImportRun::class);
    }
}
