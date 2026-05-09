<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HolidayType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'default_color',
        'icon',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const COLOR_PRESETS = [
        'red'     => 'แดง',
        'orange'  => 'ส้ม',
        'amber'   => 'อำพัน',
        'yellow'  => 'เหลือง',
        'lime'    => 'ไลม์',
        'green'   => 'เขียว',
        'emerald' => 'เขียวมรกต',
        'teal'    => 'เทอร์ควอยซ์',
        'cyan'    => 'ฟ้าใส',
        'sky'     => 'ฟ้า',
        'blue'    => 'น้ำเงิน',
        'indigo'  => 'คราม',
        'violet'  => 'ม่วงคราม',
        'purple'  => 'ม่วง',
        'fuchsia' => 'บานเย็น',
        'pink'    => 'ชมพู',
        'rose'    => 'กุหลาบ',
        'slate'   => 'เทาเข้ม',
        'gray'    => 'เทา',
    ];

    public function companyHolidays(): HasMany
    {
        return $this->hasMany(CompanyHoliday::class);
    }
}
