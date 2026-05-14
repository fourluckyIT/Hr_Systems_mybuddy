<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\CompanyProfile;
use Carbon\Carbon;

/**
 * Catalog of dynamic fields per document type.
 *
 * Each entry: 'field_key' => ['label' => display name, 'resolve' => closure($doc): string]
 * Resolver gets the document model; returns the string to render at (x,y).
 *
 * To add a new field for a doc type:
 *   1. Add an entry under that type's array in `fields()`
 *   2. Done — UI editor's dropdown picks it up automatically, renderer resolves it on print
 */
class DocumentFieldCatalog
{
    public const THAI_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    public static function fields(string $docType): array
    {
        $common = [
            'company_name' => [
                'label' => 'ชื่อบริษัท',
                'resolve' => fn($doc) => CompanyProfile::active()?->name ?? '',
            ],
            'employee_name' => [
                'label' => 'ชื่อ-สกุล พนักงาน',
                'resolve' => fn($doc) => trim(($doc->employee->first_name ?? '') . ' ' . ($doc->employee->last_name ?? '')),
            ],
            'employee_code' => [
                'label' => 'รหัสพนักงาน',
                'resolve' => fn($doc) => $doc->employee->employee_code ?? '',
            ],
            'employee_position' => [
                'label' => 'ตำแหน่ง',
                'resolve' => fn($doc) => $doc->employee->position?->name ?? '',
            ],
            'employee_department' => [
                'label' => 'แผนก',
                'resolve' => fn($doc) => $doc->employee->department?->name ?? '',
            ],
            'document_number' => [
                'label' => 'เลขที่เอกสาร',
                'resolve' => fn($doc) => $doc->document_number ?? '',
            ],
            'created_day' => [
                'label' => 'วันที่เขียน (วัน)',
                'resolve' => fn($doc) => optional($doc->created_at)->format('d') ?? '',
            ],
            'created_month_th' => [
                'label' => 'วันที่เขียน (เดือนไทย)',
                'resolve' => fn($doc) => $doc->created_at ? self::THAI_MONTHS[(int) $doc->created_at->format('m')] : '',
            ],
            'created_year_be' => [
                'label' => 'วันที่เขียน (ปี พ.ศ.)',
                'resolve' => fn($doc) => $doc->created_at ? (string) ((int) $doc->created_at->format('Y') + 543) : '',
            ],
            'created_date_th' => [
                'label' => 'วันที่เขียน (เต็ม ไทย)',
                'resolve' => fn($doc) => $doc->created_at
                    ? $doc->created_at->format('d') . ' ' . self::THAI_MONTHS[(int) $doc->created_at->format('m')] . ' ' . ((int) $doc->created_at->format('Y') + 543)
                    : '',
            ],
        ];

        $perType = match ($docType) {
            'leave' => [
                'leave_type_label' => [
                    'label' => 'ประเภทการลา',
                    'resolve' => fn($doc) => \App\Models\Employee::LEAVE_TYPE_LABELS[$doc->leave_type] ?? $doc->leave_type,
                ],
                'leave_type_key' => [
                    'label' => 'ประเภทการลา (key) — สำหรับ checkbox',
                    'resolve' => fn($doc) => $doc->leave_type,
                ],
                'leave_date_th' => [
                    'label' => 'วันที่ลา (เต็ม ไทย)',
                    'resolve' => fn($doc) => $doc->leave_date
                        ? Carbon::parse($doc->leave_date)->format('d') . ' ' . self::THAI_MONTHS[(int) Carbon::parse($doc->leave_date)->format('m')] . ' ' . ((int) Carbon::parse($doc->leave_date)->format('Y') + 543)
                        : '',
                ],
                'leave_date_from' => [
                    'label' => 'วันที่ลา - ตั้งแต่ (เต็ม ไทย) — alias ของ leave_date_th',
                    'resolve' => fn($doc) => $doc->leave_date
                        ? Carbon::parse($doc->leave_date)->format('d') . ' ' . self::THAI_MONTHS[(int) Carbon::parse($doc->leave_date)->format('m')] . ' ' . ((int) Carbon::parse($doc->leave_date)->format('Y') + 543)
                        : '',
                ],
                'leave_date_to' => [
                    'label' => 'วันที่ลา - ถึง (เต็ม ไทย) — ตอนนี้เท่ากับ from (ลา 1 วัน/ใบ)',
                    'resolve' => fn($doc) => $doc->leave_date
                        ? Carbon::parse($doc->leave_date)->format('d') . ' ' . self::THAI_MONTHS[(int) Carbon::parse($doc->leave_date)->format('m')] . ' ' . ((int) Carbon::parse($doc->leave_date)->format('Y') + 543)
                        : '',
                ],
                'leave_date_dmy' => [
                    'label' => 'วันที่ลา (d/m/Y)',
                    'resolve' => fn($doc) => optional($doc->leave_date)->format('d/m/Y') ?? '',
                ],
                'leave_date_from_dmy' => [
                    'label' => 'วันที่ลา - ตั้งแต่ (d/m/Y) — alias',
                    'resolve' => fn($doc) => optional($doc->leave_date)->format('d/m/Y') ?? '',
                ],
                'leave_date_to_dmy' => [
                    'label' => 'วันที่ลา - ถึง (d/m/Y) — alias',
                    'resolve' => fn($doc) => optional($doc->leave_date)->format('d/m/Y') ?? '',
                ],
                'leave_days_count' => [
                    'label' => 'จำนวนวันลาในใบนี้ (ปัจจุบัน = 1 เสมอ)',
                    'resolve' => fn($doc) => '1',
                ],
                'leave_day' => [
                    'label' => 'วันที่ลา (วัน)',
                    'resolve' => fn($doc) => optional($doc->leave_date)->format('d') ?? '',
                ],
                'leave_month_th' => [
                    'label' => 'วันที่ลา (เดือนไทย)',
                    'resolve' => fn($doc) => $doc->leave_date ? self::THAI_MONTHS[(int) Carbon::parse($doc->leave_date)->format('m')] : '',
                ],
                'leave_year_be' => [
                    'label' => 'วันที่ลา (ปี พ.ศ.)',
                    'resolve' => fn($doc) => $doc->leave_date ? (string) ((int) Carbon::parse($doc->leave_date)->format('Y') + 543) : '',
                ],
                'reason' => [
                    'label' => 'เหตุผล',
                    'resolve' => fn($doc) => $doc->reason ?? '',
                ],
                'vacation_carryover_days' => [
                    'label' => 'สิทธิลาพักร้อนยกยอด (วัน)',
                    'resolve' => fn($doc) => self::leaveBal($doc, 'carryover'),
                ],
                'vacation_annual_days' => [
                    'label' => 'สิทธิลาพักร้อนประจำปี (วัน)',
                    'resolve' => fn($doc) => self::leaveBal($doc, 'annual'),
                ],
                'vacation_total_days' => [
                    'label' => 'สิทธิลาพักร้อนรวม (วัน)',
                    'resolve' => fn($doc) => self::leaveBal($doc, 'total'),
                ],
                'last_leave_date_th' => [
                    'label' => 'วันที่ลาครั้งล่าสุด (ประเภทเดียวกัน)',
                    'resolve' => fn($doc) => self::lastLeave($doc),
                ],
                'used_before_count' => [
                    'label' => 'จำนวนวันที่ลาแล้ว (ประเภทเดียวกัน, ปีนี้)',
                    'resolve' => fn($doc) => (string) self::usedBefore($doc),
                ],
                'total_with_current' => [
                    'label' => 'รวมการลาทั้งสิ้นรวมครั้งนี้',
                    'resolve' => fn($doc) => (string) (self::usedBefore($doc) + 1),
                ],
            ],
            'ot' => [
                'log_date_th'    => ['label' => 'วันที่ทำ OT (ไทย)', 'resolve' => fn($doc) => $doc->log_date ? Carbon::parse($doc->log_date)->format('d') . ' ' . self::THAI_MONTHS[(int) Carbon::parse($doc->log_date)->format('m')] . ' ' . ((int) Carbon::parse($doc->log_date)->format('Y') + 543) : ''],
                'log_date_dmy'   => ['label' => 'วันที่ทำ OT (d/m/Y)', 'resolve' => fn($doc) => optional($doc->log_date)->format('d/m/Y') ?? ''],
                'requested_hours'=> ['label' => 'จำนวนชั่วโมง', 'resolve' => fn($doc) => rtrim(rtrim(number_format(($doc->requested_minutes ?? 0) / 60, 2), '0'), '.')],
                'requested_minutes'=>['label' => 'จำนวนนาที', 'resolve' => fn($doc) => (string) ($doc->requested_minutes ?? 0)],
                'job_reference'  => ['label' => 'งานอ้างอิง', 'resolve' => fn($doc) => $doc->job_reference ?? ''],
                'reason'         => ['label' => 'เหตุผล', 'resolve' => fn($doc) => $doc->reason ?? ''],
            ],
            'swap' => [
                'work_date_th'  => ['label' => 'วันที่จะมาทำงาน (ไทย)', 'resolve' => fn($doc) => $doc->work_date ? Carbon::parse($doc->work_date)->format('d') . ' ' . self::THAI_MONTHS[(int) Carbon::parse($doc->work_date)->format('m')] . ' ' . ((int) Carbon::parse($doc->work_date)->format('Y') + 543) : ''],
                'work_date_dmy' => ['label' => 'วันที่จะมาทำงาน (d/m/Y)', 'resolve' => fn($doc) => optional($doc->work_date)->format('d/m/Y') ?? ''],
                'off_date_th'   => ['label' => 'วันที่จะหยุดทดแทน (ไทย)', 'resolve' => fn($doc) => $doc->off_date ? Carbon::parse($doc->off_date)->format('d') . ' ' . self::THAI_MONTHS[(int) Carbon::parse($doc->off_date)->format('m')] . ' ' . ((int) Carbon::parse($doc->off_date)->format('Y') + 543) : ''],
                'off_date_dmy'  => ['label' => 'วันที่จะหยุดทดแทน (d/m/Y)', 'resolve' => fn($doc) => optional($doc->off_date)->format('d/m/Y') ?? ''],
                'reason'        => ['label' => 'เหตุผล', 'resolve' => fn($doc) => $doc->reason ?? ''],
            ],
            'expense' => [
                'expense_type_label' => ['label' => 'ประเภทการเบิก', 'resolve' => fn($doc) => $doc->type === 'advance' ? 'เบิกเงินล่วงหน้า' : 'เบิกค่าใช้จ่าย'],
                'claim_date_dmy'     => ['label' => 'วันที่ขอเบิก', 'resolve' => fn($doc) => optional($doc->claim_date)->format('d/m/Y') ?? ''],
                'amount'             => ['label' => 'จำนวนเงิน (บาท)', 'resolve' => fn($doc) => number_format((float) $doc->amount, 2)],
                'description'        => ['label' => 'รายละเอียด', 'resolve' => fn($doc) => $doc->description ?? ''],
            ],
            'carryover' => [
                'leave_type_label' => ['label' => 'ประเภทวันลา', 'resolve' => fn($doc) => \App\Models\Employee::LEAVE_TYPE_LABELS[$doc->leave_type] ?? $doc->leave_type],
                'days'             => ['label' => 'จำนวนวัน', 'resolve' => fn($doc) => rtrim(rtrim(number_format((float) $doc->days, 2), '0'), '.')],
                'source_year'      => ['label' => 'จากปี', 'resolve' => fn($doc) => (string) ($doc->source_year ?? '')],
                'target_year'      => ['label' => 'ไปปี', 'resolve' => fn($doc) => (string) ($doc->year ?? '')],
                'note'             => ['label' => 'หมายเหตุ', 'resolve' => fn($doc) => $doc->note ?? ''],
            ],
            'encash' => [
                'leave_type_label' => ['label' => 'ประเภทวันลา', 'resolve' => fn($doc) => \App\Models\Employee::LEAVE_TYPE_LABELS[$doc->leave_type] ?? $doc->leave_type],
                'days'             => ['label' => 'จำนวนวัน', 'resolve' => fn($doc) => rtrim(rtrim(number_format((float) $doc->days, 2), '0'), '.')],
                'rate_per_day'     => ['label' => 'อัตรา/วัน (บาท)', 'resolve' => fn($doc) => number_format((float) $doc->rate_per_day, 2)],
                'amount'           => ['label' => 'ยอดที่จะได้รับ (บาท)', 'resolve' => fn($doc) => number_format((float) $doc->amount, 2)],
                'payout_month'     => ['label' => 'จ่ายในเดือน (MM)', 'resolve' => fn($doc) => sprintf('%02d', $doc->payout_month)],
                'payout_year'      => ['label' => 'จ่ายในปี', 'resolve' => fn($doc) => (string) $doc->payout_year],
                'note'             => ['label' => 'หมายเหตุ', 'resolve' => fn($doc) => $doc->note ?? ''],
            ],
            default => [],
        };

        return array_merge($common, $perType);
    }

