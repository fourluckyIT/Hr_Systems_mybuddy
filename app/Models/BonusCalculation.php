<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonusCalculation extends Model
{
    protected $fillable = [
        'employee_id',
        'cycle_id',
        'base_reference',
        'tier_id',
        'tier_multiplier',
        'tier_adjusted_bonus',
        'attendance_adjustment',
        'final_bonus_net',
        'months_after_probation',
        'unlock_percentage',
        'actual_payment',
        'is_active_on_payment',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'base_reference'         => 'decimal:2',
            'tier_multiplier'        => 'decimal:3',
            'tier_adjusted_bonus'    => 'decimal:2',
            'attendance_adjustment'  => 'decimal:4',
            'final_bonus_net'        => 'decimal:2',
            'months_after_probation' => 'integer',
            'unlock_percentage'      => 'decimal:4',
            'actual_payment'         => 'decimal:2',
            'is_active_on_payment'   => 'boolean',
            'approved_at'            => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BonusCycle::class, 'cycle_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(PerformanceTier::class, 'tier_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BonusAuditLog::class, 'calculation_id');
    }
}
