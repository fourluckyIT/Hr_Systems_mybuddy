<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItemType extends Model
{
    protected $fillable = ['code', 'label_th', 'label_en', 'category', 'is_system', 'sort_order'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
