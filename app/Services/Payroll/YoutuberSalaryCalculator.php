<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Services\SocialSecurityService;

class YoutuberSalaryCalculator
{
    public function __construct(
        protected MonthlyStaffCalculator $monthlyStaffCalculator,
        protected SocialSecurityService $ssoService
    ) {}

    public function calculate(Employee $employee, int $month, int $year): array
    {
        // YouTuber salary uses the same engine as monthly staff
        $result = $this->monthlyStaffCalculator->calculate($employee, $month, $year);

        // Fetch work logs for tracking
        $workLogs = \App\Models\WorkLog::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->get();

        foreach ($workLogs as $log) {
            $result['items'][] = [
                'item_type_code' => 'work_log_note',
                'category' => 'info',
                'label' => 'บันทึกงาน: ' . $log->work_type . ($log->notes ? " ({$log->notes})" : ""),
                'amount' => 0,
                'source_flag' => 'auto',
                'sort_order' => 50,
            ];
        }

        return $result;
    }
}
