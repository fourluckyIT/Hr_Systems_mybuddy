<?php

namespace App\Http\Controllers;

use App\Models\BonusCalculation;
use App\Models\BonusCycle;
use App\Models\Employee;
use App\Models\PerformanceTier;
use App\Services\BonusCalculationService;
use DomainException;
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
        $selectedMonths = [];
        $cycleSummary = [
            'total_employees' => 0,
            'total_payment' => 0,
            'approved_count' => 0,
        ];

        if ($selectedCycle) {
            $calculations = BonusCalculation::query()
                ->with(['employee', 'tier'])
                ->where('cycle_id', $selectedCycle->id)
                ->orderByDesc('actual_payment')
                ->get();

            $selectedMonths = $this->bonusService->getCycleSelectedMonths($selectedCycle->id);

            $cycleSummary = [
                'total_employees' => $calculations->count(),
                'total_payment' => round($calculations->sum(fn ($c) => (float) $c->actual_payment), 2),
                'approved_count' => $calculations->where('status', 'approved')->count(),
            ];
        }

        return view('settings.bonus', compact(
            'cycles',
            'selectedCycle',
            'tiers',
            'employees',
            'calculations',
            'selectedMonths',
            'cycleSummary'
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
            ->with('success', 'สร้างโบนัสรอบใหม่สำเร็จ');
    }

    public function updateCycle(Request $request, BonusCycle $cycle): RedirectResponse
    {
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
            'status' => 'required|in:draft,calculating,calculated,reviewed,approved,paid,closed,rejected',
        ]);

        $cycle->update($validated);

        return back()->with('success', 'อัปเดตเงื่อนไขรอบโบนัสสำเร็จ');
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
        $validated = $request->validate([
            'cycle_id' => 'required|exists:bonus_cycles,id',
            'employee_id' => 'required|exists:employees,id',
            'base_reference' => 'required|numeric|min:0',
            'tier_code' => 'required|string|exists:performance_tiers,tier_code',
            'absent_days' => 'nullable|integer|min:0',
            'late_count' => 'nullable|integer|min:0',
            'leave_days' => 'nullable|integer|min:0',
        ]);

        try {
            $this->bonusService->calculateAndStore(
                (int) $validated['employee_id'],
                (int) $validated['cycle_id'],
                (float) $validated['base_reference'],
                $validated['tier_code'],
                0.0,
                isset($validated['absent_days']) ? (int) $validated['absent_days'] : null,
                isset($validated['late_count']) ? (int) $validated['late_count'] : null,
                isset($validated['leave_days']) ? (int) $validated['leave_days'] : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['bonus' => $e->getMessage()]);
        }

        return redirect()
            ->route('settings.bonus.index', ['cycle_id' => $validated['cycle_id']])
            ->with('success', 'คำนวณโบนัสรายบุคคลสำเร็จ');
    }

    public function batchCalculate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cycle_id' => 'required|exists:bonus_cycles,id',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:employees,id',
            'tier_code' => 'required|string|exists:performance_tiers,tier_code',
        ]);

        $employeesPayload = Employee::query()
            ->with('salaryProfile')
            ->whereIn('id', $validated['employee_ids'])
            ->get()
            ->map(function (Employee $employee) use ($validated) {
                return [
                    'employee_id' => $employee->id,
                    'base_reference' => (float) ($employee->salaryProfile?->base_salary ?? 0),
                    'tier_id' => $validated['tier_code'],
                    'attendance_adjustment' => 0,
                ];
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

        return redirect()
            ->route('settings.bonus.index', ['cycle_id' => $validated['cycle_id']])
            ->with('success', 'อนุมัติรายการโบนัสสำเร็จ');
    }
}
