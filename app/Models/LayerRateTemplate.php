<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayerRateTemplate extends Model
{
    protected $fillable = [
        'label', 'layer_from', 'layer_to', 'rate_per_minute', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_minute' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
