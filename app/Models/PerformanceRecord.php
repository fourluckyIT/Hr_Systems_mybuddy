<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'record_date',
        'month',
        'year',
        'work_title',
        'video_title',
        'layer',
        'hours',
        'minutes',
        'seconds',
        'quantity',
        'rate_snapshot',
        'amount_snapshot',
        'status',
        'action_select',
        'quality_score',
        'reject_reason',
        'confirmed_finished_at',
        'score',
        'category',
        'notes',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'confirmed_finished_at' => 'datetime',
            'score' => 'decimal:2',
            'quality_score' => 'decimal:1',
            'rate_snapshot' => 'decimal:4',
            'amount_snapshot' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