    public static function resolve($doc, string $docType, string $fieldKey): string
    {
        $catalog = self::fields($docType);
        if (!isset($catalog[$fieldKey])) return '';
        try {
            return (string) $catalog[$fieldKey]['resolve']($doc);
        } catch (\Throwable $e) {
            return '';
        }
    }

    // ─── Leave helpers ────────────────────────────────────────────────

    protected static function leaveBal($doc, string $key): string
    {
        if ($doc->leave_type !== 'vacation_leave' || !method_exists($doc->employee, 'getLeaveBalance')) {
            return '';
        }
        $year = $doc->leave_date ? (int) Carbon::parse($doc->leave_date)->format('Y') : (int) now()->year;
        $b = $doc->employee->getLeaveBalance('vacation_leave', $year);
        $val = match ($key) {
            'carryover' => (float) ($b['carryover'] ?? 0),
            'annual'    => (float) ($b['limit'] ?? 0),
            'total'     => (float) (($b['carryover'] ?? 0) + ($b['limit'] ?? 0)),
            default     => 0,
        };
        return rtrim(rtrim(number_format($val, 2), '0'), '.');
    }

    protected static function lastLeave($doc): string
    {
        $log = AttendanceLog::where('employee_id', $doc->employee_id)
            ->where('day_type', $doc->leave_type)
            ->whereDate('log_date', '<', Carbon::parse($doc->leave_date)->toDateString())
            ->orderByDesc('log_date')
            ->first();
        if (!$log) return '';
        $d = Carbon::parse($log->log_date);
        return $d->format('d') . ' ' . self::THAI_MONTHS[(int) $d->format('m')] . ' ' . ((int) $d->format('Y') + 543);
    }

