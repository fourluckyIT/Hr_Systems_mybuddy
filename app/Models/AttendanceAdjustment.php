<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAdjustment extends Model
{
    protected $fillable = [
        'employee_id',
        'cycle_id',
        'absent_days',
        'late_count',
        'leave_days',
        'total_adjustment',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'absent_days'      => 'integer',
            'late_count'       => 'integer',
            'leave_days'       => 'integer',
            'total_adjustment' => 'decimal:4',
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
}
