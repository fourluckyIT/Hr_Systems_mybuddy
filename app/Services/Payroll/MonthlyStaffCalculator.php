<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\AttendanceRule;
use App\Models\PayrollItem;
use App\Services\SocialSecurityService;
use App\Services\Payroll\PayrollRuleService;
use App\Services\WorkCalendarService;

use Carbon\Carbon;

class MonthlyStaffCalculator
{
    public function __construct(
        protected SocialSecurityService $ssoService,
        protected PayrollRuleService $ruleService,
        protected WorkCalendarService $calendarService
    ) {}

    public function calculate(Employee $employee, int $month, int $year, $existingItems = null): array
    {
        $salaryProfile = $employee->salaryProfile;
        $baseSalary = $salaryProfile ? (float) $salaryProfile->base_salary : 0;

        // Load attendance for the month
        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->where('is_disabled', false)
            ->whereMonth('log_date', $month)
            ->whereYear('log_date', $year)
            ->orderBy('log_date')
            ->get();

        // Load rules
        $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
        $otRule = AttendanceRule::getActiveRule('ot_rate');
        $lateRule = AttendanceRule::getActiveRule('late_deduction');
        $moduleDefaultsRule = AttendanceRule::getActiveRule('module_defaults');
        $moduleDefaults = $moduleDefaultsRule?->config ?? [];
        $lunchBreakMinutes = (int) ($workingHoursRule?->config['lunch_break_minutes'] ?? 60);

        $enableOvertime = (bool) ($moduleDefaults['enable_overtime'] ?? true);
        $enableDiligence = (bool) ($moduleDefaults['enable_diligence'] ?? true);
        $otMultiplierWorkday = (float) ($otRule?->config['rate_multiplier_workday'] ?? $otRule?->config['rate_multiplier'] ?? 1.5);
        $otMultiplierHoliday = (float) ($otRule?->config['rate_multiplier_holiday'] ?? 3.0);
        $holidayRegularMultiplierMonthly = (float) ($otRule?->config['holiday_regular_multiplier_monthly'] ?? 1.0);
        $holidayLegalSplitEnabled = (bool) ($otRule?->config['enable_holiday_legal_split'] ?? true);
        $maxOtHours = (float) ($otRule?->config['max_ot_hours'] ?? 40);
        $weeklyOtLimitHours = (float) ($otRule?->config['weekly_ot_limit_hours'] ?? 36);
        $weeklyOtLimitMinutes = max(0, (int) round($weeklyOtLimitHours * 60));
        $monthlyOtLimitMinutes = max(0, (int) round($maxOtHours * 60));
        $targetMinutesPerDay = (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540);

        // New Global Method: Calculate Mon-Fri count for the rate divisor
        $weekDaysCount = $this->calendarService->getWeekDayCount($month, $year);
        $minuteRate = $this->ruleService->getMinuteRate($employee, $month, $year);

        $totalWorkMinutes = 0;
        $totalOtMinutes = 0;
        $workdayOtMinutes = 0;
        $holidayOtMinutes = 0;
        $holidayRegularMinutes = 0;
        $weeklyOtMinutes = [];
        $totalLateMinutes = 0;
        $lateCount = 0;
        $totalEarlyLeaveMinutes = 0;
        $earlyLeaveCount = 0;
        $lwopDays = 0;

        $otDates = [];
        $holidayRegularDates = [];
        $holidayOtDates = [];
        $lateDates = [];
        $earlyLeaveDates = [];
        $lwopDates = [];

        $formatLogDate = fn($log) => Carbon::parse($log->log_date)->format('j M');

        foreach ($attendanceLogs as $log) {
            $isWorkday = in_array($log->day_type, ['workday', 'ot_full_day']);
            $isLwop = $log->day_type === 'lwop' || $log->lwop_flag;

            if ($isWorkday) {
                $workedMinutes = (int) $log->working_minutes;
                $totalWorkMinutes += max(0, $workedMinutes - $lunchBreakMinutes);
            }

            // Holiday regular + OT split (§62/§63 Thai labour law).
            // ot_minutes on a holiday = net worked minutes (gross − lunch).
            // First targetMinutesPerDay net minutes → regular rate × multiplier.
            // Excess beyond targetMinutesPerDay → OT rate × otMultiplierHoliday.
            // Presence is inferred from check_in/out OR ot_minutes > 0.
            $isHolidayLike = in_array((string) $log->day_type, ['holiday', 'company_holiday'], true);
            if ($isHolidayLike && $holidayLegalSplitEnabled) {
                $showedUp = (!empty($log->check_in) && !empty($log->check_out))
                    || (int) $log->ot_minutes > 0;
                if ($showedUp) {
                    $holidayRegularMinutes += $targetMinutesPerDay;
                    $dayAmount = round($targetMinutesPerDay * $minuteRate * $holidayRegularMultiplierMonthly, 2);
                    $holidayRegularDates[] = $formatLogDate($log) . " (วันหยุด) " . number_format($dayAmount, 2);
                }
            }

            if ($log->ot_enabled && $log->ot_minutes > 0) {
                $isHolidayOt = $isHolidayLike;
                // ot_minutes now stores clock-based OT (minutes past standard checkout)
                // for both workday and holiday, so no further conversion needed.
                $candidateOtMinutes = (int) $log->ot_minutes;

                if ($candidateOtMinutes > 0) {
                    $weekStart = Carbon::parse($log->log_date)->startOfWeek(Carbon::MONDAY)->toDateString();
                    $weekUsedMinutes = $weeklyOtMinutes[$weekStart] ?? 0;
                    $weekRemainingMinutes = max(0, $weeklyOtLimitMinutes - $weekUsedMinutes);
                    $allowedOtMinutes = (int) min($candidateOtMinutes, $weekRemainingMinutes);

                    if ($allowedOtMinutes > 0) {
                        $weeklyOtMinutes[$weekStart] = $weekUsedMinutes + $allowedOtMinutes;

                        if ($isHolidayOt) {
                            $holidayOtMinutes += $allowedOtMinutes;
                            $dayAmount = round($allowedOtMinutes * $minuteRate * $otMultiplierHoliday, 2);
                            $holidayOtDates[] = $formatLogDate($log) . " (OT วันหยุด) " . number_format($dayAmount, 2);
                        } else {
                            $workdayOtMinutes += $allowedOtMinutes;
                            $dayAmount = round($allowedOtMinutes * $minuteRate * $otMultiplierWorkday, 2);
                            $otDates[] = $formatLogDate($log) . " (OT) " . number_format($dayAmount, 2);
                        }
                    }
                }
            }

            // Track lates and early leaves for workdays
            if ($isWorkday) {
                if ($log->late_minutes > 0) {
                    $totalLateMinutes += (int) $log->late_minutes;
                    $lateCount++;
                    $dayAmount = round((int)$log->late_minutes * $minuteRate, 2);
                    $lateDates[] = $formatLogDate($log) . " (สาย) " . number_format($dayAmount, 2);
                }
                if ($log->early_leave_minutes > 0) {
                    $totalEarlyLeaveMinutes += (int) $log->early_leave_minutes;
                    $earlyLeaveCount++;
                    $dayAmount = round((int)$log->early_leave_minutes * $minuteRate, 2);
                    $earlyLeaveDates[] = $formatLogDate($log) . " (ออกเร็ว) " . number_format($dayAmount, 2);
                }
            }

            if ($isLwop) {
                $lwopDays++;
                $dayAmount = ($weekDaysCount > 0) ? round(($baseSalary / $weekDaysCount), 2) : 0;
                $lwopDates[] = $formatLogDate($log) . " (ขาดงาน) " . number_format($dayAmount, 2);
            }
        }

        $totalOtMinutes = $workdayOtMinutes + $holidayOtMinutes;

        // Additional internal cap for month policy (kept for compatibility).
        if ($totalOtMinutes > $monthlyOtLimitMinutes) {
            $excessMinutes = $totalOtMinutes - $monthlyOtLimitMinutes;

            // Trim holiday OT first because it has higher payout multiplier.
            if ($holidayOtMinutes > 0) {
                $trimHoliday = min($holidayOtMinutes, $excessMinutes);
                $holidayOtMinutes -= $trimHoliday;
                $excessMinutes -= $trimHoliday;
            }

            if ($excessMinutes > 0 && $workdayOtMinutes > 0) {
                $workdayOtMinutes = max(0, $workdayOtMinutes - $excessMinutes);
            }

            $totalOtMinutes = $workdayOtMinutes + $holidayOtMinutes;
        }

        $totalOtHours = round($totalOtMinutes / 60, 2);

        // Total working hours (excluding break, in hours)
        $totalWorkHours = round($totalWorkMinutes / 60, 2);

        // Holiday regular pay (phase-1 legal split)
        $holidayWorkPay = ($enableOvertime && $holidayLegalSplitEnabled)
            ? round($holidayRegularMinutes * $minuteRate * $holidayRegularMultiplierMonthly, 2)
            : 0;

        // OT pay = overtime-only minutes * minuteRate * multiplier
        $overtimePay = $enableOvertime
            ? round(($workdayOtMinutes * $minuteRate * $otMultiplierWorkday) + ($holidayOtMinutes * $minuteRate * $otMultiplierHoliday), 2)
            : 0;

        // Diligence allowance logic (Tiered - Global via RuleService)
        // Guard: require attendance records for modes that clock in.
        // youtuber_salary doesn't clock in — always eligible when enabled.
        $isYoutuberSalary = $employee->payroll_mode === 'youtuber_salary';
        $hasAttendanceData = $attendanceLogs->isNotEmpty() || $isYoutuberSalary;
        $diligenceAmount = ($enableDiligence && $hasAttendanceData)
            ? $this->ruleService->calculateDiligence($lateCount, $lwopDays)
            : 0;

        // LWOP deduction (Salary / Mon-Fri Days * LwopDays)
        $lwopDeduction = 0;
        if ($lwopDays > 0 && $weekDaysCount > 0) {
            $lwopDeduction = round(($baseSalary / $weekDaysCount) * $lwopDays, 2);
        }

        // Late deduction (Salary-Proportional, monthly grace quota)
        $lateDeduction = 0;
        if ($employee->isModuleEnabled('deduct_late') && $lateRule && $lateRule->config['type'] !== 'none') {
            $grace = (int) ($lateRule->config['grace_period_minutes'] ?? 0);
            $billableLateMinutes = max(0, $totalLateMinutes - $grace);
            $lateDeduction = round($billableLateMinutes * $minuteRate, 2);
        }

        // Early Leave deduction (Salary-Proportional)
        $earlyLeaveDeduction = 0;
        if ($employee->isModuleEnabled('deduct_early')) {
            // Usually early leave doesn't have a grace period in the same way, but we can reuse the same rate.
            $earlyLeaveDeduction = round($totalEarlyLeaveMinutes * $minuteRate, 2);
        }

        // Social Security
        $ssoEmployee = 0;
        if ($employee->isModuleEnabled('sso_deduction')) {
            $sso = $this->ssoService->calculate($baseSalary, "$year-$month-01");
            $ssoEmployee = $sso['employee'];
        }

        // Build items
        $items = [];
        $sortOrder = 0;

        $items[] = $this->resolveItem('base_salary', 'income', 'ฐานเงินเดือน', $baseSalary, 'master', ++$sortOrder, $existingItems);
        $items[] = $this->resolveItem('holiday_work_pay', 'income', 'ค่าทำงานวันหยุด', $holidayWorkPay, 'auto', ++$sortOrder, $existingItems, !empty($holidayRegularDates) ? implode(', ', array_unique($holidayRegularDates)) : null);
        
        $otNoteParts = [];
        if (!empty($otDates)) $otNoteParts[] = 'ปกติ: ' . implode(', ', array_unique($otDates));
        if (!empty($holidayOtDates)) $otNoteParts[] = 'วันหยุด: ' . implode(', ', array_unique($holidayOtDates));
        $otNote = !empty($otNoteParts) ? implode('; ', $otNoteParts) : null;
        $items[] = $this->resolveItem('overtime', 'income', 'ค่าล่วงเวลา', $overtimePay, 'auto', ++$sortOrder, $existingItems, $otNote);
        
        $items[] = $this->resolveItem('diligence', 'income', 'เบี้ยขยัน', $diligenceAmount, 'auto', ++$sortOrder, $existingItems);

        $sortOrder = 0;
        $items[] = $this->resolveItem('cash_advance', 'deduction', 'เงินหักล่วงหน้า', 0, 'manual', ++$sortOrder, $existingItems);
        $items[] = $this->resolveItem('lwop', 'deduction', 'ขาดงาน', $lwopDeduction, 'auto', ++$sortOrder, $existingItems, !empty($lwopDates) ? implode(', ', array_unique($lwopDates)) : null);
        $items[] = $this->resolveItem('late_deduction', 'deduction', 'มาสาย', $lateDeduction, 'auto', ++$sortOrder, $existingItems, !empty($lateDates) ? implode(', ', array_unique($lateDates)) : null);
        $items[] = $this->resolveItem('early_leave_deduction', 'deduction', 'ออกเร็ว', $earlyLeaveDeduction, 'auto', ++$sortOrder, $existingItems, !empty($earlyLeaveDates) ? implode(', ', array_unique($earlyLeaveDates)) : null);
        $items[] = $this->resolveItem('sso_employee', 'deduction', 'ประกันสังคม', $ssoEmployee, 'auto', ++$sortOrder, $existingItems);

        $totalIncome = collect($items)->where('category', 'income')->sum('amount');
        $totalDeduction = collect($items)->where('category', 'deduction')->sum('amount');
        $netPay = round($totalIncome - $totalDeduction, 2);

        return [
            'items' => $items,
            'summary' => [
                'total_work_hours' => $totalWorkHours,
                'total_ot_hours' => $totalOtHours,
                'workday_ot_minutes' => $workdayOtMinutes,
                'holiday_ot_minutes' => $holidayOtMinutes,
                'holiday_regular_minutes' => $holidayRegularMinutes,
                'late_count' => $lateCount,
                'late_minutes' => $totalLateMinutes,
                'early_leave_count' => $earlyLeaveCount,
                'early_leave_minutes' => $totalEarlyLeaveMinutes,
                'lwop_days' => $lwopDays,
                'total_income' => $totalIncome,
                'total_deduction' => $totalDeduction,
                'net_pay' => $netPay,
            ],
        ];
    }

    protected function resolveItem(string $code, string $category, string $label, float $calculatedAmount, string $defaultSource, int $sortOrder, $existingItems = null, ?string $note = null): array
    {
        // If there's an existing item with the same code and it is 'manual' or 'override', keep it.
        if ($existingItems) {
            $existing = collect($existingItems)->where('item_type_code', $code)->first();
            if ($existing && in_array($existing['source_flag'], ['manual', 'override'])) {
                return [
                    'item_type_code' => $code,
                    'category' => $category,
                    'label' => $label,
                    'amount' => (float) $existing['amount'],
                    'source_flag' => $existing['source_flag'],
                    'sort_order' => $sortOrder,
                    'note' => $existing['notes'] ?? $existing['note'] ?? null,
                ];
            }
        }

        return [
            'item_type_code' => $code,
            'category' => $category,
            'label' => $label,
            'amount' => round($calculatedAmount, 2),
            'source_flag' => $defaultSource,
            'sort_order' => $sortOrder,
            'note' => $note,
        ];
    }
}
