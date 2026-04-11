<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\AttendanceRule;
use App\Models\PayrollItem;
use App\Services\SocialSecurityService;

class MonthlyStaffCalculator
{
    public function __construct(
        protected SocialSecurityService $ssoService
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
        $diligenceRule = AttendanceRule::getActiveRule('diligence');
        $otRule = AttendanceRule::getActiveRule('ot_rate');

        $targetMinutesPerDay = $workingHoursRule?->config['target_minutes_per_day'] ?? 540;
        $workingDaysPerMonth = $workingHoursRule?->config['working_days_per_month'] ?? 22;
        $targetMonthlyMinutes = $targetMinutesPerDay * $workingDaysPerMonth;

        // Calculate attendance summary
        $totalWorkMinutes = 0;
        $totalOtMinutes = 0;
        $totalLateMinutes = 0;
        $lateCount = 0;
        $lwopDays = 0;

        foreach ($attendanceLogs as $log) {
            $isWorkday = in_array($log->day_type, ['workday', 'ot_full_day']);
            $isLwop = $log->day_type === 'lwop' || $log->lwop_flag;

            if ($isWorkday) {
                $totalWorkMinutes += $log->working_minutes;
            }

            if ($log->ot_enabled && $log->ot_minutes > 0) {
                $totalOtMinutes += $log->ot_minutes;
            }

            // Only track lates for workdays
            if ($isWorkday && $log->late_minutes > 0) {
                $totalLateMinutes += $log->late_minutes;
                $lateCount++;
            }

            if ($isLwop) {
                $lwopDays++;
            }
        }

        // Cap OT hours
        $maxOtHours = $otRule?->config['max_ot_hours'] ?? 40;
        $maxOtMinutes = $maxOtHours * 60;
        $totalOtMinutes = min($totalOtMinutes, $maxOtMinutes);
        $totalOtHours = round($totalOtMinutes / 60, 2);

        // Total working hours (excluding break, in hours)
        $totalWorkHours = round($totalWorkMinutes / 60, 2);

        // OT pay = OT minutes * (base_salary / target_monthly_minutes)
        $ratePerMinute = $targetMonthlyMinutes > 0 ? $baseSalary / $targetMonthlyMinutes : 0;
        $otMultiplier = $otRule?->config['rate_multiplier'] ?? 1.0;
        $overtimePay = round($totalOtMinutes * $ratePerMinute * $otMultiplier, 2);

        // Diligence allowance logic (Tiered)
        $diligenceAmount = 0;
        if ($diligenceRule) {
            $config = $diligenceRule->config;
            if ($config['use_tiers'] ?? false) {
                $tiers = collect($config['tiers'] ?? [])->sortByDesc('amount');
                foreach ($tiers as $tier) {
                    if ($lateCount <= ($tier['late_count_max'] ?? 0) && $lwopDays <= ($tier['lwop_days_max'] ?? 0)) {
                        $diligenceAmount = (float) $tier['amount'];
                        break; // Found the best fit
                    }
                }
            } else {
                // Classic logic
                $requireZeroLate = $config['require_zero_late'] ?? true;
                $requireZeroLwop = $config['require_zero_lwop'] ?? true;
                $meetsLate = !$requireZeroLate || $lateCount === 0;
                $meetsLwop = !$requireZeroLwop || $lwopDays === 0;
                if ($meetsLate && $meetsLwop) {
                    $diligenceAmount = (float) ($config['amount'] ?? 500);
                }
            }
        }

        // Performance bonus (placeholder)
        $performanceBonus = 0;

        // LWOP deduction
        $lwopDeduction = 0;
        if ($lwopDays > 0 && $workingDaysPerMonth > 0) {
            $lwopDeduction = round(($baseSalary / $workingDaysPerMonth) * $lwopDays, 2);
        }

        // Late deduction
        $lateDeduction = 0;
        $lateRule = AttendanceRule::getActiveRule('late_deduction');
        if ($lateRule && $lateRule->config['type'] !== 'none') {
            $ratePerMin = (float) ($lateRule->config['rate_per_minute'] ?? 0);
            $grace = (int) ($lateRule->config['grace_period_minutes'] ?? 0);
            $billableLateMinutes = max(0, $totalLateMinutes - ($grace * $lateCount));
            $lateDeduction = round($billableLateMinutes * $ratePerMin, 2);
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
        $items[] = $this->resolveItem('overtime', 'income', 'ค่าล่วงเวลา', $overtimePay, 'auto', ++$sortOrder, $existingItems);
        $items[] = $this->resolveItem('diligence', 'income', 'เบี้ยขยัน', $diligenceAmount, 'auto', ++$sortOrder, $existingItems);
        $items[] = $this->resolveItem('performance', 'income', 'ค่าประสิทธิภาพ', $performanceBonus, 'auto', ++$sortOrder, $existingItems);

        $sortOrder = 0;
        $items[] = $this->resolveItem('cash_advance', 'deduction', 'เงินหักล่วงหน้า', 0, 'manual', ++$sortOrder, $existingItems);
        $items[] = $this->resolveItem('lwop', 'deduction', 'ขาดงาน', $lwopDeduction, 'auto', ++$sortOrder, $existingItems);
        $items[] = $this->resolveItem('late_deduction', 'deduction', 'มาสาย', $lateDeduction, 'auto', ++$sortOrder, $existingItems);
        $items[] = $this->resolveItem('sso_employee', 'deduction', 'ประกันสังคม', $ssoEmployee, 'auto', ++$sortOrder, $existingItems);

        $totalIncome = collect($items)->where('category', 'income')->sum('amount');
        $totalDeduction = collect($items)->where('category', 'deduction')->sum('amount');
        $netPay = round($totalIncome - $totalDeduction, 2);

        return [
            'items' => $items,
            'summary' => [
                'total_work_hours' => $totalWorkHours,
                'total_ot_hours' => $totalOtHours,
                'late_count' => $lateCount,
                'late_minutes' => $totalLateMinutes,
                'lwop_days' => $lwopDays,
                'total_income' => $totalIncome,
                'total_deduction' => $totalDeduction,
                'net_pay' => $netPay,
            ],
        ];
    }

    protected function resolveItem(string $code, string $category, string $label, float $calculatedAmount, string $defaultSource, int $sortOrder, $existingItems = null): array
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
        ];
    }
}
