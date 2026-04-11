<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaResource extends Model
{
    protected $fillable = [
        'recording_job_id', 'footage_code', 'title', 'footage_count',
        'raw_length_seconds', 'usable_length_seconds',
        'status', 'usage_count', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'footage_count' => 'integer',
        ];
    }

    public function recordingJob()
    {
        return $this->belongsTo(RecordingJob::class);
    }

    public function editJobs()
    {
        return $this->hasMany(EditJob::class);
    }

    public function getRawDurationAttribute(): string
    {
        if (!$this->raw_length_seconds) return '-';
        return gmdate('H:i:s', $this->raw_length_seconds);
    }

    public function getUsableDurationAttribute(): string
    {
        if (!$this->usable_length_seconds) return '-';
        return gmdate('H:i:s', $this->usable_length_seconds);
    }
}
