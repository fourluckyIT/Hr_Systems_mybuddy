<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRule extends Model
{
    protected $fillable = ['rule_type', 'config', 'effective_date', 'is_active'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function getActiveRule(string $ruleType): ?self
    {
        return static::where('rule_type', $ruleType)
            ->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc')
            ->first();
    }
}
