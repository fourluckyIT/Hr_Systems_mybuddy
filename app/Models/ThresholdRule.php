<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThresholdRule extends Model
{
    protected $fillable = [
        'name', 'metric', 'operator', 'threshold_value', 'result_action', 'result_value', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'decimal:2',
            'result_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
