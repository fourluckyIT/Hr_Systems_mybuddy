<?php

namespace App\Services;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceLog;
use App\Models\BonusAuditLog;
use App\Models\BonusCalculation;
use App\Models\BonusCycle;
use App\Models\BonusCycleSelectedMonth;
use App\Models\EditingJob;
use App\Models\Employee;
use App\Models\ExtraIncomeEntry;
use App\Models\LeaveRequest;
use App\Models\Payslip;
use App\Models\PerformanceTier;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BonusCalculationService
{
    public function setCycleSelectedMonths(int $cycleId, array $monthKeys, ?string $selectedBy = null): array
    {
        $cycle = BonusCycle::findOrFail($cycleId);

        $normalized = collect($monthKeys)
            ->map(fn ($m) => trim((string) $m))
            ->filter(fn ($m) => preg_match('/^\d{4}-\d{2}$/', $m) === 1)
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            throw new \DomainException('At least one valid month is required.');
        }

        $rows = $normalized->map(function (string $monthKey) use ($cycleId, $selectedBy) {
            [$year, $month] = array_map('intval', explode('-', $monthKey));

            return [
                'cycle_id' => $cycleId,
                'selected_year' => $year,
                'selected_month' => $month,
                'selected_by' => $selectedBy,
                'selected_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ];
        })->values();

        DB::transaction(function () use ($cycleId, $rows) {
            BonusCycleSelectedMonth::where('cycle_id', $cycleId)->delete();
            BonusCycleSelectedMonth::insert($rows->all());
        });

        return $this->getCycleSelectedMonths($cycle->id);
    }

    public function getCycleSelectedMonths(int $cycleId): array
    {
        $cycle = BonusCycle::findOrFail($cycleId);

        $selected = BonusCycleSelectedMonth::query()
            ->where('cycle_id', $cycleId)
            ->orderBy('selected_year')
            ->orderBy('selected_month')
            ->get();

        $selectedKeys = $selected
            ->map(fn (BonusCycleSelectedMonth $m) => sprintf('%04d-%02d', $m->selected_year, $m->selected_month))
            ->values()
            ->all();

        return [
            'cycle_id' => $cycle->id,
            'cycle_code' => $cycle->cycle_code,
            'selected_count' => count($selectedKeys),
            'selected_months' => $selected->map(fn (BonusCycleSelectedMonth $m) => [
                'month_key' => sprintf('%04d-%02d', $m->selected_year, $m->selected_month),
                'year' => $m->selected_year,
                'month' => $m->selected_month,
                'selected_by' => $m->selected_by,
                'selected_at' => optional($m->selected_at)->toDateTimeString(),
            ])->values()->all(),
            'candidate_months' => collect(range(1, 12))->map(function (int $month) use ($cycle, $selectedKeys) {
                $key = sprintf('%04d-%02d', (int) $cycle->cycle_year, $month);

                return [
                    'month_key' => $key,
                    'year' => (int) $cycle->cycle_year,
                    'month' => $month,
                    'already_selected' => in_array($key, $selectedKeys, true),
                ];
            })->values()->all(),
        ];
    }

    public function resolveTierCodeFromMetrics(int $clipDurationMinutesPerMonth, int $qualifiedMonths): string
    {
        $tier = PerformanceTier::query()
            ->where('is_active', true)
            ->where('auto_select_enabled', true)
            ->where(function ($q) use ($clipDurationMinutesPerMonth) {
                $q->whereNull('min_clip_minutes_per_month')
                    ->orWhere('min_clip_minutes_per_month', '<=', $clipDurationMinutesPerMonth);
            })
            ->where(function ($q) use ($clipDurationMinutesPerMonth) {
                $q->whereNull('max_clip_minutes_per_month')
                    ->orWhere('max_clip_minutes_per_month', '>=', $clipDurationMinutesPerMonth);
            })
            ->where(function ($q) use ($qualifiedMonths) {
                $q->whereNull('min_qualified_months')
                    ->orWhere('min_qualified_months', '<=', $qualifiedMonths);
            })
            ->where(function ($q) use ($qualifiedMonths) {
                $q->whereNull('max_qualified_months')
                    ->orWhere('max_qualified_months', '>=', $qualifiedMonths);
            })
            ->orderBy('display_order')
            ->first();

        if (!$tier) {
            throw new \DomainException('No performance tier matches provided clip metrics.');
        }

        return $tier->tier_code;
    }

    /**
     * Calculate complete months between probation end and payment date.
     */
    public function calculateMonthsAfterProbation(Carbon $probationEndDate, Carbon $paymentDate): int
    {
        if ($paymentDate->lt($probationEndDate)) {
            return 0;
        }

        $months = ($paymentDate->year - $probationEndDate->year) * 12
                + ($paymentDate->month - $probationEndDate->month);

        if ($paymentDate->day < $probationEndDate->day) {
            $months--;
        }

        return max(0, $months);
    }

    /**
     * Calculate unlock percentage for the current cycle.
     */
    /**
     * @param array $config  Keys: june_max_ratio(0.4), june_scale_months(6), full_scale_months(12)
     */
    public function calculateUnlockPercentage(
        int $monthsAfterProbation,
        string $cyclePeriod,
        float $previousPaidRatio = 0.0,
        array $config = [],
    ): float {
        if ($monthsAfterProbation <= 0) {
            return 0.0;
        }

        $juneMaxRatio    = (float) ($config['june_max_ratio']    ?? 0.4);
        $juneScaleMonths = (int)   ($config['june_scale_months'] ?? 6);
        $fullScaleMonths = (int)   ($config['full_scale_months'] ?? 12);

        if ($cyclePeriod === 'june') {
            $unlocked = min($monthsAfterProbation / $juneScaleMonths, 1.0) * $juneMaxRatio;

            return round($unlocked, 4);
        }

        if ($cyclePeriod === 'december') {
            // 2-phase curve: respect the 40%/60% structure regardless of which cycle the employee
            // first becomes eligible. Ensures someone working only 6 months never exceeds June's cap.
            $phase1 = min($monthsAfterProbation / $juneScaleMonths, 1.0) * $juneMaxRatio;
            $remainingMonths = max(0, $monthsAfterProbation - $juneScaleMonths);
            $phase2ScaleMonths = max(1, $fullScaleMonths - $juneScaleMonths);
            $phase2 = min($remainingMonths / $phase2ScaleMonths, 1.0) * (1.0 - $juneMaxRatio);
            $totalUnlocked = min(1.0, $phase1 + $phase2);

            $decemberRatio = $totalUnlocked - $previousPaidRatio;

            return round(max(0, $decemberRatio), 4);
        }

        return 0.0;
    }

    /**
     * Get the June paid ratio for an employee in a given year.
     */
    public function getJunePaidRatio(int $employeeId, int $cycleYear): float
    {
        $juneCalc = BonusCalculation::whereHas('cycle', function ($q) use ($cycleYear) {
            $q->where('cycle_year', $cycleYear)
              ->where('cycle_period', 'june');
        })
            ->where('employee_id', $employeeId)
            ->first();

        return $juneCalc ? (float) $juneCalc->unlock_percentage : 0.0;
    }

    /**
     * Main bonus calculation for a single employee.
     */
    public function calculate(
        int $employeeId,
        int $cycleId,
        float $baseReference,
        ?string $tierCode = null,
        float $attendanceAdjustment = 0.0,
        ?int $absentDays = null,
        ?int $lateCount = null,
        ?int $leaveDays = null,
        ?int $clipDurationMinutesPerMonth = null,
        ?int $qualifiedMonths = null,
    ): array {
        $employee = Employee::findOrFail($employeeId);
        $cycle = BonusCycle::findOrFail($cycleId);

        if ($tierCode === null || trim($tierCode) === '') {
            if ($clipDurationMinutesPerMonth === null || $qualifiedMonths === null) {
                throw new \DomainException('tier_id is required unless clip metrics are provided.');
            }

            $tierCode = $this->resolveTierCodeFromMetrics($clipDurationMinutesPerMonth, $qualifiedMonths);
        }

        $tier = PerformanceTier::where('tier_code', $tierCode)
            ->where('is_active', true)
            ->firstOrFail();

        $paymentDate = $cycle->payment_date;

        if ($absentDays !== null || $lateCount !== null || $leaveDays !== null) {
            $attendanceAdjustment = $this->calculateAttendanceAdjustmentFromBreakdown(
                $absentDays ?? 0,
                $lateCount ?? 0,
                $leaveDays ?? 0,
                $cycle->attendancePenaltyConfig(),
            );
        }

        // Step 1: Apply tier multiplier
        $tierAdjusted = round($baseReference * (1 + (float) $tier->multiplier), 2);

        // Step 2: Apply attendance adjustment
        $finalNet = round($tierAdjusted * (1 + $attendanceAdjustment), 2);

        // Step 3: Check active status
        $isActive = $employee->status === 'active';

        if (!$isActive) {
            return [
                'base_reference'         => $baseReference,
                'tier_code'              => $tierCode,
                'tier_id'                => $tier->id,
                'tier_multiplier'        => (float) $tier->multiplier,
                'tier_adjusted_bonus'    => $tierAdjusted,
                'absent_days'            => (int) ($absentDays ?? 0),
                'late_count'             => (int) ($lateCount ?? 0),
                'leave_days'             => (int) ($leaveDays ?? 0),
                'attendance_adjustment'  => $attendanceAdjustment,
                'final_bonus_net'        => $finalNet,
                'months_after_probation' => 0,
                'unlock_percentage'      => 0.0,
                'actual_payment'         => 0.0,
                'is_active_on_payment'   => false,
                'reason'                 => 'Not active on payment date',
            ];
        }

        // Step 4: Calculate months after probation.
        // Fallback: ถ้าไม่มี probation_end_date ให้ใช้ start_date (ถือว่าผ่าน probation ตั้งแต่วันเริ่มงาน)
        $referenceDate = $employee->probation_end_date ?? $employee->start_date;
        $months = 0;
        if ($referenceDate) {
            $months = $this->calculateMonthsAfterProbation(
                Carbon::parse($referenceDate),
                Carbon::parse($paymentDate),
            );
        }

        // Step 5: Get previous paid ratio (for December cycle)
        $previousPaid = 0.0;
        if ($cycle->cycle_period === 'december') {
            $previousPaid = $this->getJunePaidRatio($employeeId, $cycle->cycle_year);
        }

        // Step 6: Calculate unlock percentage
        $unlockPct = $this->calculateUnlockPercentage($months, $cycle->cycle_period, $previousPaid, $cycle->unlockConfig());

        // Step 7: Calculate actual payment
        $actualPayment = round($finalNet * $unlockPct, 2);

        return [
            'base_reference'         => $baseReference,
            'tier_code'              => $tierCode,
            'tier_id'                => $tier->id,
            'tier_multiplier'        => (float) $tier->multiplier,
            'tier_adjusted_bonus'    => $tierAdjusted,
            'absent_days'            => (int) ($absentDays ?? 0),
            'late_count'             => (int) ($lateCount ?? 0),
            'leave_days'             => (int) ($leaveDays ?? 0),
            'attendance_adjustment'  => $attendanceAdjustment,
            'final_bonus_net'        => $finalNet,
            'months_after_probation' => $months,
            'unlock_percentage'      => $unlockPct,
            'actual_payment'         => $actualPayment,
            'is_active_on_payment'   => true,
            'clip_duration_minutes_per_month' => $clipDurationMinutesPerMonth,
            'qualified_months' => $qualifiedMonths,
        ];
    }

    /**
     * Calculate and persist a bonus for a single employee.
     */
    public function calculateAndStore(
        int $employeeId,
        int $cycleId,
        float $baseReference,
        ?string $tierCode = null,
        float $attendanceAdjustment = 0.0,
        ?int $absentDays = null,
        ?int $lateCount = null,
        ?int $leaveDays = null,
        ?int $clipDurationMinutesPerMonth = null,
        ?int $qualifiedMonths = null,
    ): BonusCalculation {
        $result = $this->calculate(
            $employeeId,
            $cycleId,
            $baseReference,
            $tierCode,
            $attendanceAdjustment,
            $absentDays,
            $lateCount,
            $leaveDays,
            $clipDurationMinutesPerMonth,
            $qualifiedMonths,
        );

        return DB::transaction(function () use ($employeeId, $cycleId, $result) {
            AttendanceAdjustment::updateOrCreate(
                ['employee_id' => $employeeId, 'cycle_id' => $cycleId],
                [
                    'absent_days' => (int) ($result['absent_days'] ?? 0),
                    'late_count' => (int) ($result['late_count'] ?? 0),
                    'leave_days' => (int) ($result['leave_days'] ?? 0),
                    'total_adjustment' => (float) ($result['attendance_adjustment'] ?? 0),
                ],
            );

            $calc = BonusCalculation::updateOrCreate(
                ['employee_id' => $employeeId, 'cycle_id' => $cycleId],
                [
                    'base_reference'         => $result['base_reference'],
                    'tier_id'                => $result['tier_id'],
                    'tier_multiplier'        => $result['tier_multiplier'],
                    'tier_adjusted_bonus'    => $result['tier_adjusted_bonus'],
                    'attendance_adjustment'  => $result['attendance_adjustment'],
                    'final_bonus_net'        => $result['final_bonus_net'],
                    'months_after_probation' => $result['months_after_probation'],
                    'unlock_percentage'      => $result['unlock_percentage'],
                    'actual_payment'         => $result['actual_payment'],
                    'is_active_on_payment'   => $result['is_active_on_payment'],
                    'status'                 => 'calculated',
                ],
            );

            BonusAuditLog::create([
                'calculation_id' => $calc->id,
                'action_type'    => $calc->wasRecentlyCreated ? 'created' : 'modified',
                'old_value'      => $calc->wasRecentlyCreated ? null : $calc->getOriginal(),
                'new_value'      => $result,
                'changed_by'     => Auth::user()?->name ?? 'system',
                'changed_at'     => now(),
            ]);

            return $calc;
        });
    }

    /**
     * Batch calculate for multiple employees in a cycle.
     */
    public function batchCalculate(int $cycleId, array $employees): array
    {
        $cycle = BonusCycle::findOrFail($cycleId);

        if (BonusCycleSelectedMonth::where('cycle_id', $cycleId)->count() === 0) {
            throw new \DomainException('Please select bonus months for this cycle before calculation.');
        }

        if ($cycle->isPaid()) {
            throw new \DomainException('Cannot calculate for a paid/closed cycle.');
        }

        $cycle->update(['status' => 'calculating']);

        $results = [];
        $totalPayment = 0.0;

        // Process in chunks
        $chunks = array_chunk($employees, 100);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($cycleId, $chunk, &$results, &$totalPayment) {
                foreach ($chunk as $emp) {
                    $calc = $this->calculateAndStore(
                        $emp['employee_id'],
                        $cycleId,
                        (float) $emp['base_reference'],
                        $emp['tier_id'] ?? null,
                        (float) ($emp['attendance_adjustment'] ?? 0.0),
                        isset($emp['absent_days']) ? (int) $emp['absent_days'] : null,
                        isset($emp['late_count']) ? (int) $emp['late_count'] : null,
                        isset($emp['leave_days']) ? (int) $emp['leave_days'] : null,
                        isset($emp['clip_duration_minutes_per_month']) ? (int) $emp['clip_duration_minutes_per_month'] : null,
                        isset($emp['qualified_months']) ? (int) $emp['qualified_months'] : null,
                    );

                    $totalPayment += (float) $calc->actual_payment;
                    $results[] = $calc;
                }
            });
        }

        $cycle->update(['status' => 'calculated']);

        return [
            'cycle_id'        => $cycleId,
            'total_employees' => count($results),
            'total_payment'   => round($totalPayment, 2),
            'calculations'    => $results,
        ];
    }

    /**
     * Approve specific calculations.
     */
    public function approve(int $cycleId, array $calculationIds, string $approvedBy): array
    {
        $calculations = BonusCalculation::where('cycle_id', $cycleId)
            ->whereIn('id', $calculationIds)
            ->where('status', '!=', 'paid')
            ->get();

        $approved = [];

        DB::transaction(function () use ($calculations, $approvedBy, &$approved) {
            foreach ($calculations as $calc) {
                $oldStatus = $calc->status;
                $calc->update([
                    'status'      => 'approved',
                    'approved_by' => $approvedBy,
                    'approved_at' => now(),
                ]);

                BonusAuditLog::create([
                    'calculation_id' => $calc->id,
                    'action_type'    => 'approved',
                    'old_value'      => ['status' => $oldStatus],
                    'new_value'      => ['status' => 'approved', 'approved_by' => $approvedBy],
                    'changed_by'     => $approvedBy,
                    'changed_at'     => now(),
                ]);

                $approved[] = $calc;
            }
        });

        $totalPayment = collect($approved)->sum(fn ($c) => (float) $c->actual_payment);

        return [
            'approved_count' => count($approved),
            'total_payment'  => round($totalPayment, 2),
        ];
    }

    /**
     * Get employee bonus history.
     */
    public function getEmployeeHistory(int $employeeId): array
    {
        $calculations = BonusCalculation::with('cycle', 'tier')
            ->where('employee_id', $employeeId)
            ->orderByDesc('created_at')
            ->get();

        $lifetimeBonus = $calculations->sum(fn ($c) => (float) $c->actual_payment);

        return [
            'employee_id'    => $employeeId,
            'total_cycles'   => $calculations->count(),
            'lifetime_bonus' => round($lifetimeBonus, 2),
            'cycles'         => $calculations->map(fn ($c) => [
                'cycle_id'       => $c->cycle->cycle_code,
                'payment_date'   => $c->cycle->payment_date->toDateString(),
                'tier'           => $c->tier->tier_code,
                'actual_payment' => (float) $c->actual_payment,
                'status'         => $c->status,
            ])->values()->toArray(),
        ];
    }

    /**
     * Auto-fill metrics for an employee from real data within the cycle's selected months.
     *
     * Returns:
     *  - base_reference (current salary)
     *  - absent_days, late_count, leave_days (from attendance + leave requests)
     *  - clip_duration_minutes_per_month (avg from WorkLog over selected months)
     *  - qualified_months (count of selected months with any activity)
     *  - selected_months_count
     */
    public function buildEmployeeMetrics(int $cycleId, int $employeeId): array
    {
        $cycle = BonusCycle::findOrFail($cycleId);
        $employee = Employee::with('salaryProfile')->findOrFail($employeeId);

        $months = BonusCycleSelectedMonth::where('cycle_id', $cycleId)
            ->orderBy('selected_year')
            ->orderBy('selected_month')
            ->get(['selected_year', 'selected_month']);

        $baseReference = (float) ($employee->salaryProfile?->base_salary ?? 0);

        if ($months->isEmpty()) {
            return [
                'base_reference' => $baseReference,
                'absent_days' => 0,
                'late_count' => 0,
                'leave_days' => 0,
                'clip_duration_minutes_per_month' => 0,
                'qualified_months' => 0,
                'selected_months_count' => 0,
                'has_data' => false,
                'note' => 'ยังไม่ได้เลือกเดือนคำนวณ — auto-fill ใช้ข้อมูลเดือนที่เลือกในรอบนี้',
            ];
        }

        $absentDays = 0;
        $lateCount = 0;
        $leaveDays = 0;
        $totalClipMinutes = 0.0;
        $qualifiedMonths = 0;

        foreach ($months as $m) {
            $year = (int) $m->selected_year;
            $month = (int) $m->selected_month;
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = (clone $start)->endOfMonth();

            $attendance = AttendanceLog::where('employee_id', $employeeId)
                ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
                ->get();

            $monthAbsent = $attendance->filter(fn ($log) => $log->day_type === 'lwop' || $log->lwop_flag)->count();
            $monthLate = $attendance->filter(fn ($log) => (int) $log->late_minutes > 0)->count();
            $monthLeave = LeaveRequest::where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->whereBetween('leave_date', [$start->toDateString(), $end->toDateString()])
                ->count();

            $absentDays += $monthAbsent;
            $lateCount += $monthLate;
            $leaveDays += $monthLeave;

            $monthClipMin = $this->sumEditingJobMinutes($start, $end, $employeeId);

            $totalClipMinutes += $monthClipMin;

            $hasActivity = $monthClipMin > 0
                || $attendance->whereIn('day_type', ['workday', 'ot_full_day'])->count() > 0;
            if ($hasActivity) {
                $qualifiedMonths++;
            }
        }

        $monthsCount = $months->count();
        $avgClip = $monthsCount > 0 ? $totalClipMinutes / $monthsCount : 0.0;

        return [
            'base_reference' => $baseReference,
            'absent_days' => $absentDays,
            'late_count' => $lateCount,
            'leave_days' => $leaveDays,
            'clip_duration_minutes_per_month' => (int) round($avgClip),
            'qualified_months' => $qualifiedMonths,
            'selected_months_count' => $monthsCount,
            'has_data' => true,
        ];
    }

    /**
     * Get rich data about candidate months for the visual picker:
     * shows which months have payslips finalized, attendance count, clip count.
     */
    public function getCandidateMonthsRich(int $cycleId): array
    {
        $cycle = BonusCycle::findOrFail($cycleId);
        $selectedKeys = BonusCycleSelectedMonth::where('cycle_id', $cycleId)
            ->get()
            ->map(fn ($m) => sprintf('%04d-%02d', $m->selected_year, $m->selected_month))
            ->all();

        $year = (int) $cycle->cycle_year;

        return collect(range(1, 12))->map(function (int $month) use ($year, $selectedKeys) {
            $key = sprintf('%04d-%02d', $year, $month);
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = (clone $start)->endOfMonth();

            $employeesWithAttendance = AttendanceLog::whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
                ->distinct('employee_id')
                ->count('employee_id');

            $totalClipMinutes = $this->sumEditingJobMinutes($start, $end);

            $finalizedPayslips = \App\Models\Payslip::where('year', $year)
                ->where('month', $month)
                ->where('status', 'finalized')
                ->count();

            return [
                'month_key' => $key,
                'year' => $year,
                'month' => $month,
                'month_label' => Carbon::create($year, $month, 1)->locale('th')->translatedFormat('M Y'),
                'already_selected' => in_array($key, $selectedKeys, true),
                'employees_count' => $employeesWithAttendance,
                'total_clip_minutes' => (int) round($totalClipMinutes),
                'finalized_payslips' => $finalizedPayslips,
                'is_future' => $start->isFuture(),
            ];
        })->all();
    }

    /**
     * Preview which calculations can/cannot be posted to payslips for the cycle's payment month.
     * Returns: ['target_month'=>'06', 'target_year'=>2025, 'rows'=>[{calc, payslip_status, blocked, reason}]]
     */
    public function previewBonusToPayslip(int $cycleId): array
    {
        $cycle = BonusCycle::with('calculations.employee')->findOrFail($cycleId);
        $payDate = Carbon::parse($cycle->payment_date);
        $month = (int) $payDate->month;
        $year = (int) $payDate->year;

        $calcs = BonusCalculation::with('employee')
            ->where('cycle_id', $cycleId)
            ->where('status', 'approved')
            ->get();

        $rows = $calcs->map(function (BonusCalculation $c) use ($month, $year, $cycle) {
            $payslip = Payslip::where('employee_id', $c->employee_id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            $status = $payslip?->status ?? 'not_generated';
            $blocked = $status === 'finalized';
            $alreadyPosted = ExtraIncomeEntry::where('employee_id', $c->employee_id)
                ->where('month', $month)
                ->where('year', $year)
                ->where('notes', 'like', '%[BC#' . $c->id . ']%')
                ->exists();

            return [
                'calc_id' => $c->id,
                'employee_id' => $c->employee_id,
                'employee_name' => $c->employee?->full_name,
                'employee_code' => $c->employee?->employee_code,
                'amount' => (float) $c->actual_payment,
                'payslip_status' => $status,
                'payslip_id' => $payslip?->id,
                'blocked' => $blocked,
                'already_posted' => $alreadyPosted,
                'reason' => $blocked ? 'payslip ปิดแล้ว — ต้องเปิดก่อน' : ($alreadyPosted ? 'มีรายการโบนัสซ้ำในเดือนนี้แล้ว' : null),
            ];
        })->values()->all();

        return [
            'cycle_id' => $cycle->id,
            'cycle_code' => $cycle->cycle_code,
            'target_month' => $month,
            'target_year' => $year,
            'target_label' => $payDate->locale('th')->translatedFormat('F Y'),
            'rows' => $rows,
            'total_amount' => array_sum(array_column($rows, 'amount')),
            'blocked_count' => count(array_filter($rows, fn ($r) => $r['blocked'])),
            'duplicate_count' => count(array_filter($rows, fn ($r) => $r['already_posted'])),
            'eligible_count' => count(array_filter($rows, fn ($r) => !$r['blocked'] && !$r['already_posted'])),
        ];
    }

    /**
     * Post all approved bonus calculations as ExtraIncomeEntry for the cycle's payment month.
     * Throws DomainException if any payslip is finalized (caller should preview first).
     */
    public function postBonusToPayslip(int $cycleId, ?string $createdBy = null): array
    {
        $preview = $this->previewBonusToPayslip($cycleId);

        if ($preview['blocked_count'] > 0) {
            $names = collect($preview['rows'])->where('blocked', true)->pluck('employee_name')->implode(', ');
            throw new \DomainException("ไม่สามารถบันทึกโบนัสได้ — payslip {$preview['target_label']} ของ {$names} ปิดแล้ว กรุณาเปิด payslip ก่อน");
        }

        $created = [];
        DB::transaction(function () use ($preview, $cycleId, $createdBy, &$created) {
            foreach ($preview['rows'] as $row) {
                if ($row['already_posted']) continue;
                if ((float) $row['amount'] <= 0) continue;

                $prettyCode = strtoupper(str_replace('_', '-', $preview['cycle_code']));
                $entry = ExtraIncomeEntry::create([
                    'employee_id' => $row['employee_id'],
                    'month' => $preview['target_month'],
                    'year' => $preview['target_year'],
                    'label' => 'โบนัส ' . $prettyCode,
                    'category' => 'bonus',
                    'amount' => $row['amount'],
                    'include_in_payslip' => true,
                    'notes' => "โบนัสรอบ {$prettyCode} [BC#{$row['calc_id']}]",
                    'created_by' => null,
                ]);
                $created[] = $entry->id;
            }
        });

        return [
            'created_count' => count($created),
            'skipped_count' => count($preview['rows']) - count($created),
            'total_amount' => $preview['total_amount'],
            'target_label' => $preview['target_label'],
        ];
    }

    /**
     * Sum finalized editing-job video duration (in minutes) within a date window.
     * Bonus tier tracks performance for ALL editors regardless of payroll mode,
     * so we read EditingJob directly instead of WorkLog (which only exists for freelance_layer).
     */
    protected function sumEditingJobMinutes(Carbon $start, Carbon $end, ?int $employeeId = null): float
    {
        $query = EditingJob::query()
            ->where('status', 'final')
            ->where('is_deleted', false)
            ->whereBetween('finalized_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        if ($employeeId !== null) {
            $query->where('assigned_to', $employeeId);
        }

        return (float) $query->get(['video_duration_minutes', 'video_duration_seconds'])
            ->sum(fn (EditingJob $j) => (int) $j->video_duration_minutes + ((int) $j->video_duration_seconds / 60));
    }

    /**
     * Validate bonus input data, returns array of error messages.
     */
    public function validateInput(array $data): array
    {
        $errors = [];

        if (!Employee::find($data['employee_id'] ?? 0)) {
            $errors[] = 'Employee not found';
        }

        $cycle = BonusCycle::find($data['cycle_id'] ?? 0);
        if (!$cycle) {
            $errors[] = 'Cycle not found';
        } elseif ($cycle->isPaid()) {
            $errors[] = 'Cycle already paid';
        }

        $hasTierCode = isset($data['tier_id']) && trim((string) $data['tier_id']) !== '';
        $hasMetrics = array_key_exists('clip_duration_minutes_per_month', $data)
            || array_key_exists('qualified_months', $data);

        if ($hasTierCode) {
            if (!PerformanceTier::where('tier_code', $data['tier_id'])->where('is_active', true)->exists()) {
                $errors[] = 'Invalid tier';
            }
        } elseif (!$hasMetrics) {
            $errors[] = 'tier_id or clip metrics are required';
        }

        if (array_key_exists('clip_duration_minutes_per_month', $data) && (int) $data['clip_duration_minutes_per_month'] < 0) {
            $errors[] = 'Clip duration minutes per month must be non-negative';
        }

        if (array_key_exists('qualified_months', $data) && (int) $data['qualified_months'] < 0) {
            $errors[] = 'Qualified months must be non-negative';
        }

        if (!$hasTierCode && $hasMetrics) {
            $hasAllMetrics = array_key_exists('clip_duration_minutes_per_month', $data)
                && array_key_exists('qualified_months', $data);

            if (!$hasAllMetrics) {
                $errors[] = 'Both clip_duration_minutes_per_month and qualified_months are required when tier_id is omitted';
            } elseif (empty($errors)) {
                try {
                    $this->resolveTierCodeFromMetrics(
                        (int) $data['clip_duration_minutes_per_month'],
                        (int) $data['qualified_months'],
                    );
                } catch (\DomainException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        if (($data['base_reference'] ?? 0) <= 0) {
            $errors[] = 'Base reference must be positive';
        }

        $adj = $data['attendance_adjustment'] ?? 0;
        if ($adj < -1.0 || $adj > 1.0) {
            $errors[] = 'Attendance adjustment out of range';
        }

        foreach (['absent_days', 'late_count', 'leave_days'] as $field) {
            if (array_key_exists($field, $data) && (int) $data[$field] < 0) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be non-negative';
            }
        }

        return $errors;
    }

    /**
     * Calculate adjustment from detailed attendance metrics.
     *
     * @param array $config  Keys: absent_penalty_per_day(-0.01), late_penalty_per_occurrence(-0.002),
     *                             leave_free_days(5), leave_penalty_rate(0.01)
     */
    public function calculateAttendanceAdjustmentFromBreakdown(
        int $absentDays,
        int $lateCount,
        int $leaveDays,
        array $config = [],
    ): float {
        $absentPenaltyRate = (float) ($config['absent_penalty_per_day']      ?? -0.01);
        $latePenaltyRate   = (float) ($config['late_penalty_per_occurrence']  ?? -0.002);
        $freeDays          = (int)   ($config['leave_free_days']              ?? 5);
        $leaveRate         = (float) ($config['leave_penalty_rate']           ?? 0.01);

        $absentPenalty = $absentDays * $absentPenaltyRate;
        $latePenalty   = $lateCount  * $latePenaltyRate;

        // First $freeDays are penalized $leaveRate each, recover +$leaveRate per day after.
        $leaveAdjustment = $leaveDays <= $freeDays
            ? ($leaveDays * -$leaveRate)
            : (-$leaveRate * $freeDays + (($leaveDays - $freeDays) * $leaveRate));

        $total = $absentPenalty + $latePenalty + $leaveAdjustment;

        return round(max(-1.0, min(1.0, $total)), 4);
    }

    /**
     * Validate calculation result, returns array of warning messages.
     */
    public function validateResult(array $result, BonusCycle $cycle): array
    {
        $warnings = [];

        if (($result['final_bonus_net'] ?? 0) < 0) {
            $warnings[] = 'Final bonus is negative';
        }

        if (($result['is_active_on_payment'] ?? false) && ($result['actual_payment'] ?? 0) == 0) {
            $warnings[] = 'Active employee with zero payment';
        }

        if (($result['unlock_percentage'] ?? 0) > (float) $cycle->max_allocation) {
            $warnings[] = 'Unlock exceeds cycle maximum';
        }

        return $warnings;
    }
}
