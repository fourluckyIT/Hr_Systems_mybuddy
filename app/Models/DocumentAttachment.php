<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class DocumentAttachment extends Model
{
    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_by',
        'note',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function getSizeKbAttribute(): float
    {
        return round($this->size_bytes / 1024, 1);
    }
}
