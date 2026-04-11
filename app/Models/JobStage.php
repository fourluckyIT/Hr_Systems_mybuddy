<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'code',
        'name',
        'color',
        'is_core',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_core' => 'boolean',
        'is_active' => 'boolean',
    ];
}
