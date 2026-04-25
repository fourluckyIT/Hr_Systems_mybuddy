<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyRevenue extends Model
{
    protected $fillable = [
        'source', 'description', 'amount', 'month', 'year',
        'expense_category_id', 'entry_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    public function categoryRef()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
