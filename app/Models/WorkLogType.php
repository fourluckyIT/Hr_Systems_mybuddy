<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLogType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'module_key',
        'payroll_mode',
        'footage_size',
        'target_length_minutes',
        'default_rate_per_minute',
        'sort_order',
        'description',
        'config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'target_length_minutes' => 'decimal:2',
            'default_rate_per_minute' => 'decimal:4',
            'config' => 'array',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(WorkAssignment::class);
    }
}
