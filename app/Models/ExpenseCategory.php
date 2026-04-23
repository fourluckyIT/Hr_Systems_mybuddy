<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'type', 'color', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeIncome($q)
    {
        return $q->where('type', 'income');
    }

    public function scopeExpense($q)
    {
        return $q->where('type', 'expense');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
