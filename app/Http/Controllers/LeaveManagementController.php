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

        // Build summary rows
        $rows = $employees->map(function (Employee $emp) use ($year) {
            $balances = $emp->getAllLeaveBalances($year);
            return [
                'employee' => $emp,
                'balances' => $balances,
                'policy'   => $emp->effectivePolicy(),
            ];
        });

        // Aggregate stats
        $stats = [
            'total_employees'      => $employees->count(),
            'total_policies'       => $policies->count(),
            'total_carryover_days' => LeaveCarryover::whereIn('employee_id', $employees->pluck('id'))
                                        ->where('year', $year)->sum('days'),
            'total_encash_days'    => \App\Models\LeaveEncashment::whereIn('employee_id', $employees->pluck('id'))
                                        ->where('year', $year)->sum('days'),
            'total_encash_amount'  => \App\Models\LeaveEncashment::whereIn('employee_id', $employees->pluck('id'))
                                        ->where('year', $year)->sum('amount'),
        ];

        return view('leave-management.index', compact('rows', 'policies', 'year', 'stats'));
    }

    /** Batch carryover — admin select multiple employees + ระบบคำนวณยอดที่ยกได้ */
    public function batchCarryover(Request $request)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'leave_type'  => 'required|in:vacation_leave',
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

                LeaveCarryover::create([
                    'employee_id' => $emp->id,
                    'year'        => $validated['target_year'],
                    'leave_type'  => $validated['leave_type'],
                    'days'        => $days,
                    'source_year' => (string) $validated['source_year'],
                    'note'        => "Batch carryover from {$validated['source_year']} → {$validated['target_year']}",
                    'created_by'  => Auth::id(),
                ]);
                $created++;

                if ($emp->user_id) {
                    NotificationService::notify(
                        $emp->user_id,
                        'leave.carryover',
                        'ยกยอดวันลาข้ามปีให้คุณ',
                        "{$days} วัน (ลาพักร้อน) จาก ปี {$validated['source_year']} → {$validated['target_year']}",
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
