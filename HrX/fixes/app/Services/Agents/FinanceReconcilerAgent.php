<?php

namespace App\Services\Agents;

use App\Models\CompanyExpense;
use App\Models\CompanyRevenue;
use App\Models\Payslip;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Fixes BUG-06 (rounding policy + tolerance = max(1%, ฿10)) and
 * BUG-11 (firstOrCreate under a unique index eliminates the TOCTOU race
 * between the Payslip.finalized trigger and the 1st-of-month cron).
 */
class FinanceReconcilerAgent
{
    private const TOLERANCE_ABSOLUTE_THB = 10.0;
    private const TOLERANCE_RELATIVE     = 0.01;

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationDispatchAgent $notifier,
    ) {}

    public function reconcile(int $year, int $month): array
    {
        return DB::transaction(function () use ($year, $month) {
            $calculated = $this->calculateExpected($year, $month);

            // BUG-11 fix: firstOrCreate atomically under uq_expense_month_cat.
            $expense = CompanyExpense::firstOrCreate(
                [
                    'year'        => $year,
                    'month'       => $month,
                    'category'    => 'payroll',
                    'source_flag' => 'agent_draft',
                ],
                [
                    'amount' => $calculated,
                    'note'   => "Auto-reconciled from finalized payslips for {$year}-{$month}",
                ],
            );

            $recorded  = (float) $expense->amount;
            $tolerance = max(self::TOLERANCE_ABSOLUTE_THB, $recorded * self::TOLERANCE_RELATIVE);
            $delta     = $calculated - $recorded;

            $flagged = abs($delta) > $tolerance;

            if ($expense->wasRecentlyCreated) {
                $expense->amount = $this->round($calculated);
                $expense->save();
            }

            $revenueExists = CompanyRevenue::query()
                ->where('year', $year)->where('month', $month)->exists();

            $report = [
                'year'                => $year,
                'month'               => $month,
                'calculated_expense'  => $this->round($calculated),
                'recorded_expense'    => $this->round($recorded),
                'delta'               => $this->round($delta),
                'tolerance'           => $this->round($tolerance),
                'flagged'             => $flagged,
                'missing_revenue'     => !$revenueExists,
                'expense_id'          => $expense->id,
                'created_new_draft'   => $expense->wasRecentlyCreated,
            ];

            $this->audit->record(
                action: 'finance_reconcile',
                subjectType: CompanyExpense::class,
                subjectId: $expense->id,
                meta: $report,
            );

            if ($flagged || !$revenueExists) {
                $this->notifier->dispatch(
                    eventKey: 'finance.reconciliation_ready',
                    entityId: $expense->id,
                    payload: $report,
                );
            }

            return $report;
        });
    }

    /** Sum every finalized payslip for the month. */
    private function calculateExpected(int $year, int $month): float
    {
        return (float) Payslip::query()
            ->where('status', 'finalized')
            ->whereHas('payrollBatch', fn ($q) => $q->where('year', $year)->where('month', $month))
            ->sum('net_pay');
    }

    /** BUG-06 fix: banker's rounding, consistent project-wide. */
    private function round(float $v): float
    {
        return round($v, 2, PHP_ROUND_HALF_EVEN);
    }
}
