<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\WorkLog;
use App\Models\LayerRateRule;

class FreelanceLayerCalculator
{
    public function calculate(Employee $employee, int $month, int $year): array
    {
        // Load work logs for the month
        $workLogs = WorkLog::where('employee_id', $employee->id)
            ->where('is_disabled', false)
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->get();

        // Load layer rate rules for this employee
        $layerRules = LayerRateRule::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->orderBy('layer_from')
            ->get();

        $totalIncome = 0;
        $totalMinutes = 0;
        $totalSeconds = 0;

        foreach ($workLogs as $log) {
            // duration_minutes = hours*60 + minutes + (seconds / 60)
            $durationMinutes = ($log->hours * 60) + $log->minutes + ($log->seconds / 60);
            $totalMinutes += $log->minutes + ($log->hours * 60);
            $totalSeconds += $log->seconds;

            $rate = $this->resolveRate($layerRules, $log);
            $amount = round($durationMinutes * $rate, 2);

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
                'total_minutes'  => $totalMinutes,
                'total_seconds'  => $totalSeconds,
                'total_income'   => round($totalIncome, 2),
                'total_deduction' => 0,
                'net_pay'        => round($totalIncome, 2),
                'work_log_count' => $workLogs->count(),
            ],
        ];
    }

    /**
     * Sync resolved amounts and rates back to each WorkLog row.
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

        $layerRules = LayerRateRule::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->orderBy('layer_from')
            ->get();

        foreach ($workLogs as $log) {
            $durationMinutes = ($log->hours * 60) + $log->minutes + ($log->seconds / 60);
            $rate = $this->resolveRate($layerRules, $log);
            $amount = round($durationMinutes * $rate, 2);

            if ((float) $log->amount !== $amount || (float) $log->rate !== $rate) {
                $log->update(['amount' => $amount, 'rate' => $rate]);
            }
        }
    }

    protected function findRateForLayer($layerRules, ?int $layer): float
    {
        if (!$layer) {
            return 0;
        }

        foreach ($layerRules as $rule) {
            if ($layer >= $rule->layer_from && $layer <= $rule->layer_to) {
                return (float) $rule->rate_per_minute;
            }
        }

        return 0;
    }

    protected function resolveRate($layerRules, WorkLog $log): float
    {
        if ($log->pricing_mode === 'custom') {
            return (float) ($log->custom_rate ?? $log->rate ?? 0);
        }

        if ($log->pricing_template_label && (float) $log->rate > 0) {
            return (float) $log->rate;
        }

        $matchedRate = $this->findRateForLayer($layerRules, $log->layer);

        if ($matchedRate > 0) {
            return $matchedRate;
        }

        return (float) ($log->rate ?? 0);
    }
}
