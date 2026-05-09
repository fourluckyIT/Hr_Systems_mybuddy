<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\AttendanceRule;
use App\Services\WorkCalendarService;

class PayrollRuleService
{
    public function __construct(
        protected WorkCalendarService $calendarService
    ) {}

    /**
     * Calculate the "Rate per Minute" based on salary and actual week-day count.
     * Formula: Base Salary / (WeekDays In Month * WorkHours Per Day * 60)
     */
    public function getMinuteRate(Employee $employee, int $month, int $year): float
    {
        $salaryProfile = $employee->salaryProfile;
        $baseSalary = $salaryProfile ? (float) $salaryProfile->base_salary : 0;

        if ($baseSalary <= 0) return 0;

        $weekDaysCount = $this->calendarService->getWeekDayCount($month, $year);

        $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
        // Gross minutes per day (e.g. 9 h = 540 min)
        $grossMinutesPerDay = (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540);
        // Lunch-break deduction that MonthlyStaffCalculator also removes before counting work time
        $lunchBreakMinutes  = (int) ($workingHoursRule?->config['lunch_break_minutes'] ?? 60);
        // Net payable minutes per day — must mirror the accumulator in MonthlyStaffCalculator
        $netMinutesPerDay = max(1, $grossMinutesPerDay - $lunchBreakMinutes);

        $totalMonthlyMinutes = $weekDaysCount * $netMinutesPerDay;

        return $totalMonthlyMinutes > 0 ? $baseSalary / $totalMonthlyMinutes : 0;
    }

    /**
     * Resolve the diligence amount based on tiered rules.
     *
     * Each tier can opt-in to any subset of these checks:
     *   - check_lwop          / lwop_max          (วันขาดงาน ≤ N)
     *   - check_late_count    / late_count_max    (จำนวนครั้งที่สาย ≤ N)
     *   - check_late_minutes  / late_minutes_max  (นาทีสายรวม ≤ N)
     *   - check_early_leave   / early_leave_max   (ครั้งออกก่อน ≤ N)
     *   - check_min_attended  / min_attended_days (วันมีเช็คอิน ≥ N)
     *
     * Tiers are evaluated from highest amount downward; first fully-passing tier wins.
     */
    public function calculateDiligence(array $metrics): float
    {
        $diligenceRule = AttendanceRule::getActiveRule('diligence');
        if (!$diligenceRule) return 0;

        $tiers = collect($diligenceRule->config['tiers'] ?? [])
            ->sortByDesc(fn($t) => (float) ($t['amount'] ?? 0));

        foreach ($tiers as $tier) {
            if ($this->tierPasses($tier, $metrics)) {
                return (float) ($tier['amount'] ?? 0);
            }
        }

        return 0;
    }

    protected function tierPasses(array $tier, array $metrics): bool
    {
        // Legacy bridge: tiers saved before the toggle UI used late_count_max + lwop_days_max
        // as implicit checks. If no check_* flag is present, infer them from those keys.
        $hasFlags = isset($tier['check_lwop']) || isset($tier['check_late_count'])
            || isset($tier['check_late_minutes']) || isset($tier['check_early_leave'])
            || isset($tier['check_min_attended']);
        if (!$hasFlags) {
            $tier['check_lwop'] = isset($tier['lwop_days_max']);
            $tier['lwop_max'] = $tier['lwop_days_max'] ?? 0;
            $tier['check_late_count'] = isset($tier['late_count_max']);
        }

        $checks = [
            ['check_lwop',         'lwop_max',          $metrics['lwopDays']         ?? 0, 'lte'],
            ['check_late_count',   'late_count_max',    $metrics['lateCount']        ?? 0, 'lte'],
            ['check_late_minutes', 'late_minutes_max',  $metrics['lateMinutes']      ?? 0, 'lte'],
            ['check_early_leave',  'early_leave_max',   $metrics['earlyLeaveCount']  ?? 0, 'lte'],
            ['check_min_attended', 'min_attended_days', $metrics['attendedDays']     ?? 0, 'gte'],
        ];

        foreach ($checks as [$flagKey, $valueKey, $actual, $op]) {
            if (empty($tier[$flagKey])) continue;
            $threshold = (float) ($tier[$valueKey] ?? 0);
            if ($op === 'lte' && $actual > $threshold) return false;
            if ($op === 'gte' && $actual < $threshold) return false;
        }

        return true;
    }
}
