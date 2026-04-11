<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeBankAccount extends Model
{
    protected $fillable = [
        'employee_id', 'bank_name', 'account_number', 'account_name', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
