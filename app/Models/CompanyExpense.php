<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyExpense extends Model
{
    protected $fillable = ['category', 'description', 'amount', 'month', 'year'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
