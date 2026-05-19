<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ExtraIncomeEntry;
use App\Models\LeaveCarryover;
use App\Models\LeaveEncashment;
use App\Models\LeavePolicy;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveManagementController extends Controller
{
    // index() moved to PortalController::loadLeaveTabData() — portal?tab=leave


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

    /** Batch encashment — turn leave days into payroll income for many employees */
    public function batchEncash(Request $request)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'leave_type'   => ['required', 'string', 'in:' . implode(',', array_keys(Employee::LEAVE_TYPES_TRACKED))],
            'year'         => 'required|integer|min:2020|max:2100',
            'mode'         => 'required|in:fixed,all_remaining',
            'days'         => 'nullable|numeric|min:0.5|max:365',
            'payout_month' => 'required|integer|min:1|max:12',
            'payout_year'  => 'required|integer|min:2020|max:2100',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'cap_to_max'   => 'sometimes|boolean',
        ]);

        if ($validated['mode'] === 'fixed' && empty($validated['days'])) {
            return back()->withErrors(['days' => 'ต้องระบุจำนวนวันเมื่อเลือกโหมด "วันคงที่"']);
        }

        $created = 0;
        $totalAmount = 0.0;
        $skipped = [];

        DB::transaction(function () use ($validated, &$created, &$totalAmount, &$skipped) {
            $config = Employee::LEAVE_TYPES_TRACKED[$validated['leave_type']];

            foreach ($validated['employee_ids'] as $empId) {
                $emp = Employee::find($empId);
                if (!$emp) continue;

                $balance = $emp->getLeaveBalance($validated['leave_type'], $validated['year']);
                if (!$balance['allow_encashment']) {
                    $skipped[] = $emp->display_name . ' (นโยบายห้ามแลก)';
                    continue;
                }

                $days = $validated['mode'] === 'all_remaining'
                    ? (float) $balance['remaining']
                    : (float) $validated['days'];

                if (!empty($validated['cap_to_max']) && $balance['max_encash_days_per_year'] !== null) {
                    $maxRemain = max(0, $balance['max_encash_days_per_year'] - $balance['encashed']);
                    $days = min($days, $maxRemain);
                }

                if ($days <= 0) {
                    $skipped[] = $emp->display_name . ' (เหลือ 0 / เกินเพดาน)';
                    continue;
                }
                if ($days > $balance['remaining']) {
                    $skipped[] = $emp->display_name . ' (เหลือไม่พอ ' . $balance['remaining'] . ' วัน)';
                    continue;
                }

                $policy = $emp->effectivePolicy();
                $rate = $policy ? $policy->computeEncashRate($emp) : 0.0;
                if ($rate <= 0) {
                    $base = (float) ($emp->salaryProfile?->base_salary ?? 0);
                    $rate = round($base / 30, 2);
                }
                if ($rate <= 0) {
                    $skipped[] = $emp->display_name . ' (ไม่มีเรท/ฐานเงินเดือน)';
                    continue;
                }

                $amount = round($days * $rate, 2);

                $extra = ExtraIncomeEntry::create([
                    'employee_id'        => $emp->id,
                    'month'              => $validated['payout_month'],
                    'year'               => $validated['payout_year'],
                    'label'              => "แลกวันลาเป็นเงิน ({$config['label']}) — {$days} วัน × " . number_format($rate, 2) . " บาท (Batch)",
                    'category'           => 'leave_encashment',
                    'amount'             => $amount,
                    'include_in_payslip' => true,
                ]);

                $encash = LeaveEncashment::create([
                    'employee_id'           => $emp->id,
                    'year'                  => $validated['year'],
                    'leave_type'            => $validated['leave_type'],
                    'days'                  => $days,
                    'rate_per_day'          => $rate,
                    'amount'                => $amount,
                    'payout_month'          => $validated['payout_month'],
                    'payout_year'           => $validated['payout_year'],
                    'extra_income_entry_id' => $extra->id,
                    'note'                  => 'Batch encash',
                    'created_by'            => Auth::id(),
                    'status'                => 'approved',
                    'approved_by'           => Auth::id(),
                    'approved_at'           => now(),
                ]);

                AuditLogService::logCreated($encash, "Batch encash {$days} วัน ({$config['label']}) → " . number_format($amount, 2) . " บาท");

                if ($emp->user_id) {
                    NotificationService::notify(
                        $emp->user_id,
                        'leave.encashed',
                        'แลกวันลาเป็นเงินสำเร็จ',
                        "{$days} วัน ({$config['label']}) = " . number_format($amount, 2) . " บาท จะรวมในเงินเดือน {$validated['payout_month']}/{$validated['payout_year']}",
                        route('workspace.my', [], false),
                        ['encashment_id' => $encash->id, 'source' => 'batch']
                    );
                }

                $created++;
                $totalAmount += $amount;
            }
        });

        $msg = "แลกสำเร็จ {$created} คน รวม " . number_format($totalAmount, 2) . " บาท";
        if (!empty($skipped)) {
            $msg .= ' / ข้าม ' . count($skipped) . ': ' . implode(', ', array_slice($skipped, 0, 5));
            if (count($skipped) > 5) $msg .= '...';
        }
        return back()->with('success', $msg);
    }

    /** Bulk assign one policy to multiple employees */
    public function bulkAssignPolicy(Request $request)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'leave_policy_id' => 'nullable|exists:leave_policies,id',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $count = 0;
        DB::transaction(function () use ($validated, &$count) {
            foreach ($validated['employee_ids'] as $empId) {
                $emp = Employee::find($empId);
                if (!$emp) continue;
                $old = $emp->leave_policy_id;
                $emp->leave_policy_id = $validated['leave_policy_id'];
                if ($emp->isDirty('leave_policy_id')) {
                    $emp->save();
                    AuditLogService::log($emp, 'leave_policy_assigned', 'leave_policy_id', $old, $emp->leave_policy_id, 'Bulk policy assignment');
                    $count++;
                }
            }
        });

        $policy = $validated['leave_policy_id'] ? LeavePolicy::find($validated['leave_policy_id'])?->name : 'ค่า Default';
        return back()->with('success', "เปลี่ยนนโยบายเป็น \"{$policy}\" ให้ {$count} คน");
    }

    /** GET — employee leave history for year (JSON for modal) */
    public function employeeHistory(Request $request, Employee $employee)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);
        $year = (int) $request->get('year', now()->year);

        $carryovers = $employee->leaveCarryovers()
            ->where(function ($q) use ($year) {
                $q->where('year', $year)->orWhere('source_year', (string) $year);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'leave_type' => $c->leave_type,
                'leave_label' => Employee::LEAVE_TYPES_TRACKED[$c->leave_type]['label'] ?? $c->leave_type,
                'days' => (float) $c->days,
                'source_year' => $c->source_year,
                'target_year' => $c->year,
                'status' => $c->status,
                'note' => $c->note,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        $encashments = $employee->leaveEncashments()
            ->where('year', $year)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'leave_type' => $e->leave_type,
                'leave_label' => Employee::LEAVE_TYPES_TRACKED[$e->leave_type]['label'] ?? $e->leave_type,
                'days' => (float) $e->days,
                'amount' => (float) $e->amount,
                'rate_per_day' => (float) ($e->rate_per_day ?? 0),
                'payout_month' => $e->payout_month,
                'payout_year' => $e->payout_year,
                'status' => $e->status,
                'note' => $e->note,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        $usedLogs = $employee->attendanceLogs()
            ->whereYear('log_date', $year)
            ->whereIn('day_type', array_keys(Employee::LEAVE_TYPES_TRACKED))
            ->orderBy('log_date', 'desc')
            ->get(['id', 'log_date', 'day_type', 'note'])
            ->map(fn($l) => [
                'id' => $l->id,
                'log_date' => $l->log_date?->toDateString(),
                'leave_type' => $l->day_type,
                'leave_label' => Employee::LEAVE_TYPES_TRACKED[$l->day_type]['label'] ?? $l->day_type,
                'note' => $l->note,
            ]);

        // Comprehensive audit trail
        $carryoverIds = $employee->leaveCarryovers()->pluck('id');
        $encashIds = $employee->leaveEncashments()->pluck('id');

        $audits = AuditLog::with('user')
            ->where(function ($q) use ($employee, $carryoverIds, $encashIds) {
                $q->where(function ($q2) use ($employee) {
                    $q2->where('auditable_type', Employee::class)
                       ->where('auditable_id', $employee->id)
                       ->where(function ($q3) {
                           $q3->where('action', 'like', 'leave%')
                              ->orWhere('field_name', 'like', 'leave%');
                       });
                })
                ->orWhere(function ($q2) use ($carryoverIds) {
                    $q2->where('auditable_type', LeaveCarryover::class)
                       ->whereIn('auditable_id', $carryoverIds);
                })
                ->orWhere(function ($q2) use ($encashIds) {
                    $q2->where('auditable_type', LeaveEncashment::class)
                       ->whereIn('auditable_id', $encashIds);
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'field_name' => $a->field_name,
                'reason' => $a->reason,
                'old_value' => $a->old_value,
                'new_value' => $a->new_value,
                'created_by' => $a->user?->name ?? 'system',
                'created_at' => $a->created_at?->toIso8601String(),
                'subject' => class_basename($a->auditable_type),
            ]);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->display_name,
                'code' => $employee->employee_code,
            ],
            'year' => $year,
            'balances' => $employee->getAllLeaveBalances($year),
            'carryovers' => $carryovers,
            'encashments' => $encashments,
            'used_logs' => $usedLogs,
            'audit_logs' => $audits, // Normalized key
        ]);
    }

    /** PATCH — adjust per-employee entitlement override (null = use policy default) */
    public function adjustEntitlement(Request $request, Employee $employee)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'vacation_entitlement'      => 'nullable|integer|min:0|max:365',
            'sick_leave_entitlement'    => 'nullable|integer|min:0|max:365',
            'personal_leave_entitlement'=> 'nullable|integer|min:0|max:365',
            'leave_policy_id'           => 'nullable|exists:leave_policies,id',
            'note'                      => 'nullable|string|max:255',
        ]);

        $old = $employee->only([
            'vacation_entitlement', 'sick_leave_entitlement',
            'personal_leave_entitlement', 'leave_policy_id',
        ]);

        $employee->update([
            'vacation_entitlement'       => $validated['vacation_entitlement'] ?? null,
            'sick_leave_entitlement'     => $validated['sick_leave_entitlement'] ?? null,
            'personal_leave_entitlement' => $validated['personal_leave_entitlement'] ?? null,
            'leave_policy_id'            => $validated['leave_policy_id'] ?? $employee->leave_policy_id,
        ]);

        $reason = $validated['note'] ?? 'ปรับสิทธิวันลาจาก Leave Management';
        AuditLogService::log($employee, 'leave_entitlement_adjusted', 'leave_entitlement', $old, $employee->only(array_keys($old)), $reason);

        if ($employee->user_id) {
            NotificationService::notify(
                $employee->user_id,
                'leave.entitlement_adjusted',
                'สิทธิวันลาของคุณถูกปรับ',
                $reason,
                route('workspace.my', [], false),
                []
            );
        }

        return back()->with('success', "ปรับสิทธิวันลาของ {$employee->display_name} สำเร็จ");
    }
}
