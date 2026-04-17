<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonusCycle extends Model
{
    protected $fillable = [
        'cycle_code',
        'cycle_year',
        'cycle_period',
        'payment_date',
        'max_allocation',
        'june_max_ratio',
        'june_scale_months',
        'full_scale_months',
        'absent_penalty_per_day',
        'late_penalty_per_occurrence',
        'leave_free_days',
        'leave_penalty_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cycle_year'                 => 'integer',
            'payment_date'               => 'date',
            'max_allocation'             => 'decimal:2',
            'june_max_ratio'             => 'decimal:3',
            'june_scale_months'          => 'integer',
            'full_scale_months'          => 'integer',
            'absent_penalty_per_day'     => 'decimal:4',
            'late_penalty_per_occurrence'=> 'decimal:4',
            'leave_free_days'            => 'integer',
            'leave_penalty_rate'         => 'decimal:4',
        ];
    }

    /** Return unlock rule config with sensible defaults. */
    public function unlockConfig(): array
    {
        return [
            'june_max_ratio'    => (float) ($this->june_max_ratio ?? 0.4),
            'june_scale_months' => (int)   ($this->june_scale_months ?? 6),
            'full_scale_months' => (int)   ($this->full_scale_months ?? 12),
        ];
    }

    /** Return attendance adjustment penalty config with sensible defaults. */
    public function attendancePenaltyConfig(): array
    {
        return [
            'absent_penalty_per_day'      => (float) ($this->absent_penalty_per_day ?? -0.01),
            'late_penalty_per_occurrence' => (float) ($this->late_penalty_per_occurrence ?? -0.002),
            'leave_free_days'             => (int)   ($this->leave_free_days ?? 5),
            'leave_penalty_rate'          => (float) ($this->leave_penalty_rate ?? 0.01),
        ];
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(BonusCalculation::class, 'cycle_id');
    }

    public function attendanceAdjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class, 'cycle_id');
    }

    public function selectedMonths(): HasMany
    {
        return $this->hasMany(BonusCycleSelectedMonth::class, 'cycle_id');
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'closed']);
    }

    public function canCalculate(): bool
    {
        return in_array($this->status, ['draft', 'calculating', 'rejected']);
    }
}
