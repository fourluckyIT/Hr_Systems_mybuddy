<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaResource extends Model
{
    protected $fillable = [
        'recording_job_id', 'footage_code', 'title',
        'raw_length_seconds', 'status', 'footage_count', 'notes',
    ];

    public function recordingJob()
    {
        return $this->belongsTo(RecordingJob::class);
    }
}
