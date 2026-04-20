<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleToggle extends Model
{
    protected $fillable = ['employee_id', 'module_name', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
