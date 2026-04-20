<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeadlineNotification extends Model
{
    protected $fillable = [
        'editing_job_id', 'employee_id', 'notification_type', 'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function editingJob()
    {
        return $this->belongsTo(EditingJob::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
