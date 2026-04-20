<?php

namespace App\Services\Payroll;

use App\Exceptions\AlreadyFinalizedException;
use App\Exceptions\GuardBlockException;
use App\Models\PayrollBatch;
use App\Models\Payslip;
use App\Services\Agents\PayrollGuardAgent;
use App\Services\Agents\FinanceReconcilerAgent;
use Illuminate\Support\Facades\DB;

/**
 * Fixes BUG-08. All finalize logic runs inside a single DB transaction with
 * pessimistic locks on the PayrollBatch and Payslip rows, so two admins
 * clicking "Finalize Slip" simultaneously can no longer double-finalize.
 *
 * The uq_payslip_month unique index (migration 000002) is the DB-level
 * safety net in case anything ever bypasses this action.
 */
class FinalizePayslipAction
{
    public function __construct(
        private readonly PayrollGuardAgent $guard,
        private readonly FinanceReconcilerAgent $reconciler,
    ) {}

    /**
     * @throws AlreadyFinalizedException  when called twice (idempotent no-op outcome).
     * @throws GuardBlockException        when PayrollGuardAgent returns BLOCK.
     */
    public function execute(int $payslipId, int $actorUserId): Payslip
    {
        return DB::transaction(function () use ($payslipId, $actorUserId) {

            $payslip = Payslip::query()
                ->where('id', $payslipId)
                ->lockForUpdate()
                ->firstOrFail();

            PayrollBatch::query()
                ->where('id', $payslip->payroll_batch_id)
                ->lockForUpdate()
                ->first();

            if ($payslip->status === 'finalized') {
                throw new AlreadyFinalizedException("Payslip {$payslip->id} already finalized");
            }

            $result = $this->guard->run($payslip);

            if ($result->isBlock()) {
                // NotificationDispatchAgent already fired payroll.guard_blocked inside the agent.
                throw new GuardBlockException($result->messages(), $result->summary());
            }

            $payslip->status        = 'finalized';
            $payslip->finalized_at  = now();
            $payslip->finalized_by  = $actorUserId;
            $payslip->guard_summary = $result->summary();  // stored JSON, WARN findings visible in UI
            $payslip->save();

            // Kick off reconciliation as a downstream effect. Inside the same
            // transaction is fine because FinanceReconcilerAgent itself uses
            // firstOrCreate which is safe under the unique index.
            $this->reconciler->reconcile(
                year:  $payslip->payrollBatch->year,
                month: $payslip->payrollBatch->month,
            );

            return $payslip;
        });
    }
}
