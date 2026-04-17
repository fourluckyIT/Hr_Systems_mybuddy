<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusCycleSelectedMonth extends Model
{
    protected $fillable = [
        'cycle_id',
        'selected_year',
        'selected_month',
        'selected_by',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_year' => 'integer',
            'selected_month' => 'integer',
            'selected_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BonusCycle::class, 'cycle_id');
    }
}
