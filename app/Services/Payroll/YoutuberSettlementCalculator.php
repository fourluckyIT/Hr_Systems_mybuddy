<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollItem;

class YoutuberSettlementCalculator
{
    public function calculate(Employee $employee, int $month, int $year): array
    {
        // YouTuber settlement: total_income - total_expense = net
        // Uses entry_type field to classify income vs deduction
        $workLogs = \App\Models\WorkLog::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $items = [];
        $totalIncome = 0;
        $totalDeduction = 0;

        foreach ($workLogs as $log) {
            $category = $log->entry_type === 'deduction' ? 'deduction' : 'income';
            $amount = (float) $log->amount;

            $items[] = [
                'item_type_code' => 'settlement_' . $log->id,
                'category' => $category,
                'label' => $log->work_type ?: 'รายการคงค้าง',
                'amount' => $amount,
                'source_flag' => 'auto',
                'sort_order' => $log->sort_order,
            ];

            if ($category === 'income') {
                $totalIncome += $amount;
            } else {
                $totalDeduction += $amount;
            }
        }

        return [
            'items' => $items,
            'summary' => [
                'total_income' => round($totalIncome, 2),
                'total_deduction' => round($totalDeduction, 2),
                'net_pay' => round($totalIncome - $totalDeduction, 2),
            ],
        ];
    }
}
