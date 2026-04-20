<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionCost extends Model
{
    protected $fillable = ['name', 'amount', 'is_recurring', 'month', 'year'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_recurring' => 'boolean',
        ];
    }
}
