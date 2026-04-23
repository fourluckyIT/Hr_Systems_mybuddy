<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraIncomeEntry extends Model
{
    protected $fillable = [
        'employee_id', 'month', 'year', 'label', 'category',
        'amount', 'include_in_payslip', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'include_in_payslip' => 'boolean',
            'month' => 'integer',
            'year' => 'integer',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
