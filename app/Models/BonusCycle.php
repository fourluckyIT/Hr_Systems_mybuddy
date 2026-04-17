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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cycle_year'     => 'integer',
            'payment_date'   => 'date',
            'max_allocation' => 'decimal:2',
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
