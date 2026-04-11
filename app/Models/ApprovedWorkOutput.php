<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovedWorkOutput extends Model
{
    protected $fillable = [
        'edit_job_id', 'approved_by', 'title', 'platform',
        'publish_date', 'final_duration_seconds', 'notes',
    ];

    protected function casts(): array
    {
        return ['publish_date' => 'date'];
    }

    public function editJob()
    {
        return $this->belongsTo(EditJob::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
