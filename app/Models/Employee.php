<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'employee_code', 'first_name', 'last_name', 'nickname',
        'department_id', 'position_id', 'payroll_mode', 'advance_ceiling_percent', 'fixed_rate_per_clip', 'status', 'is_active',
        'start_date', 'probation_end_date', 'end_date',
        'vacation_entitlement', 'sick_leave_entitlement', 'personal_leave_entitlement',
        'leave_policy_id',
        'tier_override_id', 'tier_source', 'tier_override_note',
    ];

    /** ประเภทวันลาที่นับสิทธิและยกยอด/แลกเงินได้ — ใช้ key ตรงกับ leave_type ใน LeaveRequest/AttendanceLog */
    /**
     * All leave types accepted by the leave-request flow (dropdowns, label maps).
     * Subset LEAVE_TYPES_TRACKED below = ones that have a quota/balance.
     * Add a row here to make a new type appear in every dropdown across the app.
     */
    public const LEAVE_TYPE_LABELS = [
        'sick_leave'      => 'ลาป่วย',
        'personal_leave'  => 'ลากิจ',
        'vacation_leave'  => 'ลาพักร้อน',
        'maternity_leave' => 'ลาคลอดบุตร',
        'paternity_leave' => 'ลาไปช่วยเหลือภริยาที่คลอดบุตร',
        'lwop'            => 'ลาไม่รับค่าจ้าง (LWOP)',
    ];

    public const LEAVE_TYPES_TRACKED = [
        'vacation_leave' => [
            'label' => 'ลาพักร้อน',
            'entitlement_field' => 'vacation_entitlement',
            'policy_field' => 'vacation_days',
            'fallback' => 6,
        ],
        'sick_leave' => [
            'label' => 'ลาป่วย',
            'entitlement_field' => 'sick_leave_entitlement',
            'policy_field' => 'sick_days',
            'fallback' => 30,
        ],
        'personal_leave' => [
            'label' => 'ลากิจ',
            'entitlement_field' => 'personal_leave_entitlement',
            'policy_field' => 'personal_days',
            'fallback' => 3,
        ],
    ];

    public function leavePolicy()
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    /** Resolved policy through 3-layer fallback (assigned → default → null) */
    public function effectivePolicy(): ?LeavePolicy
    {
        return LeavePolicy::resolveFor($this);
    }

    /** วันที่นโยบายตั้งให้ตามประเภท (ยังไม่รวม override ของ employee) */
    public function getPolicyEntitlement(string $type): int
    {
        $cfg = self::LEAVE_TYPES_TRACKED[$type] ?? null;
        if (!$cfg) return 0;

        $policy = $this->effectivePolicy();
        if ($policy) {
            return (int) ($policy->{$cfg['policy_field']} ?? $cfg['fallback']);
        }
        return $cfg['fallback'];
    }

    /** สิทธิจริงที่ใช้คำนวณ — override > policy > fallback */
    public function getResolvedEntitlement(string $type): int
    {
        $cfg = self::LEAVE_TYPES_TRACKED[$type] ?? null;
        if (!$cfg) return 0;

        $override = $this->{$cfg['entitlement_field']};
        if ($override !== null) {
            return (int) $override;
        }
        return $this->getPolicyEntitlement($type);
    }

    /**
     * วันที่ลาพักร้อนเริ่มใช้สิทธิได้ — start_date + vacation_eligibility_months ของ policy (default 12)
     * คืน null ถ้าไม่มี start_date
     */
    public function vacationEligibleFrom(): ?Carbon
    {
        if (!$this->start_date) return null;
        $months = (int) ($this->effectivePolicy()?->vacation_eligibility_months ?? 12);
        return $this->start_date->copy()->addMonths($months);
    }

    /**
     * Pro-rate สิทธิพักร้อนเมื่อครบ eligibility กลาง FY
     *   - ก่อนครบ → 0
     *   - ครบก่อนต้น FY → เต็มโควต้า (base)
     *   - ครบกลาง FY → base × (เดือนที่เหลือ / 12), floor (นับเดือนที่ครบเป็นเดือนเต็ม)
     * ใช้กับ vacation_leave เท่านั้น; ประเภทอื่นคืน $base ตามเดิม
     */
    public function proratedEntitlement(string $type, int $year, int $base): int
    {
        if ($type !== 'vacation_leave') {
            return $base;
        }

        $eligibleFrom = $this->vacationEligibleFrom();
        if (!$eligibleFrom) {
            return 0;
        }

        $startOfFy = Carbon::create($year, 1, 1)->startOfDay();
        $endOfFy   = Carbon::create($year, 12, 31)->endOfDay();

        if ($eligibleFrom->greaterThan($endOfFy)) {
            return 0;
        }
        if ($eligibleFrom->lessThanOrEqualTo($startOfFy)) {
            return $base;
        }

        $monthsRemaining = 12 - ($eligibleFrom->month - 1);
        return (int) floor($base * $monthsRemaining / 12);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'probation_end_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?: $this->first_name;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function profile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function salaryProfile()
    {
        return $this->hasOne(EmployeeSalaryProfile::class)->where('is_current', true);
    }

    public function salaryHistory()
    {
        return $this->hasMany(EmployeeSalaryProfile::class)->orderBy('effective_date', 'desc');
    }

    public function bankAccount()
    {
        return $this->hasOne(EmployeeBankAccount::class)->where('is_primary', true);
    }

    public function bankAccounts()
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function moduleToggles()
    {
        return $this->hasMany(ModuleToggle::class);
    }

    public function layerRateRules()
    {
        return $this->hasMany(LayerRateRule::class);
    }

    public function rateRules()
    {
        return $this->hasMany(RateRule::class);
    }

    public function expenseClaims()
    {
        return $this->hasMany(ExpenseClaim::class);
    }

    public function performanceRecords()
    {
        return $this->hasMany(PerformanceRecord::class);
    }

    public function editingJobs()
    {
        return $this->hasMany(EditingJob::class, 'assigned_to');
    }

    public function otRequests()
    {
        return $this->hasMany(OtRequest::class);
    }

    public function extraIncomeEntries()
    {
        return $this->hasMany(ExtraIncomeEntry::class);
    }

    public function tierOverride()
    {
        return $this->belongsTo(PerformanceTier::class, 'tier_override_id');
    }

    public function isModuleEnabled(string $moduleName): bool
    {
        $toggle = $this->moduleToggles()->where('module_name', $moduleName)->first();
        return $toggle ? (bool) $toggle->is_enabled : true;
    }

    public function getAverageMinutesLast3MonthsAttribute(): float
    {
        $threeMonthsAgo = now()->subMonths(3)->startOfMonth();
        
        $totalMinutes = $this->workLogs()
            ->where('is_disabled', false)
            ->where(function ($q) use ($threeMonthsAgo) {
                $q->where('year', '>', $threeMonthsAgo->year)
                  ->orWhere(function ($sq) use ($threeMonthsAgo) {
                      $sq->where('year', $threeMonthsAgo->year)
                        ->where('month', '>=', $threeMonthsAgo->month);
                  });
            })
            ->get()
            ->sum(function ($log) {
                return ($log->hours * 60) + $log->minutes + ($log->seconds / 60);
            });

        return round($totalMinutes / 3, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Probation helpers.
     *
     * อ้างอิงกฎหมายไทย:
     *  - พรบ.คุ้มครองแรงงาน 2541 ม.118: ลูกจ้างทำงานครบ 120 วัน ต้องจ่ายค่าชดเชยเมื่อเลิกจ้าง
     *  - ม.17/1: เลิกจ้างต้องบอกล่วงหน้าอย่างน้อย 1 งวดการจ่ายค่าจ้าง (หรือจ่ายแทนการบอกกล่าว)
     *  → ระยะเวลาทดลองงานที่นิยมและปลอดภัยคือไม่เกิน 119 วัน
     */
    public const PROBATION_LEGAL_LIMIT_DAYS = 119;
    public const PROBATION_DEFAULT_DAYS = 119;
    public const PROBATION_NEAR_END_DAYS = 14;

    public function isOnProbation(?\Carbon\Carbon $asOf = null): bool
    {
        $asOf = $asOf ?? now();
        if (!$this->probation_end_date) return false;
        return $this->probation_end_date->gte($asOf->copy()->startOfDay());
    }

    public function probationDaysRemaining(?\Carbon\Carbon $asOf = null): ?int
    {
        if (!$this->probation_end_date) return null;
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        return (int) $asOf->diffInDays($this->probation_end_date->copy()->startOfDay(), false);
    }

    public function isProbationNearEnd(int $thresholdDays = self::PROBATION_NEAR_END_DAYS): bool
    {
        $remaining = $this->probationDaysRemaining();
        return $remaining !== null && $remaining >= 0 && $remaining <= $thresholdDays;
    }

    public function probationStatusLabel(): ?string
    {
        if (!$this->probation_end_date) return null;
        $remaining = $this->probationDaysRemaining();
        if ($remaining < 0) return 'พ้นทดลอง';
        if ($remaining === 0) return 'วันสุดท้าย';
        return "เหลือ {$remaining} วัน";
    }

    public function leaveCarryovers()
    {
        return $this->hasMany(LeaveCarryover::class);
    }

    public function leaveEncashments()
    {
        return $this->hasMany(LeaveEncashment::class);
    }

    /**
     * คำนวณยอดวันลาคงเหลือสำหรับประเภทใดประเภทหนึ่ง โดยรวม carryover และหัก encashment
     */
    public function getLeaveBalance(string $type, int $year): array
    {
        $config = self::LEAVE_TYPES_TRACKED[$type] ?? null;
        if (!$config) {
            return ['type' => $type, 'limit' => 0, 'carryover' => 0, 'used' => 0, 'encashed' => 0, 'remaining' => 0, 'allow_carryover' => false, 'allow_encashment' => false];
        }

        $entitlement = (float) $this->proratedEntitlement($type, $year, $this->getResolvedEntitlement($type));
        $policy = $this->effectivePolicy();

        // นโยบาย — ยกยอด/แลกเงินได้เฉพาะ vacation_leave + ขึ้นกับ policy
        $isVacation = ($type === 'vacation_leave');
        $allowCarryover  = $isVacation && ($policy?->allow_carryover ?? false);
        $allowEncashment = $isVacation && ($policy?->allow_encashment ?? false);
        $maxCarryover    = $policy?->max_carryover_days;        // null = ไม่จำกัด
        $maxEncashPerYear = $policy?->max_encash_days_per_year; // null = ไม่จำกัด
        $expiresMonths   = $policy?->carryover_expires_months;  // null = ไม่หมดอายุ

        $carryover = (float) $this->leaveCarryovers()
            ->where('year', $year)
            ->where('leave_type', $type)
            ->where('status', 'approved')
            ->sum('days');

        $used = (int) $this->attendanceLogs()
            ->whereYear('log_date', $year)
            ->where('day_type', $type)
            ->count();

        $encashed = (float) $this->leaveEncashments()
            ->where('year', $year)
            ->where('leave_type', $type)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('days');

        // Carryovers ออก (จากปี $year ส่งไปใช้ในปีอื่น) — ตัดออกจากสิทธิ์ปีนี้
        // นับทั้ง approved และ pending เพื่อกัน admin ไม่ให้ยอมรับเกินที่เหลือจริง
        $carryoverOut = (float) $this->leaveCarryovers()
            ->where('source_year', $year)
            ->where('leave_type', $type)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('days');

        $totalAvailable = $entitlement + $carryover;
        $remaining = max(0, $totalAvailable - $used - $encashed - $carryoverOut);

        return [
            'type'              => $type,
            'label'             => $config['label'],
            'limit'             => $entitlement,
            'carryover'         => $carryover,
            'carryover_out'     => $carryoverOut,
            'total_available'   => $totalAvailable,
            'used'              => $used,
            'encashed'          => $encashed,
            'remaining'         => $remaining,
            'allow_carryover'   => $allowCarryover,
            'allow_encashment'  => $allowEncashment,
            'max_carryover_days' => $maxCarryover,
            'max_encash_days_per_year' => $maxEncashPerYear,
            'carryover_expires_months' => $expiresMonths,
            'policy_id'         => $policy?->id,
            'policy_name'       => $policy?->name,
            'is_override'       => $this->{$config['entitlement_field']} !== null,
        ];
    }

    /** Backward-compat: เก่าๆ เรียก getVacationBalance() */
    public function getVacationBalance(int $year): array
    {
        $b = $this->getLeaveBalance('vacation_leave', $year);
        return [
            'limit'     => $b['limit'] + $b['carryover'],  // รวม carryover เพื่อแสดงเป็น "limit จริง"
            'used'      => $b['used'] + $b['encashed'],     // นับ encashed เป็นใช้แล้วเพื่อแสดงผลรวม
            'remaining' => $b['remaining'],
        ];
    }

    /** สรุปทุกประเภทการลาที่ track ในปีหนึ่ง */
    public function getAllLeaveBalances(int $year): array
    {
        $result = [];
        foreach (array_keys(self::LEAVE_TYPES_TRACKED) as $type) {
            $result[$type] = $this->getLeaveBalance($type, $year);
        }
        return $result;
    }
}
