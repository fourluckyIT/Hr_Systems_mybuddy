<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobReassignment extends Model
{
    protected $fillable = [
        'editing_job_id', 'old_assignee', 'new_assignee', 'reassigned_by', 'reason', 'reassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'reassigned_at' => 'datetime',
        ];
    }

    public function editingJob()
    {
        return $this->belongsTo(EditingJob::class);
    }

    public function oldEmployee()
    {
        return $this->belongsTo(Employee::class, 'old_assignee');
    }

    public function newEmployee()
    {
        return $this->belongsTo(Employee::class, 'new_assignee');
    }
}
