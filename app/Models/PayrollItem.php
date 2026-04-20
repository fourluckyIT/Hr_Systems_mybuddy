<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $fillable = [
        'employee_id', 'payroll_batch_id', 'item_type_code', 'category',
        'label', 'amount', 'source_flag', 'sort_order', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollBatch()
    {
        return $this->belongsTo(PayrollBatch::class);
    }
}