    protected static function usedBefore($doc): int
    {
        $year = (int) Carbon::parse($doc->leave_date)->format('Y');
        return (int) AttendanceLog::where('employee_id', $doc->employee_id)
            ->whereYear('log_date', $year)
            ->where('day_type', $doc->leave_type)
            ->whereDate('log_date', '<', Carbon::parse($doc->leave_date)->toDateString())
            ->count();
    }

    /**
     * Resolve the variant_key for a given doc — used to pick the right template.
     */
    public static function variantFor(string $docType, $doc): string
    {
        return match ($docType) {
            'leave' => $doc->status === 'cancelled'
                ? 'cancelled'
                : ($doc->leave_type ?? 'default'),
            default => 'default',
        };
    }

    /**
     * Human-friendly variant choices for the admin UI per doc type.
     */
    public static function variantChoices(string $docType): array
    {
        return match ($docType) {
            'leave' => array_merge(
                ['cancelled' => 'ใบยกเลิกการลา'],
                collect(\App\Models\Employee::LEAVE_TYPE_LABELS)->mapWithKeys(fn($label, $key) => [$key => $label])->all(),
            ),
            default => ['default' => 'ค่าเริ่มต้น'],
        };
    }

    public static function docTypeChoices(): array
    {
        return [
            'leave'     => 'ใบลา',
            'ot'        => 'ใบขอ OT',
            'swap'      => 'ใบสลับวัน',
            'expense'   => 'ใบเบิกเงิน',
            'carryover' => 'ใบยกยอดวันลา',
            'encash'    => 'ใบแลกวันลาเป็นเงิน',
        ];
    }

