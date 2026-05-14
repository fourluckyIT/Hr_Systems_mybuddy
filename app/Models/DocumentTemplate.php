<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'doc_type', 'kind', 'variant_key', 'name',
        'image_path', 'image_width', 'image_height',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentTemplateField::class, 'template_id')->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): string
    {
        // Use relative URL so it works regardless of APP_URL host/port mismatch
        return '/storage/' . ltrim($this->image_path, '/');
    }

    /**
     * Resolve the best-matching active template for a given doc + its variant.
     * Match order: exact (doc_type + variant_key) → fallback (doc_type + null/'default').
     */
    public static function resolveFor(string $docType, ?string $variantKey): ?self
    {
        $exact = static::where('doc_type', $docType)
            ->where('is_active', true)
            ->where('variant_key', $variantKey)
            ->first();
        if ($exact) return $exact;

        return static::where('doc_type', $docType)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('variant_key')->orWhere('variant_key', 'default');
            })
            ->first();
    }
}
