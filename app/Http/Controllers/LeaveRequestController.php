<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\DaySwapRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\AttendanceRule;
use App\Models\CompanyHoliday;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        $leaveRequests = $leaveQuery->get();
        $swapRequests  = $swapQuery->get();

        $stats = [
            'leave_pending'  => $leaveRequests->where('status', 'pending')->count(),
            'leave_approved' => $leaveRequests->where('status', 'approved')->count(),
            'leave_rejected' => $leaveRequests->where('status', 'rejected')->count(),
            'leave_total'    => $leaveRequests->count(),
            'swap_pending'   => $swapRequests->where('status', 'pending')->count(),
            'swap_approved'  => $swapRequests->where('status', 'approved')->count(),
            'swap_total'     => $swapRequests->count(),
        ];

        return view('leave.index', [
            'leaveRequests' => $leaveRequests,
            'swapRequests'  => $swapRequests,
            'leaveTypes'    => $this->leaveTypes,
            'employees'     => $isAdmin ? Employee::active()->orderBy('first_name')->get() : collect(),
            'isAdmin'       => $isAdmin,
            'stats'         => $stats,
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

        // Probation gate (skip for admin who logs on behalf)
        // Per Thai labor law: sick (ม.32) and personal (ม.34) ALLOWED during probation,
        // vacation (ม.30) NOT allowed (requires ≥ 1 year of service)
        if (!$isAdmin) {
            $emp = Employee::find($validated['employee_id']);
            $policy = $emp?->effectivePolicy();
            $leaveType = $validated['leave_type'];
            if ($emp && $policy && !$policy->allowsDuringProbation($leaveType)) {
                $probationEnd = $emp->probation_end_date;
                $leaveDate = \Carbon\Carbon::parse($validated['leave_date']);
                if ($probationEnd && $leaveDate->lessThanOrEqualTo($probationEnd)) {
                    return back()->withErrors([
                        'leave_date' => 'ไม่สามารถลาประเภทนี้ระหว่างทดลองงานได้ (ถึง ' . $probationEnd->format('d/m/Y') . ')',
                    ])->withInput();
                }
            }

            // Vacation eligibility — must have served ≥ vacation_eligibility_months from start_date (default 12, ม.30)
            if ($emp && $policy && $leaveType === 'vacation_leave' && $emp->start_date) {
                $eligibleFrom = $emp->start_date->copy()->addMonths((int) ($policy->vacation_eligibility_months ?? 12));
                $leaveDate = \Carbon\Carbon::parse($validated['leave_date']);
                if ($leaveDate->lt($eligibleFrom)) {
                    return back()->withErrors([
                        'leave_date' => 'ลาพักร้อนได้เมื่อทำงานครบ ' . ($policy->vacation_eligibility_months ?? 12) . ' เดือน (เริ่มลาพักร้อนได้ตั้งแต่ ' . $eligibleFrom->format('d/m/Y') . ' — ม.30 พรบ.คุ้มครองแรงงาน)',
                    ])->withInput();
                }
            }

            // Quota check — กันส่งคำขอลาที่เกินสิทธิ
            if ($emp && in_array($validated['leave_type'], array_keys(Employee::LEAVE_TYPES_TRACKED), true)) {
                $year = (int) \Carbon\Carbon::parse($validated['leave_date'])->format('Y');
                $balance = $emp->getLeaveBalance($validated['leave_type'], $year);
                if ($balance['remaining'] <= 0) {
                    return back()->withErrors([
                        'leave_type' => "สิทธิ {$balance['label']} ปี {$year} หมดแล้ว (ใช้ไป {$balance['used']}/{" . rtrim(rtrim(number_format($balance['total_available'], 1), '0'), '.') . "})",
                    ])->withInput();
                }
            }
        }

        $exists = LeaveRequest::where('employee_id', $validated['employee_id'])
            ->where('leave_date', $validated['leave_date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['leave_date' => 'มีคำขอลาสำหรับวันนี้แล้ว'])->withInput();
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id'  => $validated['employee_id'],
            'leave_date'   => $validated['leave_date'],
            'leave_type'   => $validated['leave_type'],
            'reason'       => $validated['reason'],
            'status'       => $isAdmin ? 'approved' : 'pending',
            'requested_by' => $user->id,
            'reviewed_by'  => $isAdmin ? $user->id : null,
            'reviewed_at'  => $isAdmin ? now() : null,
        ]);

        if ($isAdmin) {
            $this->applyLeaveToAttendance($leaveRequest);
        } else {
            // แจ้งเตือน Admin เมื่อพนักงานยื่นคำขอ (ไม่ต้องแจ้งถ้า Admin บันทึกให้เอง)
            $employee = $user->employee;
            NotificationService::notifyAdmins(
                'leave.requested',
                'คำขอลา: ' . ($employee?->display_name ?? $user->name)
                    . ' · ' . Carbon::parse($validated['leave_date'])->format('d/m'),
                ($this->leaveTypes[$validated['leave_type']] ?? $validated['leave_type'])
                    . ($validated['reason'] ? ' — ' . $validated['reason'] : ''),
                route('leave.index', [], false),
                ['leave_request_id' => $leaveRequest->id]
            );
        }

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
                'review_note' => $validated['review_note'] ?? null,
            ]);

            // If approved → apply to attendance log
            if ($validated['action'] === 'approved') {
                $this->applyLeaveToAttendance($leaveRequest);
            }
        });

        // Notify the requester
        $leaveRequest->loadMissing('employee');
        if ($leaveRequest->employee && $leaveRequest->employee->user_id) {
            $approved = $validated['action'] === 'approved';
            NotificationService::notify(
                $leaveRequest->employee->user_id,
                $approved ? 'leave.approved' : 'leave.rejected',
                $approved ? 'คำขอลาได้รับการอนุมัติ' : 'คำขอลาไม่ได้รับการอนุมัติ',
                'วันที่ ' . Carbon::parse($leaveRequest->leave_date)->format('d/m/Y')
                    . ' · ' . ($this->leaveTypes[$leaveRequest->leave_type] ?? $leaveRequest->leave_type)
                    . ($validated['review_note'] ?? null ? ' — ' . $validated['review_note'] : ''),
                route('leave.index', [], false),
                ['leave_request_id' => $leaveRequest->id]
            );
        }

        return back()->with('success', $validated['action'] === 'approved' ? 'อนุมัติคำขอลาแล้ว' : 'ปฏิเสธคำขอลาแล้ว');
    }

    public function cancelLeave(LeaveRequest $leaveRequest)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        if (!$isAdmin && (int) $leaveRequest->requested_by !== (int) $user->id) {
            abort(403);
        }

        if (!$isAdmin && $leaveRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'สามารถยกเลิกได้เฉพาะคำขอที่รอตรวจสอบ']);
        }

        if ($leaveRequest->status === 'approved') {
            $this->reverseLeaveFromAttendance($leaveRequest);
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

        $swap = DaySwapRequest::create([
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
            $this->applySwapToAttendance($swap);
        } else {
            $employee = $user->employee;
            NotificationService::notifyAdmins(
                'swap.requested',
                'คำขอสลับวัน: ' . ($employee?->display_name ?? $user->name),
                'มาทำงาน ' . Carbon::parse($validated['work_date'])->format('d/m/Y')
                    . ' ↔ หยุด ' . Carbon::parse($validated['off_date'])->format('d/m/Y')
                    . ($validated['reason'] ? ' — ' . $validated['reason'] : ''),
                route('leave.index', [], false),
                ['day_swap_request_id' => $swap->id]
            );
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $isAdmin ? 'บันทึกการสลับวันและอนุมัติแล้ว' : 'ส่งคำขอสลับวันสำเร็จ รอแอดมินตรวจสอบ'
            ]);
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

        try {
            DB::transaction(function () use ($daySwapRequest, $validated) {
                if ($validated['action'] === 'approved') {
                    $this->validateSwapPolicyForApproval($daySwapRequest);
                }

                $daySwapRequest->update([
                    'status'      => $validated['action'],
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    'review_note' => $validated['review_note'] ?? null,
                ]);

                if ($validated['action'] === 'approved') {
                    $this->applySwapToAttendance($daySwapRequest);
                }
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $daySwapRequest->loadMissing('employee');
        if ($daySwapRequest->employee && $daySwapRequest->employee->user_id) {
            $approved = $validated['action'] === 'approved';
            NotificationService::notify(
                $daySwapRequest->employee->user_id,
                $approved ? 'swap.approved' : 'swap.rejected',
                $approved ? 'คำขอสลับวันได้รับการอนุมัติ' : 'คำขอสลับวันไม่ได้รับการอนุมัติ',
                'มาทำงาน ' . Carbon::parse($daySwapRequest->work_date)->format('d/m/Y')
                    . ' ↔ หยุด ' . Carbon::parse($daySwapRequest->off_date)->format('d/m/Y')
                    . ($validated['review_note'] ?? null ? ' — ' . $validated['review_note'] : ''),
                route('leave.index', [], false),
                ['day_swap_request_id' => $daySwapRequest->id]
            );
        }

        return back()->with('success', $validated['action'] === 'approved' ? 'อนุมัติการสลับวันแล้ว' : 'ปฏิเสธการสลับวันแล้ว');
    }

    public function cancelSwap(DaySwapRequest $daySwapRequest)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        if (!$isAdmin && (int) $daySwapRequest->requested_by !== (int) $user->id) {
            abort(403);
        }

        if (!$isAdmin && $daySwapRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'สามารถยกเลิกได้เฉพาะคำขอที่รอตรวจสอบ']);
        }

        if ($daySwapRequest->status === 'approved') {
            $this->reverseSwapFromAttendance($daySwapRequest);
        }

        $daySwapRequest->update(['status' => 'cancelled']);

        return back()->with('success', 'ยกเลิกคำขอสลับวันแล้ว');
    }

    // ─── Internal helpers ────────────────────────────────────────────────

    protected function applyLeaveToAttendance(LeaveRequest $leaveRequest): void
    {
        $dateStr = Carbon::parse($leaveRequest->leave_date)->toDateString();

        $log = AttendanceLog::where('employee_id', $leaveRequest->employee_id)
            ->whereDate('log_date', $dateStr)
            ->first();

        if ($log) {
            $log->update(['day_type' => $leaveRequest->leave_type]);
        } else {
            AttendanceLog::create([
                'employee_id' => $leaveRequest->employee_id,
                'log_date'    => $dateStr,
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
            $dateStr = Carbon::parse($entry['date'])->toDateString();

            $log = AttendanceLog::where('employee_id', $swap->employee_id)
                ->whereDate('log_date', $dateStr)
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
                    'log_date'              => $dateStr,
                    'day_type'              => $entry['type'],
                    'is_swapped_day'        => true,
                ]);
            }
        }
    }

    protected function reverseLeaveFromAttendance(LeaveRequest $leaveRequest): void
    {
        $dateStr = Carbon::parse($leaveRequest->leave_date)->toDateString();
        $log = AttendanceLog::where('employee_id', $leaveRequest->employee_id)
            ->whereDate('log_date', $dateStr)
            ->first();

        if ($log && $log->day_type === $leaveRequest->leave_type) {
            $isCompanyHoliday = CompanyHoliday::where('is_active', true)
                ->whereDate('holiday_date', $dateStr)
                ->exists();
            $restoredType = $isCompanyHoliday ? 'company_holiday' : (Carbon::parse($dateStr)->isWeekend() ? 'holiday' : 'workday');
            
            $log->update(['day_type' => $restoredType]);
        }
    }

    protected function reverseSwapFromAttendance(DaySwapRequest $swap): void
    {
        foreach ([
            ['date' => $swap->work_date, 'original_type' => 'holiday'],
            ['date' => $swap->off_date,  'original_type' => 'workday'],
        ] as $entry) {
            $dateStr = Carbon::parse($entry['date'])->toDateString();
            $log = AttendanceLog::where('employee_id', $swap->employee_id)
                ->whereDate('log_date', $dateStr)
                ->first();

            if ($log && $log->is_swapped_day) {
                $restoredType = $log->swapped_from_day_type;
                if (!$restoredType) {
                    $isCompanyHoliday = CompanyHoliday::where('is_active', true)
                        ->whereDate('holiday_date', $dateStr)
                        ->exists();
                    $restoredType = $isCompanyHoliday ? 'company_holiday' : (Carbon::parse($dateStr)->isWeekend() ? 'holiday' : 'workday');
                }
                
                $log->update([
                    'day_type'              => $restoredType,
                    'is_swapped_day'        => false,
                    'swapped_from_day_type' => null,
                ]);
            }
        }
    }

    protected function validateSwapPolicyForApproval(DaySwapRequest $swap): void
    {
        $workDateType = $this->resolveDayTypeForDate($swap->employee_id, Carbon::parse($swap->work_date));
        $offDateType = $this->resolveDayTypeForDate($swap->employee_id, Carbon::parse($swap->off_date));

        if (!in_array($workDateType, ['holiday', 'company_holiday'], true)) {
            throw ValidationException::withMessages([
                'work_date' => 'วันที่จะมาทำงานต้องเป็นวันหยุดเท่านั้น (weekly/company holiday)',
            ]);
        }

        if (!in_array($offDateType, ['workday', 'ot_full_day'], true)) {
            throw ValidationException::withMessages([
                'off_date' => 'วันที่จะหยุดแทนต้องเป็นวันทำงานปกติ',
            ]);
        }

        if ($workDateType === 'company_holiday' && !$this->allowCompanyHolidaySwap()) {
            throw ValidationException::withMessages([
                'work_date' => 'วันหยุดตามประเพณีไม่สามารถสลับได้สำหรับกิจการทั่วไป (ยกเว้นกิจการที่กฎหมายกำหนด)',
            ]);
        }

        if ($this->wouldExceedSixConsecutiveWorkdays($swap)) {
            throw ValidationException::withMessages([
                'off_date' => 'การสลับนี้จะทำให้ทำงานติดต่อกันเกิน 6 วัน',
            ]);
        }
    }

    protected function allowCompanyHolidaySwap(): bool
    {
        $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
        return (bool) ($workingHoursRule?->config['allow_company_holiday_swap'] ?? false);
    }

    protected function resolveDayTypeForDate(int $employeeId, Carbon $date): string
    {
        $dateString = $date->toDateString();

        $log = AttendanceLog::where('employee_id', $employeeId)
            ->whereDate('log_date', $dateString)
            ->first();

        if ($log) {
            return (string) $log->day_type;
        }

        $isCompanyHoliday = CompanyHoliday::where('is_active', true)
            ->whereDate('holiday_date', $dateString)
            ->exists();

        if ($isCompanyHoliday) {
            return 'company_holiday';
        }

        return $date->isWeekend() ? 'holiday' : 'workday';
    }

    protected function wouldExceedSixConsecutiveWorkdays(DaySwapRequest $swap): bool
    {
        $isWorkday = static fn(?string $dayType): bool => in_array((string) $dayType, ['workday', 'ot_full_day'], true);

        $workDate = Carbon::parse($swap->work_date)->startOfDay();
        $offDate = Carbon::parse($swap->off_date)->startOfDay();
        $startPivot = $workDate->lessThanOrEqualTo($offDate) ? $workDate : $offDate;
        $endPivot = $workDate->greaterThanOrEqualTo($offDate) ? $workDate : $offDate;
        $start = $startPivot->copy()->subDays(14);
        $end = $endPivot->copy()->addDays(14);

        $logs = AttendanceLog::where('employee_id', $swap->employee_id)
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn(AttendanceLog $log) => Carbon::parse($log->log_date)->toDateString());

        $overrides = [
            $workDate->toDateString() => 'workday',
            $offDate->toDateString() => 'holiday',
        ];

        $maxStreak = 0;
        $streak = 0;

        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $dateKey = $date->toDateString();
            $dayType = $overrides[$dateKey] ?? ($logs[$dateKey]->day_type ?? $this->resolveDayTypeForDate($swap->employee_id, $date));

            if ($isWorkday($dayType)) {
                $streak++;
                $maxStreak = max($maxStreak, $streak);
            } else {
                $streak = 0;
            }
        }

        return $maxStreak > 6;
    }
}
