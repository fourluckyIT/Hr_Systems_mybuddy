<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordingJob extends Model
{
    protected $fillable = [
        'title', 'game_type', 'game', 'map', 'scheduled_date', 'scheduled_time', 'planned_duration_minutes',
        'status', 'priority', 'notes', 'footage_count', 'longest_footage_seconds', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'footage_count' => 'integer',
            'longest_footage_seconds' => 'integer',
        ];
    }

    public function assignees()
    {
        return $this->hasMany(RecordingJobAssignee::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'recording_job_assignees')->withPivot('role')->withTimestamps();
    }

    public function mediaResources()
    {
        return $this->hasMany(MediaResource::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
