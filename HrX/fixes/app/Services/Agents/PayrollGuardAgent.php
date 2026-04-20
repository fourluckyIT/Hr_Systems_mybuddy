<?php

namespace App\Services\Agents;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollItem;
use App\Models\Payslip;
use App\Services\AuditLogService;
use App\Services\SocialSecurityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Fixes BUG-01 (Thai OT cap 36h/week), BUG-03 (use source_ref_id dedupe),
 * BUG-07 (net_pay >= 0 is the only hard rule; zero is WARN, not BLOCK),
 * BUG-14 (fires payroll.guard_blocked event on BLOCK).
 *
 * Pure validator — no writes except AuditLog.
 */
class PayrollGuardAgent
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly SocialSecurityService $sso,
        private readonly NotificationDispatchAgent $notifier,
    ) {}

    public function run(Payslip $payslip): GuardResult
    {
        $batch    = $payslip->payrollBatch;
        $employee = $payslip->employee;
        $month    = Carbon::create($batch->year, $batch->month, 1);

        $result = new GuardResult();

        $this->checkAttendanceCoverage($employee, $month, $result);
        $this->checkNetPay($payslip, $result);
        $this->checkOtCapPerWeek($employee, $month, $result);
        $this->checkSsoCeiling($payslip, $result);
        $this->flagManualOverrides($payslip, $result);
        $this->checkFreelanceWorkLogs($payslip, $result);
        $this->checkDuplicateItems($payslip, $result);

        $this->audit->record(
            action: 'payroll_guard_check',
            subjectType: Payslip::class,
            subjectId: $payslip->id,
            meta: $result->summary(),
        );

        if ($result->isBlock()) {
            $this->notifier->dispatch(
                eventKey: 'payroll.guard_blocked',
                priority: 'High',
                entityId: $payslip->id,
                payload: $result->summary(),
            );
        }

        return $result;
    }

    /** Check #1 — every working day has an attendance record. */
    private function checkAttendanceCoverage(Employee $e, Carbon $month, GuardResult $r): void
    {
        $workingDays = app(\App\Services\WorkCalendarService::class)
            ->workingDays($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $e);

        $recorded = AttendanceLog::where('employee_id', $e->id)
            ->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->pluck('work_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique();

        $gaps = $workingDays->reject(fn ($d) => $recorded->contains($d->toDateString()));

        if ($gaps->isNotEmpty()) {
            $r->block('attendance_gap', [
                'missing_dates' => $gaps->map->toDateString()->values()->all(),
            ]);
        }
    }

    /** Check #2 (fixed from BUG-07) — only BLOCK on negative; zero is WARN. */
    private function checkNetPay(Payslip $p, GuardResult $r): void
    {
        $net = (float) $p->net_pay;

        if ($net < 0) {
            $r->block('negative_net_pay', ['net_pay' => $net]);
        } elseif ($net == 0.0) {
            $r->warn('zero_net_pay', [
                'reason' => 'all deductions consumed salary — verify advance/loan balances',
            ]);
        }
    }

    /**
     * Check #3 (fixed from BUG-01) — Thai Labour Protection Act §26:
     * OT + holiday work combined ≤ 36h / week. Replaces the legally-wrong
     * "40h / month" rule in the original spec.
     */
    private function checkOtCapPerWeek(Employee $e, Carbon $month, GuardResult $r): void
    {
        $weeks = AttendanceLog::where('employee_id', $e->id)
            ->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->selectRaw('YEARWEEK(work_date, 3) AS yw, SUM(COALESCE(ot_minutes,0)) AS ot_min')
            ->groupBy('yw')
            ->get();

        foreach ($weeks as $w) {
            $hours = round($w->ot_min / 60, 1);
            if ($hours > 36.0) {
                $r->block('ot_weekly_cap_exceeded', [
                    'year_week' => $w->yw,
                    'ot_hours'  => $hours,
                    'cap_hours' => 36.0,
                ]);
            }
        }
    }

    /** Check #4 — SSO deduction bounded by current ceiling. */
    private function checkSsoCeiling(Payslip $p, GuardResult $r): void
    {
        $ssoItem = $p->items()->where('category', 'sso')->first();
        if (!$ssoItem) return;

        $ceiling = $this->sso->maxEmployeeContribution(for: $p->payrollBatch->period());
        if ((float)$ssoItem->amount > $ceiling) {
            $r->block('sso_above_ceiling', [
                'amount'  => (float)$ssoItem->amount,
                'ceiling' => $ceiling,
            ]);
        }
    }

    /** Check #5 — manual / override items flagged for human review (WARN, never BLOCK). */
    private function flagManualOverrides(Payslip $p, GuardResult $r): void
    {
        $manual = $p->items()->whereIn('source_flag', ['manual', 'override'])->get();
        if ($manual->isNotEmpty()) {
            $r->warn('manual_items_present', [
                'count' => $manual->count(),
                'ids'   => $manual->pluck('id')->all(),
            ]);
        }
    }

    /** Check #6 — freelance lines must have non-zero amounts. */
    private function checkFreelanceWorkLogs(Payslip $p, GuardResult $r): void
    {
        $zero = $p->items()
            ->where('source_ref_type', 'work_log')
            ->where('amount', '<=', 0)
            ->pluck('id');

        if ($zero->isNotEmpty()) {
            $r->block('zero_freelance_amount', ['item_ids' => $zero->all()]);
        }
    }

    /**
     * Check #7 (fixed from BUG-03) — dedupe by source_ref_id, not label+amount.
     * A duplicate now means: two PayrollItem rows pointing at the exact same
     * upstream record (same source_ref_type + source_ref_id).
     */
    private function checkDuplicateItems(Payslip $p, GuardResult $r): void
    {
        $dupes = PayrollItem::query()
            ->where('payroll_batch_id', $p->payroll_batch_id)
            ->where('employee_id', $p->employee_id)
            ->whereNotNull('source_ref_id')
            ->selectRaw('source_ref_type, source_ref_id, COUNT(*) AS c')
            ->groupBy('source_ref_type', 'source_ref_id')
            ->havingRaw('c > 1')
            ->get();

        if ($dupes->isNotEmpty()) {
            $r->block('duplicate_source_refs', [
                'duplicates' => $dupes->map->toArray()->all(),
            ]);
        }
    }
}
