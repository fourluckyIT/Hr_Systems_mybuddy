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
        $otCfg = $otRule?->config ?? [];

        $otMultiplierWorkday = (float) ($otCfg['rate_multiplier_workday'] ?? $otCfg['rate_multiplier'] ?? 1.5);
        $otMultiplierHolidayLegacy = (float) ($otCfg['rate_multiplier_holiday'] ?? 3.0);
        // Per-day-type multipliers — fall back to legacy holiday multiplier so old configs keep working.
        $otMultiplierWeeklyOff = (float) ($otCfg['rate_multiplier_weekly_off'] ?? $otMultiplierHolidayLegacy);
        $otMultiplierCompanyHoliday = (float) ($otCfg['rate_multiplier_company_holiday'] ?? $otMultiplierHolidayLegacy);

        $holidayRegularMultiplierLegacy = (float) ($otCfg['holiday_regular_multiplier_monthly'] ?? 1.0);
        $regularMultiplierWeeklyOff = (float) ($otCfg['regular_multiplier_weekly_off'] ?? $holidayRegularMultiplierLegacy);
        $regularMultiplierCompanyHoliday = (float) ($otCfg['regular_multiplier_company_holiday'] ?? $holidayRegularMultiplierLegacy);

        $allowOtWeeklyOff = (bool) ($otCfg['allow_ot_weekly_off'] ?? true);
        $allowOtCompanyHoliday = (bool) ($otCfg['allow_ot_company_holiday'] ?? true);

        $holidayLegalSplitEnabled = (bool) ($otCfg['enable_holiday_legal_split'] ?? true);
        $maxOtHours = (float) ($otCfg['max_ot_hours'] ?? 40);
        $weeklyOtLimitHours = (float) ($otCfg['weekly_ot_limit_hours'] ?? 36);
        $dailyOtLimitHours = (float) ($otCfg['daily_ot_limit_hours'] ?? 0); // 0 = no daily cap
        $weeklyOtLimitMinutes = max(0, (int) round($weeklyOtLimitHours * 60));
        $monthlyOtLimitMinutes = max(0, (int) round($maxOtHours * 60));
        $dailyOtLimitMinutes = max(0, (int) round($dailyOtLimitHours * 60));
        $targetMinutesPerDay = (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540);

        // New Global Method: Calculate Mon-Fri count for the rate divisor
        $weekDaysCount = $this->calendarService->getWeekDayCount($month, $year);
        $minuteRate = $this->ruleService->getMinuteRate($employee, $month, $year);

        $totalWorkMinutes = 0;
        $totalOtMinutes = 0;
        $workdayOtMinutes = 0;
        // Per-type buckets so weekly-off and company-holiday can use different multipliers
        // and survive the monthly cap correctly.
        $weeklyOffOtMinutes = 0;
        $companyHolidayOtMinutes = 0;
        $weeklyOffRegularMinutes = 0;
        $companyHolidayRegularMinutes = 0;
        $weeklyOtMinutes = [];
        $totalLateMinutes = 0;
        $lateCount = 0;
        $totalEarlyLeaveMinutes = 0;
        $earlyLeaveCount = 0;
        $lwopDays = 0;
        $attendedDays = 0;

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

            // Distinguish weekly off (Sat/Sun) vs company/public holiday — different rates allowed.
            $isWeeklyOff = (string) $log->day_type === 'holiday';
            $isCompanyHoliday = (string) $log->day_type === 'company_holiday';
            $isHolidayLike = $isWeeklyOff || $isCompanyHoliday;

            $regularMultiplier = $isCompanyHoliday ? $regularMultiplierCompanyHoliday : $regularMultiplierWeeklyOff;
            $otMultiplierForHoliday = $isCompanyHoliday ? $otMultiplierCompanyHoliday : $otMultiplierWeeklyOff;
            $allowOtForThisDay = $isCompanyHoliday ? $allowOtCompanyHoliday : ($isWeeklyOff ? $allowOtWeeklyOff : true);

            // Holiday regular + OT split (§62/§63 Thai labour law).
            if ($isHolidayLike && $holidayLegalSplitEnabled) {
                $showedUp = (!empty($log->check_in) && !empty($log->check_out))
                    || (int) $log->ot_minutes > 0;
                if ($showedUp) {
                    if ($isCompanyHoliday) {
                        $companyHolidayRegularMinutes += $targetMinutesPerDay;
                    } else {
                        $weeklyOffRegularMinutes += $targetMinutesPerDay;
                    }
                    $dayAmount = round($targetMinutesPerDay * $minuteRate * $regularMultiplier, 2);
                    $label = $isCompanyHoliday ? 'วันหยุดบริษัท' : 'วันหยุดสัปดาห์';
                    $holidayRegularDates[] = $formatLogDate($log) . " ($label) " . number_format($dayAmount, 2);
                }
            }

            if ($log->ot_enabled && $log->ot_minutes > 0 && (!$isHolidayLike || $allowOtForThisDay)) {
                $candidateOtMinutes = (int) $log->ot_minutes;

                // Per-day cap (0 = disabled).
                if ($dailyOtLimitMinutes > 0) {
                    $candidateOtMinutes = min($candidateOtMinutes, $dailyOtLimitMinutes);
                }

                if ($candidateOtMinutes > 0) {
                    $weekStart = Carbon::parse($log->log_date)->startOfWeek(Carbon::MONDAY)->toDateString();
                    $weekUsedMinutes = $weeklyOtMinutes[$weekStart] ?? 0;
                    $weekRemainingMinutes = max(0, $weeklyOtLimitMinutes - $weekUsedMinutes);
                    $allowedOtMinutes = (int) min($candidateOtMinutes, $weekRemainingMinutes);

                    if ($allowedOtMinutes > 0) {
                        $weeklyOtMinutes[$weekStart] = $weekUsedMinutes + $allowedOtMinutes;

                        if ($isHolidayLike) {
                            if ($isCompanyHoliday) {
                                $companyHolidayOtMinutes += $allowedOtMinutes;
                            } else {
                                $weeklyOffOtMinutes += $allowedOtMinutes;
                            }
                            $dayAmount = round($allowedOtMinutes * $minuteRate * $otMultiplierForHoliday, 2);
                            $tag = $isCompanyHoliday ? 'OT วันหยุดบริษัท' : 'OT วันหยุดสัปดาห์';
                            $holidayOtDates[] = $formatLogDate($log) . " ($tag) " . number_format($dayAmount, 2);
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
                if (!empty($log->check_in)) {
                    $attendedDays++;
                }
                if ($log->late_minutes > 0) {
                    $totalLateMinutes += (int) $log->late_minutes;
                    $lateCount++;
                    $lateDates[] = $formatLogDate($log) . ' (สาย ' . (int)$log->late_minutes . ' นาที)';
                }
                if ($log->early_leave_minutes > 0) {
                    $totalEarlyLeaveMinutes += (int) $log->early_leave_minutes;
                    $earlyLeaveCount++;
                    $earlyLeaveDates[] = $formatLogDate($log) . ' (ออกก่อนเวลา ' . (int)$log->early_leave_minutes . ' นาที)';
                }
            }

            if ($isLwop) {
                $lwopDays++;
                $dayAmount = ($weekDaysCount > 0) ? round(($baseSalary / $weekDaysCount), 2) : 0;
                $lwopDates[] = $formatLogDate($log) . " (ขาดงาน) " . number_format($dayAmount, 2);
            }
        }

        $holidayOtMinutes = $weeklyOffOtMinutes + $companyHolidayOtMinutes;
        $holidayRegularMinutes = $weeklyOffRegularMinutes + $companyHolidayRegularMinutes;
        $totalOtMinutes = $workdayOtMinutes + $holidayOtMinutes;

        // Additional internal cap for month policy. Trim holiday OT first because it pays more,
        // then weekly-off vs company-holiday in that order, then workday OT.
        if ($totalOtMinutes > $monthlyOtLimitMinutes) {
            $excessMinutes = $totalOtMinutes - $monthlyOtLimitMinutes;

            $trim = min($companyHolidayOtMinutes, $excessMinutes);
            $companyHolidayOtMinutes -= $trim; $excessMinutes -= $trim;

            $trim = min($weeklyOffOtMinutes, $excessMinutes);
            $weeklyOffOtMinutes -= $trim; $excessMinutes -= $trim;

            if ($excessMinutes > 0 && $workdayOtMinutes > 0) {
                $workdayOtMinutes = max(0, $workdayOtMinutes - $excessMinutes);
            }

            $holidayOtMinutes = $weeklyOffOtMinutes + $companyHolidayOtMinutes;
            $totalOtMinutes = $workdayOtMinutes + $holidayOtMinutes;
        }

        $totalOtHours = round($totalOtMinutes / 60, 2);

        // Total working hours (excluding break, in hours)
        $totalWorkHours = round($totalWorkMinutes / 60, 2);

        // Holiday regular pay (phase-1 legal split) — per-type minute buckets × per-type multiplier.
        $holidayWorkPay = ($enableOvertime && $holidayLegalSplitEnabled)
            ? round(
                ($weeklyOffRegularMinutes      * $minuteRate * $regularMultiplierWeeklyOff) +
                ($companyHolidayRegularMinutes * $minuteRate * $regularMultiplierCompanyHoliday),
                2
            )
            : 0;

        // OT pay — workday + (weekly-off OT × its multiplier) + (company-holiday OT × its multiplier).
        $overtimePay = $enableOvertime
            ? round(
                ($workdayOtMinutes        * $minuteRate * $otMultiplierWorkday) +
                ($weeklyOffOtMinutes      * $minuteRate * $otMultiplierWeeklyOff) +
                ($companyHolidayOtMinutes * $minuteRate * $otMultiplierCompanyHoliday),
                2
            )
            : 0;

        // Diligence allowance logic (Tiered - Global via RuleService)
        // Guard: require attendance records for modes that clock in.
        // youtuber_salary doesn't clock in — always eligible when enabled.
        $isYoutuberSalary = $employee->payroll_mode === 'youtuber_salary';
        $hasAttendanceData = $attendanceLogs->isNotEmpty() || $isYoutuberSalary;
        $diligenceAmount = ($enableDiligence && $hasAttendanceData)
            ? $this->ruleService->calculateDiligence([
                'lwopDays' => $lwopDays,
                'lateCount' => $lateCount,
                'lateMinutes' => $totalLateMinutes,
                'earlyLeaveCount' => $earlyLeaveCount,
                'attendedDays' => $isYoutuberSalary ? PHP_INT_MAX : $attendedDays,
            ])
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
        $items[] = $this->resolveItem('holiday_work_pay', 'income', 'ค่าทำงานวันหยุด', $holidayWorkPay, 'auto', ++$sortOrder, $existingItems, !empty($holidayRegularDates) ? implode("\n", array_unique($holidayRegularDates)) : null);

        // Flat one-entry-per-bullet — each line already carries its own tag like "(OT)" or "(OT วันหยุดบริษัท)"
        $otAllEntries = array_unique(array_merge($otDates, $holidayOtDates));
        $otNote = !empty($otAllEntries) ? implode("\n", $otAllEntries) : null;
        $items[] = $this->resolveItem('overtime', 'income', 'ค่าล่วงเวลา', $overtimePay, 'auto', ++$sortOrder, $existingItems, $otNote);

        $items[] = $this->resolveItem('diligence', 'income', 'เบี้ยขยัน', $diligenceAmount, 'auto', ++$sortOrder, $existingItems);

        $sortOrder = 0;
        $items[] = $this->resolveItem('cash_advance', 'deduction', 'เงินหักล่วงหน้า', 0, 'manual', ++$sortOrder, $existingItems);
        $items[] = $this->resolveItem('lwop', 'deduction', 'ขาดงาน', $lwopDeduction, 'auto', ++$sortOrder, $existingItems, !empty($lwopDates) ? implode("\n", array_unique($lwopDates)) : null);
        // If late minutes accumulated but deduction is 0 (per-employee toggle off, global rule disabled,
        // or grace ate it), prepend a specific explanation so admins know where to fix it.
        $lateNote = !empty($lateDates) ? implode("\n", array_unique($lateDates)) : null;
        if ($lateNote && $lateDeduction == 0 && $totalLateMinutes > 0) {
            if (!$employee->isModuleEnabled('deduct_late')) {
                $reason = 'กฎหักมาสายถูกปิดเฉพาะพนักงานคนนี้ — กดปุ่ม LATE บนหัว workspace เพื่อเปิด';
            } elseif (!$lateRule || ($lateRule->config['type'] ?? 'none') === 'none') {
                $reason = 'กฎหักมาสายปิดทั้งบริษัท (ตั้งค่า → กฎคำนวณ → หักเงิน)';
            } else {
                $reason = 'ภายในช่วงผ่อนผัน ' . ((int)($lateRule->config['grace_period_minutes'] ?? 0)) . ' นาที — ไม่หักเงิน';
            }
            $lateNote = $reason . "\n" . $lateNote;
        }
        $earlyNote = !empty($earlyLeaveDates) ? implode("\n", array_unique($earlyLeaveDates)) : null;
        if ($earlyNote && $earlyLeaveDeduction == 0 && $totalEarlyLeaveMinutes > 0) {
            $earlyNote = (!$employee->isModuleEnabled('deduct_early')
                ? 'กฎหักออกก่อนเวลาถูกปิดเฉพาะพนักงานคนนี้ — กดปุ่ม EARLY บนหัว workspace เพื่อเปิด'
                : 'กฎหักออกก่อนเวลาปิดทั้งบริษัท') . "\n" . $earlyNote;
        }
        $items[] = $this->resolveItem('late_deduction', 'deduction', 'มาสาย', $lateDeduction, 'auto', ++$sortOrder, $existingItems, $lateNote);
        $items[] = $this->resolveItem('early_leave_deduction', 'deduction', 'ออกก่อนเวลา', $earlyLeaveDeduction, 'auto', ++$sortOrder, $existingItems, $earlyNote);
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
