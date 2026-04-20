<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryProfile extends Model
{
    protected $fillable = [
        'employee_id', 'base_salary', 'effective_date', 'notes', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'effective_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
