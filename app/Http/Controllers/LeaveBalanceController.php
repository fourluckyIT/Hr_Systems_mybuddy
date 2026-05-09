<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ExtraIncomeEntry;
use App\Models\LeaveCarryover;
use App\Models\LeaveEncashment;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveBalanceController extends Controller
{
    /**
     * Employee-initiated carryover request (status=pending, awaits admin approval).
     */
    public function requestCarryover(Request $request, Employee $employee)
    {
        $user = Auth::user();
        // Must be the employee themselves OR admin requesting on someone's behalf
        if ($user->employee?->id !== $employee->id && !$user->hasRole('admin')) {
            abort(403, 'ไม่มีสิทธิ์ส่งคำขอแทนผู้อื่น');
        }

        $validated = $request->validate([
            'leave_type'  => ['required', 'string', 'in:' . implode(',', array_keys(Employee::LEAVE_TYPES_TRACKED))],
            'source_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'target_year' => ['required', 'integer', 'min:2020', 'max:2100', 'gt:source_year'],
            'days'        => ['required', 'numeric', 'min:0.5', 'max:365'],
            'note'        => ['nullable', 'string', 'max:255'],
        ]);

        $sourceBalance = $employee->getLeaveBalance($validated['leave_type'], $validated['source_year']);
        if (!$sourceBalance['allow_carryover']) {
            return back()->withErrors(['leave_type' => 'นโยบายปัจจุบันไม่อนุญาตยกยอด']);
        }
        if ($validated['days'] > $sourceBalance['remaining']) {
            return back()->withErrors(['days' => "วันลาในปี {$validated['source_year']} เหลือเพียง {$sourceBalance['remaining']} วัน"]);
        }

        $entry = LeaveCarryover::create([
            'employee_id' => $employee->id,
            'year'        => $validated['target_year'],
            'leave_type'  => $validated['leave_type'],
            'days'        => $validated['days'],
            'source_year' => $validated['source_year'],
            'note'        => $validated['note'] ?? null,
            'created_by'  => Auth::id(),
            'status'      => 'pending',
        ]);

        AuditLogService::logCreated($entry, "ส่งคำขอยกยอด {$validated['days']} วัน — รอ admin อนุมัติ");

        return redirect()->route('portal.show', ['type' => 'carryover', 'id' => $entry->id])
            ->with('success', 'ส่งคำขอยกยอดวันลาเรียบร้อย — รอผู้ดูแลอนุมัติ');
    }

    /**
     * Employee-initiated encashment request (status=pending, no ExtraIncomeEntry yet).
     * ExtraIncomeEntry only created when admin approves.
     */
    public function requestEncash(Request $request, Employee $employee)
    {
        $user = Auth::user();
        if ($user->employee?->id !== $employee->id && !$user->hasRole('admin')) {
            abort(403, 'ไม่มีสิทธิ์ส่งคำขอแทนผู้อื่น');
        }

        $validated = $request->validate([
            'leave_type'   => ['required', 'string', 'in:' . implode(',', array_keys(Employee::LEAVE_TYPES_TRACKED))],
            'year'         => ['required', 'integer', 'min:2020', 'max:2100'],
            'days'         => ['required', 'numeric', 'min:0.5', 'max:365'],
            'rate_per_day' => ['nullable', 'numeric', 'min:0'],
            'payout_month' => ['required', 'integer', 'min:1', 'max:12'],
            'payout_year'  => ['required', 'integer', 'min:2020', 'max:2100'],
            'note'         => ['nullable', 'string', 'max:255'],
        ]);

        $balance = $employee->getLeaveBalance($validated['leave_type'], $validated['year']);
        if (!$balance['allow_encashment']) {
            return back()->withErrors(['leave_type' => 'นโยบายปัจจุบันไม่อนุญาตแลกเป็นเงิน']);
        }
        if ($validated['days'] > $balance['remaining']) {
            return back()->withErrors(['days' => "วันลาในปี {$validated['year']} เหลือเพียง {$balance['remaining']} วัน"]);
        }

        $ratePerDay = $validated['rate_per_day'] ?? round((float) ($employee->salaryProfile?->base_salary ?? 0) / 30, 2);
        $amount = round($validated['days'] * $ratePerDay, 2);

        $entry = LeaveEncashment::create([
            'employee_id'  => $employee->id,
            'year'         => $validated['year'],
            'leave_type'   => $validated['leave_type'],
            'days'         => $validated['days'],
            'rate_per_day' => $ratePerDay,
            'amount'       => $amount,
            'payout_month' => $validated['payout_month'],
            'payout_year'  => $validated['payout_year'],
            'note'         => $validated['note'] ?? null,
            'created_by'   => Auth::id(),
            'status'       => 'pending',
        ]);

        AuditLogService::logCreated($entry, "ส่งคำขอแลกวันลา {$validated['days']} วัน → " . number_format($amount, 2) . ' บาท — รอ admin อนุมัติ');

        return redirect()->route('portal.show', ['type' => 'encash', 'id' => $entry->id])
            ->with('success', 'ส่งคำขอแลกวันลาเป็นเงินเรียบร้อย — รอผู้ดูแลอนุมัติ');
    }

    public function printForm(Request $request, Employee $employee)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $action = $request->query('action', 'encash'); // 'encash' or 'carryover'
        $year = $request->query('year', now()->year);

        $employee->load(['department', 'position']);
        $company = \App\Models\CompanyProfile::active();
        
        $balance = $employee->getLeaveBalance('vacation_leave', $year);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('leave-balance.print-form', [
            'employee' => $employee,
            'company' => $company,
            'action' => $action,
            'year' => $year,
            'balance' => $balance,
        ]);
        $pdf->setPaper('a4');

        $actionLabel = $action === 'encash' ? 'encashment' : 'carryover';
        $filename = "leave_{$actionLabel}_form_{$employee->employee_code}_{$year}.pdf";

        return $pdf->stream($filename);
    }
    /**
     * ยกยอดวันลา (carryover) จากปีเก่าไปปีใหม่
     * เฉพาะ admin ใช้งาน
     */
    public function carryover(Request $request, Employee $employee)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'leave_type'  => ['required', 'string', 'in:' . implode(',', array_keys(Employee::LEAVE_TYPES_TRACKED))],
            'source_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'target_year' => ['required', 'integer', 'min:2020', 'max:2100', 'gt:source_year'],
            'days'        => ['required', 'numeric', 'min:0.5', 'max:365'],
            'note'        => ['nullable', 'string', 'max:255'],
        ]);

        $config = Employee::LEAVE_TYPES_TRACKED[$validated['leave_type']];
        $sourceBalance = $employee->getLeaveBalance($validated['leave_type'], $validated['source_year']);

        // ตรวจตามนโยบาย: ประเภทนี้ยกยอดได้ไหม
        if (!$sourceBalance['allow_carryover']) {
            return back()->withErrors(['leave_type' => 'นโยบายปัจจุบันไม่อนุญาตยกยอด ' . $config['label']]);
        }

        // ตรวจ max carryover days (ตาม policy)
        if ($sourceBalance['max_carryover_days'] !== null && $validated['days'] > $sourceBalance['max_carryover_days']) {
            return back()->withErrors([
                'days' => "นโยบายกำหนดให้ยกยอดได้สูงสุด {$sourceBalance['max_carryover_days']} วัน",
            ]);
        }

        // ตรวจสอบว่ามียอดเหลือพอจะยกยอดไหม (ปี source)
        if ($validated['days'] > $sourceBalance['remaining']) {
            return back()->withErrors([
                'days' => "วันลาในปี {$validated['source_year']} เหลือเพียง {$sourceBalance['remaining']} วัน",
            ]);
        }

        $entry = LeaveCarryover::create([
            'employee_id'   => $employee->id,
            'year'          => $validated['target_year'],
            'leave_type'    => $validated['leave_type'],
            'days'          => $validated['days'],
            'source_year'   => $validated['source_year'],
            'note'          => $validated['note'] ?? null,
            'created_by'    => Auth::id(),
            // Admin-direct: auto-approved (no employee request flow)
            'status'        => 'approved',
            'approved_by'   => Auth::id(),
            'approved_at'   => now(),
        ]);

        AuditLogService::logCreated($entry, "Carryover {$validated['days']} วัน ({$config['label']}) จาก {$validated['source_year']} → {$validated['target_year']}");

        if ($employee->user_id) {
            NotificationService::notify(
                $employee->user_id,
                'leave.carryover',
                'ยกยอดวันลาข้ามปีให้คุณ',
                "{$validated['days']} วัน ({$config['label']}) จาก ปี {$validated['source_year']} → {$validated['target_year']}",
                route('workspace.my', [], false),
                ['carryover_id' => $entry->id]
            );
        }

        return back()->with('success', "ยกยอด {$validated['days']} วัน ({$config['label']}) สำเร็จ");
    }

    /**
     * ลบรายการยกยอด (admin only)
     */
    public function deleteCarryover(LeaveCarryover $carryover)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        AuditLogService::logDeleted($carryover, "Carryover deleted by " . (Auth::user()?->name ?? 'system'));
        $carryover->delete();

        return back()->with('success', 'ลบรายการยกยอดแล้ว');
    }

    /**
     * แลกวันลาเป็นเงิน (encashment)
     * เฉพาะ admin ใช้งาน — สร้าง ExtraIncomeEntry อัตโนมัติเพื่อรวมเข้ารอบเงินเดือน
     */
    public function encash(Request $request, Employee $employee)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'leave_type'   => ['required', 'string', 'in:' . implode(',', array_keys(Employee::LEAVE_TYPES_TRACKED))],
            'year'         => ['required', 'integer', 'min:2020', 'max:2100'],
            'days'         => ['required', 'numeric', 'min:0.5', 'max:365'],
            'rate_per_day' => ['nullable', 'numeric', 'min:0'],
            'payout_month' => ['required', 'integer', 'min:1', 'max:12'],
            'payout_year'  => ['required', 'integer', 'min:2020', 'max:2100'],
            'note'         => ['nullable', 'string', 'max:255'],
        ]);

        $config = Employee::LEAVE_TYPES_TRACKED[$validated['leave_type']];
        $balance = $employee->getLeaveBalance($validated['leave_type'], $validated['year']);
        $policy = $employee->effectivePolicy();

        // ตรวจตามนโยบาย: ประเภทนี้แลกเงินได้ไหม
        if (!$balance['allow_encashment']) {
            return back()->withErrors(['leave_type' => 'นโยบายปัจจุบันไม่อนุญาตแลก ' . $config['label'] . ' เป็นเงิน']);
        }

        // ตรวจ max encash per year (ตาม policy) — รวมที่แลกไปแล้ว + ที่จะแลกใหม่
        if ($balance['max_encash_days_per_year'] !== null) {
            $totalIfApproved = $balance['encashed'] + $validated['days'];
            if ($totalIfApproved > $balance['max_encash_days_per_year']) {
                $remaining = $balance['max_encash_days_per_year'] - $balance['encashed'];
                return back()->withErrors([
                    'days' => "นโยบายให้แลกได้ปีละสูงสุด {$balance['max_encash_days_per_year']} วัน — แลกได้อีก " . max(0, $remaining) . " วัน",
                ]);
            }
        }

        // ตรวจสอบยอดคงเหลือ
        if ($validated['days'] > $balance['remaining']) {
            return back()->withErrors([
                'days' => "วันลาในปี {$validated['year']} เหลือเพียง {$balance['remaining']} วัน",
            ]);
        }

        // คำนวณ rate ตาม encash_rate_formula ของ policy
        $ratePerDay = (float) ($validated['rate_per_day'] ?? 0);
        if ($ratePerDay <= 0) {
            $ratePerDay = $policy ? $policy->computeEncashRate($employee) : 0.0;
            if ($ratePerDay <= 0) {
                $baseSalary = (float) ($employee->salaryProfile?->base_salary ?? 0);
                $ratePerDay = round($baseSalary / 30, 2);
            }
        }

        if ($ratePerDay <= 0) {
            $hint = $policy && $policy->encash_rate_formula === 'manual'
                ? 'นโยบายตั้งให้ระบุอัตราเอง — กรุณากรอกอัตรา'
                : 'ไม่มีฐานเงินเดือน — ระบุอัตราต่อวันเอง';
            return back()->withErrors(['rate_per_day' => $hint]);
        }

        $amount = round($validated['days'] * $ratePerDay, 2);

        $encashment = DB::transaction(function () use ($employee, $validated, $config, $ratePerDay, $amount) {
            // 1. สร้าง ExtraIncomeEntry เพื่อให้รวมเข้ารอบเงินเดือนของเดือนนั้น
            $extra = ExtraIncomeEntry::create([
                'employee_id'        => $employee->id,
                'month'              => $validated['payout_month'],
                'year'               => $validated['payout_year'],
                'label'              => "แลกวันลาเป็นเงิน ({$config['label']}) — {$validated['days']} วัน × " . number_format($ratePerDay, 2) . " บาท",
                'category'           => 'leave_encashment',
                'amount'             => $amount,
                'include_in_payslip' => true,
            ]);

            // 2. สร้าง LeaveEncashment record (admin-direct = auto-approved)
            return LeaveEncashment::create([
                'employee_id'           => $employee->id,
                'year'                  => $validated['year'],
                'leave_type'            => $validated['leave_type'],
                'days'                  => $validated['days'],
                'rate_per_day'          => $ratePerDay,
                'amount'                => $amount,
                'payout_month'          => $validated['payout_month'],
                'payout_year'           => $validated['payout_year'],
                'extra_income_entry_id' => $extra->id,
                'note'                  => $validated['note'] ?? null,
                'created_by'            => Auth::id(),
                'status'                => 'approved',
                'approved_by'           => Auth::id(),
                'approved_at'           => now(),
            ]);
        });

        AuditLogService::logCreated($encashment, "Encash {$validated['days']} วัน ({$config['label']}) → " . number_format($amount, 2) . " บาท");

        if ($employee->user_id) {
            NotificationService::notify(
                $employee->user_id,
                'leave.encashed',
                'แลกวันลาเป็นเงินสำเร็จ',
                "{$validated['days']} วัน ({$config['label']}) = " . number_format($amount, 2) . " บาท จะรวมในเงินเดือนเดือน {$validated['payout_month']}/{$validated['payout_year']}",
                route('workspace.my', [], false),
                ['encashment_id' => $encashment->id]
            );
        }

        return back()->with('success', "แลก {$validated['days']} วันเป็นเงิน " . number_format($amount, 2) . " บาท สำเร็จ");
    }

    /**
     * ลบรายการแลกวันลา (admin only) — ต้องลบ ExtraIncomeEntry ที่ผูกอยู่ด้วย
     */
    public function deleteEncashment(LeaveEncashment $encashment)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        DB::transaction(function () use ($encashment) {
            if ($encashment->extra_income_entry_id) {
                ExtraIncomeEntry::where('id', $encashment->extra_income_entry_id)->delete();
            }
            AuditLogService::logDeleted($encashment, "Encashment deleted by " . (Auth::user()?->name ?? 'system'));
            $encashment->delete();
        });

        return back()->with('success', 'ลบรายการแลกวันลาแล้ว (รวมรายรับที่ผูกอยู่)');
    }
}
