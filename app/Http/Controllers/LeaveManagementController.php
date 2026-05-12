<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ExtraIncomeEntry;
use App\Models\LeaveCarryover;
use App\Models\LeavePolicy;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveManagementController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $today = now();

        $employees = Employee::with([
            'department', 'position', 'leavePolicy',
            'leaveCarryovers' => fn($q) => $q->where('year', $year),
            'leaveEncashments' => fn($q) => $q->where('year', $year),
        ])
            ->where('is_active', true)
            ->whereIn('payroll_mode', ['monthly_staff', 'office_staff', 'youtuber_salary'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $policies = LeavePolicy::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $departments = \App\Models\Department::where('is_active', true)->orderBy('name')->get();

        $rowsArr = $employees->map(function (Employee $emp) use ($year, $today) {
            $balances = $emp->getAllLeaveBalances($year);
            $policy = $emp->effectivePolicy();

            $isProbation = $emp->probation_end_date && $emp->probation_end_date->isFuture();
            $hasNoPolicy = !$policy;

            // Carryover expiry: if vacation has carryover_expires_months and start of $year + months is within 60 days from today
            $vac = $balances['vacation_leave'] ?? null;
            $expiresMonths = $vac['carryover_expires_months'] ?? null;
            $carryIn = (float) ($vac['carryover'] ?? 0);
            $expiringSoon = false;
            $expiresAt = null;
            if ($expiresMonths !== null && $carryIn > 0) {
                $expiresAt = \Carbon\Carbon::create($year, 1, 1)->addMonths($expiresMonths);
                $expiringSoon = $expiresAt->diffInDays($today, false) >= -60 && $expiresAt->isAfter($today);
            }

            $totalRemaining = 0;
            foreach ($balances as $b) $totalRemaining += (float) ($b['remaining'] ?? 0);
            $lowVacation = ($vac['remaining'] ?? 0) < 5 && ($vac['limit'] ?? 0) > 0;

            return [
                'id'          => $emp->id,
                'code'        => $emp->employee_code,
                'name'        => $emp->display_name,
                'dept_id'     => $emp->department_id,
                'dept_name'   => $emp->department?->name ?? '—',
                'pos_name'    => $emp->position?->name,
                'policy_id'   => $policy?->id,
                'policy_name' => $policy?->name,
                'policy_is_default' => (bool) $policy?->is_default,
                'is_probation' => $isProbation,
                'has_no_policy' => $hasNoPolicy,
                'expiring_soon' => $expiringSoon,
                'expires_at'  => $expiresAt?->toDateString(),
                'low_vacation' => $lowVacation,
                'total_remaining' => $totalRemaining,
                'balances' => $balances,
            ];
        })->values()->toArray();

        // Year-end nudge: show banner Oct-Dec, count employees with carryover-eligible unused vacation
        $isYearEndPeriod = (int) $today->month >= 10;
        $expiringCount = collect($rowsArr)->where('expiring_soon', true)->count();
        $unusedVacationCount = collect($rowsArr)
            ->filter(fn($r) => ($r['balances']['vacation_leave']['remaining'] ?? 0) > 0 && ($r['balances']['vacation_leave']['allow_carryover'] ?? false))
            ->count();

        $stats = [
            'total_employees'      => $employees->count(),
            'total_policies'       => $policies->count(),
            'total_carryover_days' => LeaveCarryover::whereIn('employee_id', $employees->pluck('id'))
                                        ->where('year', $year)->sum('days'),
            'total_encash_days'    => \App\Models\LeaveEncashment::whereIn('employee_id', $employees->pluck('id'))
                                        ->where('year', $year)->sum('days'),
            'total_encash_amount'  => \App\Models\LeaveEncashment::whereIn('employee_id', $employees->pluck('id'))
                                        ->where('year', $year)->sum('amount'),
            'no_policy_count'      => collect($rowsArr)->where('has_no_policy', true)->count(),
            'probation_count'      => collect($rowsArr)->where('is_probation', true)->count(),
            'expiring_count'       => $expiringCount,
            'low_vacation_count'   => collect($rowsArr)->where('low_vacation', true)->count(),
        ];

        return view('leave-management.index', compact(
            'rowsArr', 'policies', 'departments', 'year', 'stats', 'isYearEndPeriod', 'unusedVacationCount'
        ));
    }

    /** Batch carryover — admin select multiple employees + ระบบคำนวณยอดที่ยกได้ */
    public function batchCarryover(Request $request)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'leave_type'  => ['required', 'string', 'in:' . implode(',', array_keys(Employee::LEAVE_TYPES_TRACKED))],
            'source_year' => 'required|integer|min:2020|max:2100',
            'target_year' => 'required|integer|gt:source_year',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'cap_to_max'   => 'sometimes|boolean',  // ตัดให้ไม่เกิน max_carryover_days
        ]);

        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($validated, &$created, &$skipped) {
            foreach ($validated['employee_ids'] as $empId) {
                $emp = Employee::find($empId);
                if (!$emp) continue;

                $balance = $emp->getLeaveBalance($validated['leave_type'], $validated['source_year']);
                if (!$balance['allow_carryover']) {
                    $skipped[] = $emp->display_name . ' (นโยบายห้ามยก)';
                    continue;
                }

                $days = $balance['remaining'];
                if ($days <= 0) {
                    $skipped[] = $emp->display_name . ' (เหลือ 0 วัน)';
                    continue;
                }

                if (!empty($validated['cap_to_max']) && $balance['max_carryover_days'] !== null) {
                    $days = min($days, $balance['max_carryover_days']);
                }

                $typeLabel = Employee::LEAVE_TYPES_TRACKED[$validated['leave_type']]['label'] ?? $validated['leave_type'];

                LeaveCarryover::create([
                    'employee_id' => $emp->id,
                    'year'        => $validated['target_year'],
                    'leave_type'  => $validated['leave_type'],
                    'days'        => $days,
                    'source_year' => (string) $validated['source_year'],
                    'note'        => "Batch carryover from {$validated['source_year']} → {$validated['target_year']}",
                    'created_by'  => Auth::id(),
                    'status'      => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);
                $created++;

                if ($emp->user_id) {
                    NotificationService::notify(
                        $emp->user_id,
                        'leave.carryover',
                        'ยกยอดวันลาข้ามปีให้คุณ',
                        "{$days} วัน ({$typeLabel}) จาก ปี {$validated['source_year']} → {$validated['target_year']}",
                        route('workspace.my', [], false),
                        ['source' => 'batch']
                    );
                }
            }
        });

        $msg = "ยกยอดสำเร็จ {$created} คน";
        if (!empty($skipped)) {
            $msg .= ' / ข้าม ' . count($skipped) . ' คน: ' . implode(', ', array_slice($skipped, 0, 5));
            if (count($skipped) > 5) $msg .= '...';
        }
        return back()->with('success', $msg);
    }
}
