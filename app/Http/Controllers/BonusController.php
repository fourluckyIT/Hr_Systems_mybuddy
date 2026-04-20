<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveBonusRequest;
use App\Http\Requests\BatchCalculateBonusRequest;
use App\Http\Requests\CalculateBonusRequest;
use App\Http\Requests\SelectBonusMonthsRequest;
use App\Models\BonusCalculation;
use App\Models\BonusCycle;
use App\Services\BonusCalculationService;
use DomainException;
use Illuminate\Http\JsonResponse;

class BonusController extends Controller
{
    public function __construct(
        protected BonusCalculationService $bonusService
    ) {}

    /**
     * POST /api/bonus/calculate
     */
    public function calculate(CalculateBonusRequest $request): JsonResponse
    {
        $data = $request->validated();

        $errors = $this->bonusService->validateInput($data);
        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        try {
            $calc = $this->bonusService->calculateAndStore(
                (int) $data['employee_id'],
                (int) $data['cycle_id'],
                (float) $data['base_reference'],
                $data['tier_id'] ?? null,
                (float) ($data['attendance_adjustment'] ?? 0.0),
                isset($data['absent_days']) ? (int) $data['absent_days'] : null,
                isset($data['late_count']) ? (int) $data['late_count'] : null,
                isset($data['leave_days']) ? (int) $data['leave_days'] : null,
                isset($data['clip_duration_minutes_per_month']) ? (int) $data['clip_duration_minutes_per_month'] : null,
                isset($data['qualified_months']) ? (int) $data['qualified_months'] : null,
            );
        } catch (DomainException $e) {
            return response()->json(['status' => 'error', 'errors' => [$e->getMessage()]], 422);
        }

        $cycle = BonusCycle::find($data['cycle_id']);
        $result = $calc->toArray();
        $warnings = $this->bonusService->validateResult($result, $cycle);

        return response()->json([
            'status'      => 'success',
            'calculation' => [
                'employee_id'           => $calc->employee_id,
                'cycle_id'              => $cycle->cycle_code,
                'base_reference'        => (float) $calc->base_reference,
                'tier_adjusted_bonus'   => (float) $calc->tier_adjusted_bonus,
                'final_bonus_net'       => (float) $calc->final_bonus_net,
                'months_after_probation' => $calc->months_after_probation,
                'unlock_percentage'     => (float) $calc->unlock_percentage,
                'actual_payment'        => (float) $calc->actual_payment,
            ],
            'warnings' => $warnings,
        ]);
    }

    /**
     * POST /api/bonus/batch-calculate
     */
    public function batchCalculate(BatchCalculateBonusRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->bonusService->batchCalculate(
                (int) $data['cycle_id'],
                $data['employees'],
            );
        } catch (DomainException $e) {
            return response()->json(['status' => 'error', 'errors' => [$e->getMessage()]], 422);
        }

        $cycle = BonusCycle::find($data['cycle_id']);

        return response()->json([
            'status'          => 'success',
            'cycle_id'        => $cycle->cycle_code,
            'total_employees' => $result['total_employees'],
            'total_payment'   => $result['total_payment'],
            'calculations'    => collect($result['calculations'])->map(fn ($c) => [
                'employee_id'     => $c->employee_id,
                'actual_payment'  => (float) $c->actual_payment,
                'unlock_percentage' => (float) $c->unlock_percentage,
                'tier_adjusted_bonus' => (float) $c->tier_adjusted_bonus,
            ])->values(),
        ]);
    }

    /**
     * GET /api/bonus/employee/{employee}/history
     */
    public function employeeHistory(int $employee): JsonResponse
    {
        $history = $this->bonusService->getEmployeeHistory($employee);

        return response()->json($history);
    }

    /**
     * POST /api/bonus/approve
     */
    public function approve(ApproveBonusRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->bonusService->approve(
            (int) $data['cycle_id'],
            $data['calculation_ids'],
            $data['approved_by'],
        );

        return response()->json([
            'status'         => 'approved',
            'approved_count' => $result['approved_count'],
            'total_payment'  => $result['total_payment'],
        ]);
    }

    /**
     * GET /api/bonus/cycle/{cycle}/summary
     */
    public function cycleSummary(int $cycle): JsonResponse
    {
        $bonusCycle = BonusCycle::findOrFail($cycle);
        $calculations = BonusCalculation::with(['employee', 'tier'])
            ->where('cycle_id', $cycle)
            ->orderByDesc('actual_payment')
            ->get();

        return response()->json([
            'cycle'       => $bonusCycle->cycle_code,
            'status'      => $bonusCycle->status,
            'total_employees' => $calculations->count(),
            'total_payment'   => round($calculations->sum(fn ($c) => (float) $c->actual_payment), 2),
            'calculations'    => $calculations->map(fn ($c) => [
                'employee_id'           => $c->employee_id,
                'full_name'             => $c->employee->full_name ?? null,
                'tier'                  => $c->tier->tier_code,
                'base_reference'        => (float) $c->base_reference,
                'tier_adjusted_bonus'   => (float) $c->tier_adjusted_bonus,
                'final_bonus_net'       => (float) $c->final_bonus_net,
                'months_after_probation' => $c->months_after_probation,
                'unlock_percentage'     => (float) $c->unlock_percentage,
                'actual_payment'        => (float) $c->actual_payment,
            ]),
            'tier_distribution' => $calculations->groupBy(fn ($c) => $c->tier->tier_name)->map(fn ($group, $name) => [
                'tier_name'      => $name,
                'employee_count' => $group->count(),
                'avg_payment'    => round($group->avg(fn ($c) => (float) $c->actual_payment), 2),
                'total_payment'  => round($group->sum(fn ($c) => (float) $c->actual_payment), 2),
            ])->values(),
        ]);
    }

    /**
     * PUT /api/bonus/cycle/{cycle}/months
     */
    public function selectCycleMonths(SelectBonusMonthsRequest $request, int $cycle): JsonResponse
    {
        $selectedBy = auth()->user()?->name;

        try {
            $result = $this->bonusService->setCycleSelectedMonths($cycle, $request->validated('months'), $selectedBy);
        } catch (DomainException $e) {
            return response()->json(['status' => 'error', 'errors' => [$e->getMessage()]], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bonus months updated',
            'data' => $result,
        ]);
    }

    /**
     * GET /api/bonus/cycle/{cycle}/months
     */
    public function cycleMonths(int $cycle): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->bonusService->getCycleSelectedMonths($cycle),
        ]);
    }
}
