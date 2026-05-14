<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateField extends Model
{
    protected $fillable = [
        'template_id', 'field_key',
        'x', 'y', 'width',
        'font_size', 'font_weight', 'align',
        'is_checkbox', 'checkbox_when',
        'sort_order',
    ];

    protected $casts = [
        'x' => 'integer', 'y' => 'integer',
        'width' => 'integer',
        'font_size' => 'integer',
        'is_checkbox' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
