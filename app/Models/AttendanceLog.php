<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id', 'log_date', 'day_type', 'check_in', 'check_out',
        'late_minutes', 'early_leave_minutes', 'ot_minutes', 'ot_enabled', 'lwop_flag', 'notes',
        'is_swapped_day', 'swapped_from_day_type', 'swapped_at', 'swapped_by',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'ot_enabled' => 'boolean',
            'lwop_flag' => 'boolean',
            'is_swapped_day' => 'boolean',
            'swapped_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getWorkingMinutesAttribute(): int
    {
        if (!$this->check_in || !$this->check_out) {
            return 0;
        }
        $start = \Carbon\Carbon::parse($this->check_in);
        $end = \Carbon\Carbon::parse($this->check_out);
        return max(0, $start->diffInMinutes($end));
    }
}