    /**
     * Starter field placements for known doc-type/variant combos.
     * Positions are RELATIVE (0..1) to image width/height so they scale to any uploaded image.
     * Font size is relative to image height (0.018 ≈ ~26px on a 1430px-tall image).
     *
     * `fs` = font_size_pct (relative to image height)
     * `w`  = width_pct (relative to image width, optional)
     * `cb` = checkbox_when value (when set, field becomes a checkbox)
     *
     * Users load these as a starting point, then drag to fine-tune.
     */
    public static function presets(string $docType, ?string $variantKey): array
    {
        // Common starter blocks for any leave variant — header strip + employee info
        $leaveHeader = [
            ['field_key' => 'company_name',     'xp' => 0.62, 'yp' => 0.115, 'fs' => 0.020],
            ['field_key' => 'created_day',      'xp' => 0.56, 'yp' => 0.150, 'fs' => 0.020],
            ['field_key' => 'created_month_th', 'xp' => 0.68, 'yp' => 0.150, 'fs' => 0.020],
            ['field_key' => 'created_year_be',  'xp' => 0.86, 'yp' => 0.150, 'fs' => 0.020],
            ['field_key' => 'employee_name',     'xp' => 0.27, 'yp' => 0.235, 'fs' => 0.020],
            ['field_key' => 'employee_position', 'xp' => 0.66, 'yp' => 0.235, 'fs' => 0.020],
        ];

        return match ([$docType, $variantKey]) {
            ['leave', 'sick_leave'], ['leave', 'personal_leave'], ['leave', 'maternity_leave'] => array_merge($leaveHeader, [
                // checkboxes for "ประเภทการลาที่ขอ"
                ['field_key' => 'leave_type_key', 'xp' => 0.165, 'yp' => 0.385, 'fs' => 0.022, 'cb' => 'sick_leave'],
                ['field_key' => 'leave_type_key', 'xp' => 0.250, 'yp' => 0.385, 'fs' => 0.022, 'cb' => 'maternity_leave'],
                ['field_key' => 'leave_type_key', 'xp' => 0.360, 'yp' => 0.385, 'fs' => 0.022, 'cb' => 'personal_leave'],
                // date range + days
                ['field_key' => 'leave_date_th', 'xp' => 0.235, 'yp' => 0.460, 'fs' => 0.020],
                ['field_key' => 'leave_date_th', 'xp' => 0.510, 'yp' => 0.460, 'fs' => 0.020],
                ['field_key' => 'reason',        'xp' => 0.195, 'yp' => 0.510, 'fs' => 0.020, 'w' => 0.70],
                // last leave dates (history)
                ['field_key' => 'last_leave_date_th', 'xp' => 0.280, 'yp' => 0.595, 'fs' => 0.020],
                ['field_key' => 'last_leave_date_th', 'xp' => 0.625, 'yp' => 0.595, 'fs' => 0.020],
                // signature name under (ลงชื่อ)
                ['field_key' => 'employee_name', 'xp' => 0.640, 'yp' => 0.715, 'fs' => 0.020],
            ]),

            ['leave', 'vacation_leave'] => array_merge($leaveHeader, [
                ['field_key' => 'vacation_carryover_days', 'xp' => 0.260, 'yp' => 0.330, 'fs' => 0.020, 'w' => 0.05],
                ['field_key' => 'vacation_annual_days',    'xp' => 0.460, 'yp' => 0.330, 'fs' => 0.020, 'w' => 0.05],
                ['field_key' => 'vacation_total_days',     'xp' => 0.770, 'yp' => 0.330, 'fs' => 0.020, 'w' => 0.05],
                ['field_key' => 'leave_date_th',           'xp' => 0.420, 'yp' => 0.380, 'fs' => 0.020],
                ['field_key' => 'leave_date_th',           'xp' => 0.730, 'yp' => 0.380, 'fs' => 0.020],
                ['field_key' => 'used_before_count',  'xp' => 0.085, 'yp' => 0.620, 'fs' => 0.020],
                ['field_key' => 'total_with_current', 'xp' => 0.230, 'yp' => 0.620, 'fs' => 0.020],
                ['field_key' => 'total_with_current', 'xp' => 0.350, 'yp' => 0.620, 'fs' => 0.020],
                ['field_key' => 'employee_name', 'xp' => 0.750, 'yp' => 0.715, 'fs' => 0.020],
            ]),

            ['leave', 'paternity_leave'] => array_merge($leaveHeader, [
                ['field_key' => 'reason',         'xp' => 0.520, 'yp' => 0.330, 'fs' => 0.020, 'w' => 0.45],
                ['field_key' => 'leave_date_th',  'xp' => 0.515, 'yp' => 0.430, 'fs' => 0.020],
                ['field_key' => 'leave_date_th',  'xp' => 0.770, 'yp' => 0.430, 'fs' => 0.020],
                ['field_key' => 'employee_name',  'xp' => 0.640, 'yp' => 0.745, 'fs' => 0.020],
            ]),

            ['leave', 'cancelled'] => array_merge($leaveHeader, [
                ['field_key' => 'leave_type_label', 'xp' => 0.380, 'yp' => 0.350, 'fs' => 0.020],
                ['field_key' => 'leave_date_th',    'xp' => 0.660, 'yp' => 0.350, 'fs' => 0.020],
                ['field_key' => 'leave_date_th',    'xp' => 0.165, 'yp' => 0.390, 'fs' => 0.020],
                ['field_key' => 'reason',           'xp' => 0.130, 'yp' => 0.440, 'fs' => 0.020, 'w' => 0.75],
                ['field_key' => 'leave_date_th',    'xp' => 0.165, 'yp' => 0.525, 'fs' => 0.020],
                ['field_key' => 'leave_date_th',    'xp' => 0.470, 'yp' => 0.525, 'fs' => 0.020],
                ['field_key' => 'employee_name',    'xp' => 0.640, 'yp' => 0.665, 'fs' => 0.020],
            ]),

            default => [],
        };
    }
}
