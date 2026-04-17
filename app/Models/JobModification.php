<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobModification extends Model
{
    protected $fillable = [
        'editing_job_id', 'modified_by', 'field_name', 'old_value', 'new_value', 'modified_at',
    ];

    protected function casts(): array
    {
        return [
            'modified_at' => 'datetime',
        ];
    }

    public function editingJob()
    {
        return $this->belongsTo(EditingJob::class);
    }
}
