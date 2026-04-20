<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayslipItem extends Model
{
    protected $fillable = ['payslip_id', 'category', 'label', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payslip()
    {
        return $this->belongsTo(Payslip::class);
    }
}
