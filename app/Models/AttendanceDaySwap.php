<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDaySwap extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_log_id',
        'log_date',
        'from_day_type',
        'to_day_type',
        'swap_reason',
        'swapped_by',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceLog()
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function swappedBy()
    {
        return $this->belongsTo(User::class, 'swapped_by');
    }
}
