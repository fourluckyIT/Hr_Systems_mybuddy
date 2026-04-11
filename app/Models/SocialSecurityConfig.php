<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialSecurityConfig extends Model
{
    protected $fillable = [
        'effective_date', 'employee_rate', 'employer_rate',
        'salary_ceiling', 'max_contribution', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'employee_rate' => 'decimal:2',
            'employer_rate' => 'decimal:2',
            'salary_ceiling' => 'decimal:2',
            'max_contribution' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public static function getCurrentConfig(): ?self
    {
        return static::where('is_active', true)
            ->where('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc')
            ->first();
    }
}
