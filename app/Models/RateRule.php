<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateRule extends Model
{
    protected $fillable = ['employee_id', 'rate_type', 'rate', 'effective_date', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
