<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $fillable = [
        'employee_id', 'payroll_batch_id', 'month', 'year',
        'total_income', 'total_deduction', 'net_pay',
        'status', 'finalized_at', 'finalized_by', 'payment_date', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'total_income' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'finalized_at' => 'datetime',
            'payment_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollBatch()
    {
        return $this->belongsTo(PayrollBatch::class);
    }

    public function items()
    {
        return $this->hasMany(PayslipItem::class)->orderBy('sort_order');
    }

    public function incomeItems()
    {
        return $this->hasMany(PayslipItem::class)->where('category', 'income')->orderBy('sort_order');
    }

    public function deductionItems()
    {
        return $this->hasMany(PayslipItem::class)->where('category', 'deduction')->orderBy('sort_order');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
