<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayerRateRule extends Model
{
    protected $fillable = [
        'employee_id', 'layer_from', 'layer_to', 'rate_per_minute', 'effective_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_minute' => 'decimal:4',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
