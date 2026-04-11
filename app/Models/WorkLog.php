<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'month', 'year', 'log_date', 'work_type',
        'layer', 'hours', 'minutes', 'seconds', 'quantity',
        'rate', 'amount', 'pricing_mode', 'custom_rate', 'pricing_template_label',
        'sort_order', 'notes', 'entry_type', 'is_disabled',
        'source_flag', 'edit_job_id',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'custom_rate' => 'decimal:4',
            'is_disabled' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function editJob()
    {
        return $this->belongsTo(EditJob::class);
    }

    public function getDurationMinutesAttribute(): float
    {
        return ($this->hours * 60) + $this->minutes + ($this->seconds / 60);
    }
}
