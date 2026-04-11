<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'work_log_type_id',
        'assigned_date',
        'due_date',
        'status',
        'priority',
        'notes',
        'assigned_by',
        'completed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function workType()
    {
        return $this->belongsTo(WorkLogType::class, 'work_log_type_id');
    }
}
