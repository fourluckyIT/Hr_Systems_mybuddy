<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtRequest extends Model
{
    protected $fillable = [
        'employee_id', 'log_date', 'requested_minutes', 'reason', 'job_reference',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'reviewed_at' => 'datetime',
            'requested_minutes' => 'integer',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeForMonth($q, int $month, int $year)
    {
        return $q->whereMonth('log_date', $month)->whereYear('log_date', $year);
    }
}
