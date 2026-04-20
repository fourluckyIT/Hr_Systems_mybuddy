<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollBatch extends Model
{
    protected $fillable = ['month', 'year', 'status', 'created_by', 'finalized_at'];

    protected function casts(): array
    {
        return ['finalized_at' => 'datetime'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }
}
