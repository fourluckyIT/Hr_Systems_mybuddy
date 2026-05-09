<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyHoliday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'holiday_type_id',
        'color',
        'is_active',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function holidayType(): BelongsTo
    {
        return $this->belongsTo(HolidayType::class);
    }

    /**
     * Resolve the color: use override `color` if set, else fall back to type's default.
     */
    public function getEffectiveColorAttribute(): string
    {
        if (!empty($this->color)) {
            return $this->color;
        }
        return $this->holidayType?->default_color ?? 'purple';
    }

    /**
     * Tailwind class string for backgrounds/badges in calendar views.
     */
    public function getEffectiveColorClassesAttribute(): string
    {
        $c = $this->effective_color;
        return "bg-{$c}-100 text-{$c}-800 border-{$c}-200";
    }

    public function getEffectiveIconAttribute(): string
    {
        return $this->holidayType?->icon ?? '🏢';
    }
}
