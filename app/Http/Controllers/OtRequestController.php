<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\OtRequest;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OtRequestController extends Controller
{
    /**
     * Employee-facing: list own OT requests + compact form.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee ?? null;

        if (!$employee) {
            // Admins without an employee record belong on the inbox, not the personal form.
            if ($user->hasRole('admin')) {
                return redirect()->route('ot.inbox');
            }
            abort(403, 'Employee record required to request OT.');
        }

        $requests = OtRequest::where('employee_id', $employee->id)
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('ot.request', [
            'employee'   => $employee,
            'requests'   => $requests,
            'todayIso'   => now()->toDateString(),
        ]);
    }

    /**
     * Employee-facing: submit a new OT request.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');

        $data = $request->validate([
            'employee_id'       => 'nullable|integer|exists:employees,id',
            'log_date'          => 'required|date',
            'requested_minutes' => 'required|integer|min:15|max:720',
            'reason'            => 'required|string|max:500',
            'job_reference'     => 'nullable|string|max:120',
        ]);

        // Admin can submit on behalf of any employee via employee_id;
        // owner is always restricted to their own employee record.
        if ($isAdmin && !empty($data['employee_id'])) {
            $employee = Employee::findOrFail($data['employee_id']);
        } else {
            $employee = $user->employee;
            abort_unless($employee, 403, 'Employee record required.');
        }
        unset($data['employee_id']);

        // Prevent duplicate pending request for same date
        $dup = OtRequest::where('employee_id', $employee->id)
            ->whereDate('log_date', $data['log_date'])
            ->where('status', 'pending')
            ->exists();

        if ($dup) {
            return back()->with('error', 'มีคำขอ OT วันนั้นรออนุมัติอยู่แล้ว');
        }

        $dateStr = Carbon::parse($data['log_date'])->toDateString();
        $data['log_date'] = $dateStr;

        $otRequest = DB::transaction(function () use ($employee, $data, $dateStr) {
            $ot = OtRequest::create(array_merge($data, [
                'employee_id' => $employee->id,
                'status'      => 'pending',
            ]));

            // Stamp the attendance log so Workspace admin view can show (i)
            $log = AttendanceLog::where('employee_id', $employee->id)
                ->whereDate('log_date', $dateStr)
                ->first();

            if (!$log) {
                $log = AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'log_date'    => $dateStr,
                    'day_type'    => 'workday',
                ]);
            }
            $log->update([
                'ot_status'       => 'requested',
                'ot_request_id'   => $ot->id,
                'ot_request_note' => $data['reason'],
            ]);

            return $ot;
        });

        AuditLogService::logCreated($otRequest, 'OT request submitted');

        NotificationService::notifyAdmins(
            'ot.requested',
            "คำขอ OT: {$employee->display_name} · ".$otRequest->log_date->format('d/m'),
            $otRequest->reason,
            route('ot.inbox', [], false),
            ['ot_request_id' => $otRequest->id]
        );

        return back()->with('success', 'ส่งคำขอ OT แล้ว รอ Admin อนุมัติ');
    }

    /**
     * Employee-facing: cancel own pending request.
     */
    public function cancel(Request $request, OtRequest $otRequest)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $otRequest->employee_id === $employee->id, 403);
        abort_unless($otRequest->status === 'pending', 422, 'ยกเลิกไม่ได้: คำขอถูกตรวจสอบแล้ว');

        DB::transaction(function () use ($otRequest) {
            $otRequest->update(['status' => 'cancelled']);
            AttendanceLog::where('ot_request_id', $otRequest->id)->update([
                'ot_status' => 'none',
                'ot_request_id' => null,
                'ot_request_note' => null,
            ]);
        });

        return back()->with('success', 'ยกเลิกคำขอแล้ว');
    }

    /**
     * Admin-facing: approve a request (called from Workspace (i) popup or inbox).
     */
    public function approve(Request $request, OtRequest $otRequest)
    {
        abort_unless($otRequest->status === 'pending', 422, 'คำขอนี้ไม่ได้อยู่สถานะรออนุมัติ');

        DB::transaction(function () use ($otRequest, $request) {
            $otRequest->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $request->input('note'),
            ]);

            $log = AttendanceLog::where('ot_request_id', $otRequest->id)->first();
            if ($log) {
                $log->update([
                    'ot_enabled' => true,
                    'ot_minutes' => max($log->ot_minutes, $otRequest->requested_minutes),
                    'ot_status'  => 'approved',
                ]);
            }
        });

        AuditLogService::log($otRequest, 'approved', 'ot_request', [], $otRequest->getAttributes(), 'OT approved');

        if ($otRequest->employee && $otRequest->employee->user_id) {
            NotificationService::notify(
                $otRequest->employee->user_id,
                'ot.approved',
                'คำขอ OT ได้รับการอนุมัติ',
                'วันที่ '.$otRequest->log_date->format('d/m/Y').' · '.$otRequest->requested_minutes.' นาที',
                route('ot.request', [], false),
                ['ot_request_id' => $otRequest->id]
            );
        }

        return back()->with('success', 'อนุมัติคำขอ OT แล้ว');
    }

    /**
     * Admin-facing: reject a pending OT request.
     */
    public function reject(Request $request, OtRequest $otRequest)
    {
        abort_unless($otRequest->status === 'pending', 422, 'คำขอนี้ไม่ได้อยู่สถานะรออนุมัติ');

        $data = $request->validate([
            'review_note' => 'nullable|string|max:300',
        ]);

        DB::transaction(function () use ($otRequest, $request, $data) {
            $otRequest->update([
                'status'      => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);

            AttendanceLog::where('ot_request_id', $otRequest->id)->update([
                'ot_status'       => 'rejected',
                'ot_request_note' => $data['review_note'] ?? null,
            ]);
        });

        AuditLogService::log($otRequest, 'rejected', 'ot_request', [], $otRequest->getAttributes(), 'OT rejected');

        if ($otRequest->employee && $otRequest->employee->user_id) {
            NotificationService::notify(
                $otRequest->employee->user_id,
                'ot.rejected',
                'คำขอ OT ไม่ได้รับการอนุมัติ',
                'วันที่ '.$otRequest->log_date->format('d/m/Y').' · '.($data['review_note'] ?? 'ไม่มีหมายเหตุ'),
                route('ot.request', [], false),
                ['ot_request_id' => $otRequest->id]
            );
        }

        return back()->with('success', 'ปฏิเสธคำขอ OT แล้ว');
    }

    /**
     * Admin-facing: consolidated OT inbox across all employees.
     */
    public function inbox()
    {
        $pending = OtRequest::with('employee')->pending()->orderBy('log_date')->get();
        return view('ot.inbox', compact('pending'));
    }
}
