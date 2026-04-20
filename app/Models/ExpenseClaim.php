<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseClaim extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'employee_id', 
        'description', 
        'amount', 
        'type', 
        'claim_date', 
        'status', 
        'month', 
        'year', 
        'approved_at'
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'claim_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
