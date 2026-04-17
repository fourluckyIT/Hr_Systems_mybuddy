<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollItem;
use App\Models\Payslip;
use App\Models\PayslipItem;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    public function __construct(
        protected MonthlyStaffCalculator $monthlyStaffCalc,
        protected FreelanceLayerCalculator $freelanceLayerCalc,
        protected FreelanceFixedCalculator $freelanceFixedCalc,
        protected YoutuberSalaryCalculator $youtuberSalaryCalc,
        protected YoutuberSettlementCalculator $youtuberSettlementCalc,
    ) {}

    public function calculateForEmployee(Employee $employee, int $month, int $year): array
    {
        $calculator = match ($employee->payroll_mode) {
            'monthly_staff', 'office_staff' => $this->monthlyStaffCalc,
            'freelance_layer' => $this->freelanceLayerCalc,
            'freelance_fixed' => $this->freelanceFixedCalc,
            'youtuber_salary' => $this->youtuberSalaryCalc,
            'youtuber_settlement' => $this->youtuberSettlementCalc,
            default => $this->monthlyStaffCalc,
        };

        $result = $calculator->calculate($employee, $month, $year);

        // Fetch approved claims/advances for this month
        $approvedClaims = \App\Models\ExpenseClaim::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->where('status', 'approved')
            ->get();

        foreach ($approvedClaims as $claim) {
            $category = $claim->type === 'advance' ? 'deduction' : 'income';
            $label = ($claim->type === 'advance' ? 'เบิกเงินล่วงหน้า: ' : 'เบิกค่าใช้จ่าย: ') . $claim->description;
            
            $result['items'][] = [
                'item_type_code' => 'claim_' . $claim->id,
                'category' => $category,
                'label' => $label,
                'amount' => (float) $claim->amount,
                'source_flag' => 'auto',
                'sort_order' => 99, // Place at the end
            ];

            if ($category === 'income') {
                $result['summary']['total_income'] += (float) $claim->amount;
            } else {
                $result['summary']['total_deduction'] += (float) $claim->amount;
            }
        }

        $result['summary']['net_pay'] = round($result['summary']['total_income'] - $result['summary']['total_deduction'], 2);

        return $result;
    }

    /**
     * Sync WorkLog amounts/rates to DB based on current rate rules.
     * Only applies to freelance modes that use WorkLog-based pricing.
     * Call after calculate() + savePayrollItems() to keep the grid display accurate.
     */
    public function syncWorkLogAmounts(Employee $employee, int $month, int $year): void
    {
        match ($employee->payroll_mode) {
            'freelance_layer' => $this->freelanceLayerCalc->syncWorkLogAmounts($employee, $month, $year),
            'freelance_fixed' => $this->freelanceFixedCalc->syncWorkLogAmounts($employee, $month, $year),
            default          => null,
        };
    }

    public function savePayrollItems(Employee $employee, int $month, int $year, array $result): PayrollBatch
    {
        return DB::transaction(function () use ($employee, $month, $year, $result) {
            // Get or create batch
            $batch = PayrollBatch::firstOrCreate(
                ['month' => $month, 'year' => $year],
                ['status' => 'draft']
            );

            // Delete existing auto-generated items (keep manual ones)
            PayrollItem::where('employee_id', $employee->id)
                ->where('payroll_batch_id', $batch->id)
                ->where('source_flag', '!=', 'manual')
                ->where('source_flag', '!=', 'override')
                ->delete();

            // Insert new items
            foreach ($result['items'] as $item) {
                // Check if manual/override exists for this type
                $existing = PayrollItem::where('employee_id', $employee->id)
                    ->where('payroll_batch_id', $batch->id)
                    ->where('item_type_code', $item['item_type_code'])
                    ->whereIn('source_flag', ['manual', 'override'])
                    ->first();

                if ($existing) {
                    continue; // Keep manual override
                }

                PayrollItem::create([
                    'employee_id' => $employee->id,
                    'payroll_batch_id' => $batch->id,
                    'item_type_code' => $item['item_type_code'],
                    'category' => $item['category'],
                    'label' => $item['label'],
                    'amount' => $item['amount'],
                    'source_flag' => $item['source_flag'],
                    'sort_order' => $item['sort_order'],
                ]);
            }

            return $batch;
        });
    }

    public function finalizePayslip(Employee $employee, int $month, int $year): Payslip
    {
        return DB::transaction(function () use ($employee, $month, $year) {
            $batch = PayrollBatch::where('month', $month)->where('year', $year)->first();

            $items = PayrollItem::where('employee_id', $employee->id)
                ->where('payroll_batch_id', $batch?->id)
                ->get();

            $totalIncome = $items->where('category', 'income')->sum('amount');
            $totalDeduction = $items->where('category', 'deduction')->sum('amount');
            $netPay = round($totalIncome - $totalDeduction, 2);

            // Create or update payslip
            $payslip = Payslip::updateOrCreate(
                ['employee_id' => $employee->id, 'month' => $month, 'year' => $year],
                [
                    'payroll_batch_id' => $batch?->id,
                    'total_income' => $totalIncome,
                    'total_deduction' => $totalDeduction,
                    'net_pay' => $netPay,
                    'status' => 'finalized',
                    'finalized_at' => now(),
                    'finalized_by' => auth()->id(),
                ]
            );

            // Snapshot items
            $payslip->items()->delete();
            foreach ($items as $item) {
                PayslipItem::create([
                    'payslip_id' => $payslip->id,
                    'category' => $item->category,
                    'label' => $item->label,
                    'amount' => $item->amount,
                    'sort_order' => $item->sort_order,
                ]);
            }

            return $payslip;
        });
    }
}
