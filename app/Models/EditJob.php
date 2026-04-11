<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditJob extends Model
{
    protected $fillable = [
        'media_resource_id', 'assigned_to', 'title', 'status', 'priority',
        'due_date', 'finished_date', 'output_duration_seconds',
        'output_notes', 'notes', 'created_by',
        'pricing_group', 'pricing_template_label', 'assigned_rate',
        'assigned_quantity', 'assigned_fixed_rate',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'finished_date' => 'date',
            'assigned_rate' => 'decimal:4',
            'assigned_fixed_rate' => 'decimal:2',
        ];
    }

    public function mediaResource()
    {
        return $this->belongsTo(MediaResource::class);
    }

    public function editor()
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedOutputs()
    {
        return $this->hasMany(ApprovedWorkOutput::class);
    }
}
