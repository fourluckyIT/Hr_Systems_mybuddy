<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'payslip_header_subtitle',
        'address',
        'phone',
        'email',
        'tax_id',
        'primary_color',
        'secondary_color',
        'text_color',
        'payslip_header_note',
        'payslip_footer_text',
        'signature_approver_name',
        'signature_approver_image_path',
        'signature_receiver_name',
        'signature_receiver_image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the active company profile (usually only one)
     */
    public static function active()
    {
        return static::where('is_active', true)->first() 
            ?? static::first();
    }
}
