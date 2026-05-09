<?php

namespace App\Http\Controllers;

use App\Models\BonusCalculation;
use App\Models\BonusCycle;
use App\Models\BonusCycleSelectedMonth;
use App\Models\Employee;
use App\Models\PerformanceTier;
use App\Services\BonusCalculationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BonusManagementController extends Controller
{
    public function __construct(
        protected BonusCalculationService $bonusService
    ) {}

    public function index(Request $request)
    {
        $cycles = BonusCycle::query()
            ->orderByDesc('cycle_year')
            ->orderByRaw("CASE WHEN cycle_period = 'december' THEN 0 WHEN cycle_period = 'june' THEN 1 ELSE 2 END")
            ->get();

        $selectedCycle = null;
        if ($request->filled('cycle_id')) {
            $selectedCycle = BonusCycle::find($request->integer('cycle_id'));
        }
        if (!$selectedCycle) {
            $selectedCycle = $cycles->first();
        }

        $tiers = PerformanceTier::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $employees = Employee::query()
            ->with(['salaryProfile'])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $calculations = collect();
        $candidateMonths = [];
        $hasSelectedMonths = false;
        $cycleSummary = [
            'total_employees' => 0,
            'total_payment' => 0,
            'approved_count' => 0,
            'pending_count' => 0,
            'avg_payment' => 0,
        ];

        if ($selectedCycle) {
            $calculations = BonusCalculation::query()
                ->with(['employee.salaryProfile', 'tier'])
                ->where('cycle_id', $selectedCycle->id)
                ->orderByDesc('actual_payment')
                ->get();

            $candidateMonths = $this->bonusService->getCandidateMonthsRich($selectedCycle->id);
            $hasSelectedMonths = BonusCycleSelectedMonth::where('cycle_id', $selectedCycle->id)->exists();

            $totalPayment = round($calculations->sum(fn ($c) => (float) $c->actual_payment), 2);
            $count = $calculations->count();
            $cycleSummary = [
                'total_employees' => $count,
                'total_payment' => $totalPayment,
                'approved_count' => $calculations->where('status', 'approved')->count(),
                'pending_count' => $calculations->where('status', 'calculated')->count(),
                'avg_payment' => $count > 0 ? round($totalPayment / $count, 2) : 0,
            ];
        }

        return view('settings.bonus', compact(
            'cycles',
            'selectedCycle',
            'tiers',
            'employees',
            'calculations',
            'candidateMonths',
            'cycleSummary',
            'hasSelectedMonths'
        ));
    }

    public function storeCycle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cycle_code' => 'required|string|max:20|unique:bonus_cycles,cycle_code',
            'cycle_year' => 'required|integer|min:2020|max:2100',
            'cycle_period' => 'required|in:june,december',
            'payment_date' => 'required|date',
            'max_allocation' => 'required|numeric|min:0|max:1',
        ]);

        $validated += [
            'status' => 'draft',
            'june_max_ratio' => 0.400,
            'june_scale_months' => 6,
            'full_scale_months' => 12,
            'absent_penalty_per_day' => -0.0100,
            'late_penalty_per_occurrence' => -0.0020,
            'leave_free_days' => 5,
            'leave_penalty_rate' => 0.0100,
        ];

        $cycle = BonusCycle::create($validated);

        return redirect()
            ->route('settings.bonus.index', ['cycle_id' => $cycle->id])
            ->with('success', 'สร้างรอบโบนัสใหม่สำเร็จ');
    }

    public function updateCycle(Request $request, BonusCycle $cycle): RedirectResponse
    {
        // Status ไม่ให้ผู้ใช้แก้ตรง ๆ — ใช้ปุ่ม transition แทน
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'max_allocation' => 'required|numeric|min:0|max:1',
            'june_max_ratio' => 'required|numeric|min:0|max:1',
            'june_scale_months' => 'required|integer|min:1|max:24',
            'full_scale_months' => 'required|integer|min:1|max:36',
            'absent_penalty_per_day' => 'required|numeric|min:-1|max:0',
            'late_penalty_per_occurrence' => 'required|numeric|min:-1|max:0',
            'leave_free_days' => 'required|integer|min:0|max:30',
            'leave_penalty_rate' => 'required|numeric|min:0|max:1',
        ]);

        $cycle->update($validated);

        return back()->with('success', 'อัปเดตเงื่อนไขรอบโบนัสสำเร็จ');
    }

    /**
     * State machine transitions: mark cycle as paid / reopen / reject.
     */
    public function transitionCycle(Request $request, BonusCycle $cycle): RedirectResponse
    {
        $action = $request->input('transition_action');

        $allowed = [
            'mark_paid' => ['from' => ['approved'], 'to' => 'paid'],
            'close' => ['from' => ['paid'], 'to' => 'closed'],
            'reopen' => ['from' => ['rejected', 'closed'], 'to' => 'draft'],
        ];

        if (!isset($allowed[$action])) {
            return back()->withErrors(['action' => 'ไม่รู้จักคำสั่งนี้']);
        }

        $rule = $allowed[$action];
        if (!in_array($cycle->status, $rule['from'], true)) {
            return back()->withErrors(['action' => "ไม่สามารถ {$action} ได้ในสถานะ {$cycle->status}"]);
        }

        // mark_paid: post bonus to payslip BEFORE flipping status (so failure aborts cleanly)
        $payslipResult = null;
        if ($rule['to'] === 'paid') {
            try {
                $payslipResult = $this->bonusService->postBonusToPayslip($cycle->id, auth()->user()?->name);
            } catch (DomainException $e) {
                return back()->withErrors(['action' => $e->getMessage()]);
            }
        }

        $cycle->update(['status' => $rule['to']]);

        // Cascade status to calculations
        if ($rule['to'] === 'paid') {
            BonusCalculation::where('cycle_id', $cycle->id)
                ->where('status', 'approved')
                ->update(['status' => 'paid']);
        } elseif ($rule['to'] === 'draft') {
            BonusCalculation::where('cycle_id', $cycle->id)
                ->where('status', 'rejected')
                ->update(['status' => 'calculated']);
        }

        $msg = 'อัปเดตสถานะรอบโบนัสเรียบร้อย';
        if ($payslipResult) {
            $msg = "✅ ทำเครื่องหมายจ่ายแล้ว — สร้างโบนัส {$payslipResult['created_count']} รายการ เข้า payslip {$payslipResult['target_label']} (รวม ฿" . number_format($payslipResult['total_amount'], 0) . ')';
        }

        return back()->with('success', $msg);
    }

    /**
     * Preview which calculations can be posted to payslip.
     */
    public function previewPayslipPost(BonusCycle $cycle): JsonResponse
    {
        return response()->json($this->bonusService->previewBonusToPayslip($cycle->id));
    }

    public function destroyCycle(BonusCycle $cycle): RedirectResponse
    {
        if (in_array($cycle->status, ['approved', 'paid', 'closed'], true)) {
            return back()->withErrors(['delete' => 'ไม่สามารถลบรอบโบนัสที่ผ่านการอนุมัติหรือจ่ายแล้วได้']);
        }

        $cycle->delete();

        return redirect()
            ->route('settings.bonus.index')
            ->with('success', 'ลบรอบโบนัสเรียบร้อยแล้ว');
    }

    public function updateSelectedMonths(Request $request, BonusCycle $cycle): RedirectResponse
    {
        $validated = $request->validate([
            'months' => 'required|array|min:1',
            'months.*' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        try {
            $this->bonusService->setCycleSelectedMonths(
                $cycle->id,
                $validated['months'],
                auth()->user()?->name
            );
        } catch (DomainException $e) {
            return back()->withErrors(['months' => $e->getMessage()]);
        }

        return back()->with('success', 'อัปเดตเดือนที่ใช้คำนวณโบนัสสำเร็จ');
    }

    public function calculate(Request $request): RedirectResponse
    {
        $validated = $this->validateCalculatePayload($request);

        try {
            $this->bonusService->calculateAndStore(
                (int) $validated['employee_id'],
                (int) $validated['cycle_id'],
                (float) $validated['base_reference'],
                $validated['tier_code'] ?? null,
                0.0,
                isset($validated['absent_days']) ? (int) $validated['absent_days'] : null,
                isset($validated['late_count']) ? (int) $validated['late_count'] : null,
                isset($validated['leave_days']) ? (int) $validated['leave_days'] : null,
                isset($validated['clip_duration_minutes_per_month']) ? (int) $validated['clip_duration_minutes_per_month'] : null,
                isset($validated['qualified_months']) ? (int) $validated['qualified_months'] : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['bonus' => $e->getMessage()]);
        }

        return redirect()
            ->route('settings.bonus.index', ['cycle_id' => $validated['cycle_id']])
            ->with('success', 'คำนวณโบนัสรายบุคคลสำเร็จ');
    }

    /**
     * Preview a calculation without persisting — used for live preview before save.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $this->validateCalculatePayload($request);

        try {
            $result = $this->bonusService->calculate(
                (int) $validated['employee_id'],
                (int) $validated['cycle_id'],
                (float) $validated['base_reference'],
                $validated['tier_code'] ?? null,
                0.0,
                isset($validated['absent_days']) ? (int) $validated['absent_days'] : null,
                isset($validated['late_count']) ? (int) $validated['late_count'] : null,
                isset($validated['leave_days']) ? (int) $validated['leave_days'] : null,
                isset($validated['clip_duration_minutes_per_month']) ? (int) $validated['clip_duration_minutes_per_month'] : null,
                isset($validated['qualified_months']) ? (int) $validated['qualified_months'] : null,
            );
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $cycle = BonusCycle::findOrFail((int) $validated['cycle_id']);
        $warnings = $this->bonusService->validateResult($result, $cycle);

        return response()->json([
            'result' => $result,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Auto-fill metrics for a single employee from real attendance + worklog data.
     */
    public function metricsForEmployee(BonusCycle $cycle, Employee $employee): JsonResponse
    {
        $metrics = $this->bonusService->buildEmployeeMetrics($cycle->id, $employee->id);

        return response()->json($metrics);
    }

    public function batchCalculate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cycle_id' => 'required|exists:bonus_cycles,id',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:employees,id',
            'tier_code' => 'nullable|string|exists:performance_tiers,tier_code',
            'auto_fill' => 'sometimes|boolean',
            'clip_duration_minutes_per_month' => 'nullable|integer|min:0',
            'qualified_months' => 'nullable|integer|min:0',
        ]);

        $autoFill = (bool) ($validated['auto_fill'] ?? true);

        $employeesPayload = Employee::query()
            ->with('salaryProfile')
            ->whereIn('id', $validated['employee_ids'])
            ->get()
            ->map(function (Employee $employee) use ($validated, $autoFill) {
                $payload = [
                    'employee_id' => $employee->id,
                    'base_reference' => (float) ($employee->salaryProfile?->base_salary ?? 0),
                    'tier_id' => $validated['tier_code'] ?? null,
                    'attendance_adjustment' => 0,
                ];

                if ($autoFill) {
                    $metrics = $this->bonusService->buildEmployeeMetrics(
                        (int) $validated['cycle_id'],
                        $employee->id,
                    );
                    $payload['absent_days'] = (int) $metrics['absent_days'];
                    $payload['late_count'] = (int) $metrics['late_count'];
                    $payload['leave_days'] = (int) $metrics['leave_days'];
                    $payload['clip_duration_minutes_per_month'] = (int) $metrics['clip_duration_minutes_per_month'];
                    $payload['qualified_months'] = (int) $metrics['qualified_months'];
                } else {
                    $payload['clip_duration_minutes_per_month'] = isset($validated['clip_duration_minutes_per_month'])
                        ? (int) $validated['clip_duration_minutes_per_month'] : null;
                    $payload['qualified_months'] = isset($validated['qualified_months'])
                        ? (int) $validated['qualified_months'] : null;
                }

                return $payload;
            })
            ->values()
            ->all();

        try {
            $this->bonusService->batchCalculate((int) $validated['cycle_id'], $employeesPayload);
        } catch (DomainException $e) {
            return back()->withErrors(['bonus' => $e->getMessage()]);
        }

        return redirect()
            ->route('settings.bonus.index', ['cycle_id' => $validated['cycle_id']])
            ->with('success', 'คำนวณโบนัสแบบกลุ่มสำเร็จ');
    }

    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cycle_id' => 'required|exists:bonus_cycles,id',
            'calculation_ids' => 'required|array|min:1',
            'calculation_ids.*' => 'required|exists:bonus_calculations,id',
        ]);

        $this->bonusService->approve(
            (int) $validated['cycle_id'],
            $validated['calculation_ids'],
            auth()->user()?->name ?? 'system'
        );

        // Update cycle status if all calculations now approved
        $cycle = BonusCycle::find($validated['cycle_id']);
        if ($cycle && $cycle->status === 'calculated') {
            $hasUnapproved = BonusCalculation::where('cycle_id', $cycle->id)
                ->where('status', '!=', 'approved')
                ->exists();
            if (!$hasUnapproved) {
                $cycle->update(['status' => 'approved']);
            }
        }

        return redirect()
            ->route('settings.bonus.index', ['cycle_id' => $validated['cycle_id']])
            ->with('success', 'อนุมัติรายการโบนัสสำเร็จ');
    }

    /**
     * Recalculate a single existing row using auto-filled metrics from real data.
     */
    public function recalculateRow(BonusCalculation $calculation): RedirectResponse
    {
        $cycleId = (int) $calculation->cycle_id;

        if (in_array($calculation->status, ['approved', 'paid'], true)) {
            return back()->withErrors(['recalc' => 'ไม่สามารถคำนวณซ้ำรายการที่อนุมัติ/จ่ายแล้ว']);
        }

        $metrics = $this->bonusService->buildEmployeeMetrics($cycleId, (int) $calculation->employee_id);

        try {
            $this->bonusService->calculateAndStore(
                (int) $calculation->employee_id,
                $cycleId,
                (float) $metrics['base_reference'],
                null,
                0.0,
                (int) $metrics['absent_days'],
                (int) $metrics['late_count'],
                (int) $metrics['leave_days'],
                (int) $metrics['clip_duration_minutes_per_month'],
                (int) $metrics['qualified_months'],
            );
        } catch (DomainException $e) {
            return back()->withErrors(['recalc' => $e->getMessage()]);
        }

        return back()->with('success', 'คำนวณซ้ำสำเร็จ');
    }

    public function destroyRow(BonusCalculation $calculation): RedirectResponse
    {
        if (in_array($calculation->status, ['approved', 'paid'], true)) {
            return back()->withErrors(['delete' => 'ไม่สามารถลบรายการที่อนุมัติ/จ่ายแล้ว']);
        }

        $calculation->delete();

        return back()->with('success', 'ลบรายการคำนวณเรียบร้อย');
    }

    private function validateCalculatePayload(Request $request): array
    {
        return $request->validate([
            'cycle_id' => 'required|exists:bonus_cycles,id',
            'employee_id' => 'required|exists:employees,id',
            'base_reference' => 'required|numeric|min:0',
            'tier_code' => 'nullable|string|exists:performance_tiers,tier_code',
            'absent_days' => 'nullable|integer|min:0',
            'late_count' => 'nullable|integer|min:0',
            'leave_days' => 'nullable|integer|min:0',
            'clip_duration_minutes_per_month' => 'nullable|integer|min:0',
            'qualified_months' => 'nullable|integer|min:0',
        ]);
    }
}
