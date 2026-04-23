<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyExpense extends Model
{
    protected $fillable = [
        'category', 'description', 'amount', 'month', 'year',
        'expense_category_id', 'entry_date', 'is_recurring', 'status', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    public function categoryRef()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
