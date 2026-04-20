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
        $targetMinutesPerDay = (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540); // Standard 9 hours including break? 
        // Note: target_minutes_per_day is usually 540 (9 hours) or 480 (8 hours).
        
        $totalMonthlyMinutes = $weekDaysCount * $targetMinutesPerDay;

        return $totalMonthlyMinutes > 0 ? $baseSalary / $totalMonthlyMinutes : 0;
    }

    /**
     * Resolve the diligence amount based on tiered rules.
     */
    public function calculateDiligence(int $lateCount, int $lwopDays): float
    {
        $diligenceRule = AttendanceRule::getActiveRule('diligence');
        
        if (!$diligenceRule) return 0;

        $config = $diligenceRule->config;
        
        if ($config['use_tiers'] ?? false) {
            $tiers = collect($config['tiers'] ?? [])->sortByDesc(fn($t) => (float)$t['amount']);
            foreach ($tiers as $tier) {
                if ($lateCount <= ($tier['late_count_max'] ?? 0) && $lwopDays <= ($tier['lwop_days_max'] ?? 0)) {
                    return (float) $tier['amount'];
                }
            }
        } else {
            $requireZeroLate = $config['require_zero_late'] ?? true;
            $requireZeroLwop = $config['require_zero_lwop'] ?? true;
            if ((!$requireZeroLate || $lateCount === 0) && (!$requireZeroLwop || $lwopDays === 0)) {
                return (float) ($config['amount'] ?? 500);
            }
        }

        return 0;
    }
}
