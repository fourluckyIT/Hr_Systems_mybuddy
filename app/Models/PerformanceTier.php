<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceTier extends Model
{
    protected $fillable = [
        'tier_code',
        'tier_name',
        'multiplier',
        'min_clip_minutes_per_month',
        'max_clip_minutes_per_month',
        'min_qualified_months',
        'max_qualified_months',
        'description',
        'display_order',
        'is_active',
        'auto_select_enabled',
    ];

    protected function casts(): array
    {
        return [
            'multiplier'    => 'decimal:3',
            'min_clip_minutes_per_month' => 'integer',
            'max_clip_minutes_per_month' => 'integer',
            'min_qualified_months' => 'integer',
            'max_qualified_months' => 'integer',
            'display_order' => 'integer',
            'is_active'     => 'boolean',
            'auto_select_enabled' => 'boolean',
        ];
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(BonusCalculation::class, 'tier_id');
    }
}
