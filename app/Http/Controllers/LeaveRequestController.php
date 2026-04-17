<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\DaySwapRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends Controller
{
    protected array $leaveTypes = [
        'sick_leave'     => 'ลาป่วย',
        'personal_leave' => 'ลากิจ',
        'vacation_leave' => 'ลาพักร้อน',
        'lwop'           => 'ลาไม่รับค่าจ้าง (LWOP)',
    ];

    // ─── Leave Requests ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $leaveQuery = LeaveRequest::with(['employee', 'requestedBy', 'reviewedBy'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('leave_date');

        $swapQuery = DaySwapRequest::with(['employee', 'requestedBy', 'reviewedBy'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('work_date');

        if (!$isAdmin) {
            $employeeId = $user->employee?->id;
            $leaveQuery->where('employee_id', $employeeId);
            $swapQuery->where('employee_id', $employeeId);
        }

        return view('leave.index', [
            'leaveRequests' => $leaveQuery->get(),
            'swapRequests'  => $swapQuery->get(),
            'leaveTypes'    => $this->leaveTypes,
            'employees'     => $isAdmin ? Employee::active()->orderBy('first_name')->get() : collect(),
            'isAdmin'       => $isAdmin,
        ]);
    }

    public function storeLeave(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_date'  => ['required', 'date'],
            'leave_type'  => ['required', 'in:' . implode(',', array_keys($this->leaveTypes))],
            'reason'      => ['nullable', 'string', 'max:500'],
        ]);

        // owner can only request for themselves
        if (!$isAdmin && (int) $validated['employee_id'] !== (int) $user->employee?->id) {
            abort(403, 'คุณสามารถขอลาเฉพาะสำหรับตัวเองเท่านั้น');
        }

        $exists = LeaveRequest::where('employee_id', $validated['employee_id'])
            ->where('leave_date', $validated['leave_date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['leave_date' => 'มีคำขอลาสำหรับวันนี้แล้ว'])->withInput();
        }

        LeaveRequest::create([
            'employee_id'  => $validated['employee_id'],
            'leave_date'   => $validated['leave_date'],
            'leave_type'   => $validated['leave_type'],
            'reason'       => $validated['reason'],
            'status'       => $isAdmin ? 'approved' : 'pending',
            'requested_by' => $user->id,
            'reviewed_by'  => $isAdmin ? $user->id : null,
            'reviewed_at'  => $isAdmin ? now() : null,
        ]);

        return back()->with('success', $isAdmin ? 'บันทึกคำขอลาและอนุมัติแล้ว' : 'ส่งคำขอลาสำเร็จ รอแอดมินตรวจสอบ');
    }

    public function reviewLeave(Request $request, LeaveRequest $leaveRequest)
    {
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'action'      => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($leaveRequest, $validated) {
            $leaveRequest->update([
                'status'      => $validated['action'],
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'],
            ]);

            // If approved → apply to attendance log
            if ($validated['action'] === 'approved') {
                $this->applyLeaveToAttendance($leaveRequest);
            }
        });

        return back()->with('success', $validated['action'] === 'approved' ? 'อนุมัติคำขอลาแล้ว' : 'ปฏิเสธคำขอลาแล้ว');
    }

    public function cancelLeave(LeaveRequest $leaveRequest)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        if (!$isAdmin && (int) $leaveRequest->requested_by !== (int) $user->id) {
            abort(403);
        }

        if (!$leaveRequest->isPending()) {
            return back()->withErrors(['error' => 'สามารถยกเลิกได้เฉพาะคำขอที่รอตรวจสอบ']);
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return back()->with('success', 'ยกเลิกคำขอลาแล้ว');
    }

    // ─── Day-Swap Requests ───────────────────────────────────────────────

    public function storeSwap(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date'   => ['required', 'date'],
            'off_date'    => ['required', 'date', 'different:work_date'],
            'reason'      => ['nullable', 'string', 'max:500'],
        ]);

        if (!$isAdmin && (int) $validated['employee_id'] !== (int) $user->employee?->id) {
            abort(403, 'คุณสามารถขอสลับวันเฉพาะสำหรับตัวเองเท่านั้น');
        }

        DaySwapRequest::create([
            'employee_id'  => $validated['employee_id'],
            'work_date'    => $validated['work_date'],
            'off_date'     => $validated['off_date'],
            'reason'       => $validated['reason'],
            'status'       => $isAdmin ? 'approved' : 'pending',
            'requested_by' => $user->id,
            'reviewed_by'  => $isAdmin ? $user->id : null,
            'reviewed_at'  => $isAdmin ? now() : null,
        ]);

        if ($isAdmin) {
            $this->applySwapToAttendance(DaySwapRequest::latest()->first());
        }

        return back()->with('success', $isAdmin ? 'บันทึกการสลับวันและอนุมัติแล้ว' : 'ส่งคำขอสลับวันสำเร็จ รอแอดมินตรวจสอบ');
    }

    public function reviewSwap(Request $request, DaySwapRequest $daySwapRequest)
    {
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'action'      => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($daySwapRequest, $validated) {
            $daySwapRequest->update([
                'status'      => $validated['action'],
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'],
            ]);

            if ($validated['action'] === 'approved') {
                $this->applySwapToAttendance($daySwapRequest);
            }
        });

        return back()->with('success', $validated['action'] === 'approved' ? 'อนุมัติการสลับวันแล้ว' : 'ปฏิเสธการสลับวันแล้ว');
    }

    public function cancelSwap(DaySwapRequest $daySwapRequest)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        if (!$isAdmin && (int) $daySwapRequest->requested_by !== (int) $user->id) {
            abort(403);
        }

        if (!$daySwapRequest->isPending()) {
            return back()->withErrors(['error' => 'สามารถยกเลิกได้เฉพาะคำขอที่รอตรวจสอบ']);
        }

        $daySwapRequest->update(['status' => 'cancelled']);

        return back()->with('success', 'ยกเลิกคำขอสลับวันแล้ว');
    }

    // ─── Internal helpers ────────────────────────────────────────────────

    protected function applyLeaveToAttendance(LeaveRequest $leaveRequest): void
    {
        $log = AttendanceLog::where('employee_id', $leaveRequest->employee_id)
            ->where('log_date', $leaveRequest->leave_date)
            ->first();

        if ($log) {
            $log->update(['day_type' => $leaveRequest->leave_type]);
        } else {
            AttendanceLog::create([
                'employee_id' => $leaveRequest->employee_id,
                'log_date'    => $leaveRequest->leave_date,
                'day_type'    => $leaveRequest->leave_type,
            ]);
        }
    }

    protected function applySwapToAttendance(DaySwapRequest $swap): void
    {
        // work_date → workday, off_date → holiday
        foreach ([
            ['date' => $swap->work_date, 'type' => 'workday'],
            ['date' => $swap->off_date,  'type' => 'holiday'],
        ] as $entry) {
            $log = AttendanceLog::where('employee_id', $swap->employee_id)
                ->where('log_date', $entry['date'])
                ->first();

            if ($log) {
                $log->update([
                    'day_type'              => $entry['type'],
                    'is_swapped_day'        => true,
                    'swapped_from_day_type' => $log->day_type,
                ]);
            } else {
                AttendanceLog::create([
                    'employee_id'           => $swap->employee_id,
                    'log_date'              => $entry['date'],
                    'day_type'              => $entry['type'],
                    'is_swapped_day'        => true,
                ]);
            }
        }
    }
}
