<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\WorkLog;

class FreelanceFixedCalculator
{
    public function calculate(Employee $employee, int $month, int $year): array
    {
        $workLogs = WorkLog::where('employee_id', $employee->id)
            ->where('is_disabled', false)
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->get();

        $totalIncome = 0;

        foreach ($workLogs as $log) {
            $amount = round($log->quantity * (float) $log->rate, 2);
            $totalIncome += $amount;
        }

        $items = [];
        $items[] = [
            'item_type_code' => 'freelance_income',
            'category'       => 'income',
            'label'          => 'ค่าจ้าง',
            'amount'         => round($totalIncome, 2),
            'source_flag'    => 'auto',
            'sort_order'     => 1,
        ];

        return [
            'items'   => $items,
            'summary' => [
                'total_income'   => round($totalIncome, 2),
                'total_deduction' => 0,
                'net_pay'        => round($totalIncome, 2),
                'work_log_count' => $workLogs->count(),
            ],
        ];
    }

    /**
     * Sync resolved amounts back to each WorkLog row.
     * Call this AFTER calculate(), from the controller's recalculate / saveWorkLogs flows.
     * Kept separate so calculate() stays a pure read operation.
     */
    public function syncWorkLogAmounts(Employee $employee, int $month, int $year): void
    {
        $workLogs = WorkLog::where('employee_id', $employee->id)
            ->where('is_disabled', false)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        foreach ($workLogs as $log) {
            $amount = round($log->quantity * (float) $log->rate, 2);
            if ((float) $log->amount !== $amount) {
                $log->update(['amount' => $amount]);
            }
        }
    }
}
