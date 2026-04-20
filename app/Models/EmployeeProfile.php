<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'employee_id', 'id_card', 'address', 'phone', 'email', 'photo', 'birth_date',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
