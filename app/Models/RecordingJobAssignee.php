<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordingJobAssignee extends Model
{
    protected $fillable = ['recording_job_id', 'employee_id', 'role'];

    public function recordingJob()
    {
        return $this->belongsTo(RecordingJob::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
