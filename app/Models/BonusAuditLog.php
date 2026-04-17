<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'calculation_id',
        'action_type',
        'old_value',
        'new_value',
        'changed_by',
        'changed_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_value'  => 'array',
            'new_value'  => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(BonusCalculation::class, 'calculation_id');
    }
}
