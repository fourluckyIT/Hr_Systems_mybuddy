<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollItem;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Fixes BUG-02 (manual/override items must survive recalculation).
 *
 * Contract:
 *   - `recalculate()` only ever deletes rows with source_flag='auto'.
 *   - manual and override rows are preserved verbatim.
 *   - A dedicated `resetToAuto()` method exists for the rare case an admin
 *     wants to drop everything and start fresh (requires explicit UI confirm).
 *
 * Existing per-mode calculators (monthly_staff, freelance_layer, freelance_fixed,
 * youtuber_salary, youtuber_settlement) are called from buildAutoItems(). Keep
 * those classes wherever they already live and adapt the two calls marked *here*.
 */
class PayrollCalculationService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function recalculate(PayrollBatch $batch, Employee $employee): array
    {
        return DB::transaction(function () use ($batch, $employee) {
            // Lock this batch+employee slice so a parallel recalc can't duplicate rows.
            PayrollItem::query()
                ->where('payroll_batch_id', $batch->id)
                ->where('employee_id', $employee->id)
                ->lockForUpdate()
                ->get();

            // BUG-02 fix: scope DELETE to auto rows only.
            $deletedAutoCount = PayrollItem::query()
                ->where('payroll_batch_id', $batch->id)
                ->where('employee_id', $employee->id)
                ->where('source_flag', 'auto')
                ->delete();

            $autoRows = $this->buildAutoItems($batch, $employee);   // *here*
            foreach ($autoRows as $row) {
                PayrollItem::create($row + [
                    'payroll_batch_id' => $batch->id,
                    'employee_id'      => $employee->id,
                    'source_flag'      => 'auto',
                ]);
            }

            $this->audit->record(
                action: 'payroll_recalculate',
                subjectType: PayrollBatch::class,
                subjectId: $batch->id,
                meta: [
                    'employee_id'         => $employee->id,
                    'deleted_auto_count'  => $deletedAutoCount,
                    'recreated_count'     => count($autoRows),
                    'preserved_manual'    => PayrollItem::where('payroll_batch_id', $batch->id)
                        ->where('employee_id', $employee->id)
                        ->whereIn('source_flag', ['manual', 'override'])
                        ->count(),
                ],
            );

            return [
                'deleted_auto'  => $deletedAutoCount,
                'created_auto'  => count($autoRows),
            ];
        });
    }

    /**
     * Nuclear option — drops EVERY payroll item for this batch+employee.
     * UI must require an explicit double-confirm; this is the only place
     * manual/override rows are allowed to be destroyed.
     */
    public function resetToAuto(PayrollBatch $batch, Employee $employee): void
    {
        DB::transaction(function () use ($batch, $employee) {
            PayrollItem::query()
                ->where('payroll_batch_id', $batch->id)
                ->where('employee_id', $employee->id)
                ->delete();

            $this->audit->record(
                action: 'payroll_reset_to_auto',
                subjectType: PayrollBatch::class,
                subjectId: $batch->id,
                meta: ['employee_id' => $employee->id, 'warning' => 'destroyed_manual_and_override_rows'],
            );

            $this->recalculate($batch, $employee);
        });
    }

    /**
     * Dispatch to the right calculator based on Employee.payroll_mode.
     * Return an array of arrays shaped: [
     *   'label', 'category', 'amount', 'source_ref_type', 'source_ref_id'
     * ]
     *
     * (Adapt to your existing calculator classes.)
     */
    private function buildAutoItems(PayrollBatch $batch, Employee $employee): array
    {
        $calculator = match ($employee->payroll_mode) {
            'monthly_staff', 'office_staff' => app(Calculators\MonthlyStaffCalculator::class),
            'freelance_layer'                => app(Calculators\FreelanceLayerCalculator::class),
            'freelance_fixed'                => app(Calculators\FreelanceFixedCalculator::class),
            'youtuber_salary'                => app(Calculators\YoutuberSalaryCalculator::class),
            'youtuber_settlement'            => app(Calculators\YoutuberSettlementCalculator::class),
            default => throw new \DomainException("Unknown payroll_mode: {$employee->payroll_mode}"),
        };

        return $calculator->calculate($batch, $employee);
    }
}
