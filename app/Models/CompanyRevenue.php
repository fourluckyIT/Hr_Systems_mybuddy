<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyRevenue extends Model
{
    protected $fillable = ['source', 'description', 'amount', 'month', 'year'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
