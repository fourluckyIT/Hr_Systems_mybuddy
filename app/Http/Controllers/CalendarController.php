<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\CompanyHoliday;
use App\Models\DaySwapRequest;
use App\Models\LeaveRequest;
use App\Models\RecordingJob;
use App\Models\EditingJob;
use App\Models\JobStage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    public function index(Request $request, $month = null, $year = null)
    {
        // Check for specific date (weekly view prioritizing)
        if ($request->has('date')) {
            $currentDate = Carbon::parse($request->date);
        } else {
            // Fallback for old /calendar/m/y routing
            $month = $month ?: date('n');
            $year = $year ?: date('Y');
            // If viewing current month, look at today, otherwise start of that month
            if ($month == date('n') && $year == date('Y')) {
                $currentDate = Carbon::today();
            } else {
                $currentDate = Carbon::create($year, $month, 1)->startOfMonth();
            }
        }

        // View mode: week (default) or month
        $viewMode = $request->query('view') === 'month' ? 'month' : 'week';

        if ($viewMode === 'month') {
            // Full calendar grid covering the current month, padded to whole weeks
            $startDate = $currentDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
            $endDate   = $currentDate->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        } else {
            // 7-day week (Sunday to Saturday)
            $startDate = $currentDate->copy()->startOfWeek(Carbon::SUNDAY);
            $endDate   = $currentDate->copy()->endOfWeek(Carbon::SATURDAY);
        }

        // Fetch Company Holidays (eager-load type so views can resolve color/icon without N+1)
        $holidays = CompanyHoliday::with('holidayType')
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->get();

        // Auth + employee filter
        $user    = Auth::user();
        $isAdmin = $user->hasRole('admin');

        // Admin can pick any employee (or none = global). Non-admin is forced to their own employee_id.
        $rawFilter = $request->query('employee');
        $rawFilter = ctype_digit((string) $rawFilter) ? (int) $rawFilter : null;
        $filterEmployeeId = $isAdmin ? $rawFilter : ($user->employee?->id);

        // Fetch Attendance Logs (Leaves/LWOP/Not Started)
        $logsQuery = AttendanceLog::with('employee')
            ->whereBetween('log_date', [$startDate, $endDate])
            ->whereNotIn('day_type', ['workday', 'holiday', 'company_holiday', 'not_started']);
        if ($filterEmployeeId) $logsQuery->where('employee_id', $filterEmployeeId);
        $logs = $logsQuery->get();

        // Fetch Recording Jobs
        $recordingJobsQuery = RecordingJob::with('assignees.employee')
            ->whereBetween('scheduled_date', [$startDate, $endDate]);
        if ($filterEmployeeId) {
            $recordingJobsQuery->whereHas('assignees', fn($q) => $q->where('employee_id', $filterEmployeeId));
        }
        $recordingJobs = $recordingJobsQuery->get();

        // Fetch Editing Jobs (Consolidated Pipeline)
        $editingJobsQuery = EditingJob::with(['game', 'assignee'])
            ->active()
            ->whereBetween('deadline_date', [$startDate, $endDate]);
        if ($filterEmployeeId) $editingJobsQuery->where('assigned_to', $filterEmployeeId);
        $editingJobs = $editingJobsQuery->get();

        // Personal leave / swap requests — same filter rule
        $leaveQuery = LeaveRequest::with('employee')
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('leave_date', [$startDate, $endDate]);
        $swapQuery = DaySwapRequest::with('employee')
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('work_date', [$startDate, $endDate])
                  ->orWhereBetween('off_date',  [$startDate, $endDate]);
            });
        if ($filterEmployeeId) {
            $leaveQuery->where('employee_id', $filterEmployeeId);
            $swapQuery->where('employee_id', $filterEmployeeId);
        }

        $leaveRequests = $leaveQuery->get();
        $swapRequests  = $swapQuery->get();

        $events = [];

        // Add Holidays to events (All day)
        foreach ($holidays as $h) {
            $dateStr = $h->holiday_date->format('Y-m-d');
            $events[$dateStr][] = [
                'type' => 'company_holiday',
                'id' => $h->id,
                'label' => $h->effective_icon . ' ' . ($h->holidayType?->name ?? 'วันหยุด') . ': ' . $h->name,
                'color' => $h->effective_color_classes,
                'is_all_day' => true,
                'model' => $h,
            ];
        }

        // Add Logs to events (All day)
        foreach ($logs as $log) {
            $dateStr = Carbon::parse($log->log_date)->format('Y-m-d');
            $label = $this->getLogLabel($log);
            $color = $this->getLogColor($log->day_type);

            $events[$dateStr][] = [
                'type' => 'attendance_log',
                'id' => $log->id,
                'label' => $log->employee->nickname . ': ' . $label,
                'employee_id' => $log->employee_id,
                'color' => $color,
                'is_all_day' => true,
                'model' => $log,
            ];
        }

        // Pre-fetch Job Stages for color mapping
        $jobStages = JobStage::all()->keyBy('code');

        // Add Recording Jobs to events (Time bound or All day)
        foreach ($recordingJobs as $rj) {
            $dateStr = Carbon::parse($rj->scheduled_date)->format('Y-m-d');
            $stage = $jobStages->get($rj->status);
            $c = $stage ? "bg-{$stage->color}-100 text-{$stage->color}-800 border-{$stage->color}-200" : 'bg-amber-100 text-amber-800 border-amber-200';
            
            $isAllDay = empty($rj->scheduled_time);
            $startTimeString = $rj->scheduled_time ? Carbon::parse($rj->scheduled_time)->format('H:i') : null;
            $durationMinutes = $rj->planned_duration_minutes ?: 60; // Default 1 hour if not set

            $events[$dateStr][] = [
                'type' => 'recording_job',
                'id' => $rj->id,
                'label' => ($isAllDay ? '🎥 ' : '') . $rj->title,
                'color' => $c,
                'is_all_day' => $isAllDay,
                'start_time' => $startTimeString,
                'duration_minutes' => $durationMinutes,
                'model' => $rj,
            ];
        }

        // Add Editing Jobs to events (All day)
        foreach ($editingJobs as $ej) {
            $dateStr = Carbon::parse($ej->deadline_date)->format('Y-m-d');
            $stage = $jobStages->get($ej->status);
            $c = $stage ? "bg-{$stage->color}-100 text-{$stage->color}-800 border-{$stage->color}-200" : 'bg-sky-100 text-sky-800 border-sky-200';

            $events[$dateStr][] = [
                'type' => 'editing_job',
                'id' => $ej->id,
                'label' => '✂️ ' . $ej->job_name,
                'color' => $c,
                'is_all_day' => true,
                'model' => $ej,
            ];
        }

        // Add Leave Requests to events (personal)
        $leaveTypeLabels = \App\Models\Employee::LEAVE_TYPE_LABELS;
        foreach ($leaveRequests as $lr) {
            $dateStr = Carbon::parse($lr->leave_date)->format('Y-m-d');
            $isPending = $lr->status === 'pending';
            $color = $isPending ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-blue-100 text-blue-800 border-blue-200';
            $badge = $isPending ? '⏳' : '✓';
            $typeLabel = $leaveTypeLabels[$lr->leave_type] ?? $lr->leave_type;

            $events[$dateStr][] = [
                'type'       => 'leave_request',
                'id'         => $lr->id,
                'label'      => "{$badge} {$lr->employee->nickname}: {$typeLabel}",
                'color'      => $color,
                'is_all_day' => true,
                'model'      => $lr,
            ];
        }

        // Add Day-Swap Requests to events (personal). Show on BOTH the work_date and off_date,
        // with a clear label naming the employee and the counterpart date so admins can see who swapped what.
        foreach ($swapRequests as $sr) {
            $isPending = $sr->status === 'pending';
            $color = $isPending ? 'bg-orange-50 text-orange-600 border-orange-200' : 'bg-orange-100 text-orange-800 border-orange-200';
            $badge = $isPending ? '⏳' : '⇄';
            $name = $sr->employee->nickname ?: trim(($sr->employee->first_name ?? '') . ' ' . ($sr->employee->last_name ?? ''));
            $workStr = Carbon::parse($sr->work_date)->format('d/m');
            $offStr  = Carbon::parse($sr->off_date)->format('d/m');

            // Side that becomes a workday
            $workDate = Carbon::parse($sr->work_date)->format('Y-m-d');
            if ($workDate >= $startDate->format('Y-m-d') && $workDate <= $endDate->format('Y-m-d')) {
                $events[$workDate][] = [
                    'type'       => 'day_swap_request',
                    'id'         => $sr->id,
                    'label'      => "{$badge} {$name}: มาทำงาน (แทนวันที่ {$offStr})",
                    'color'      => $color,
                    'is_all_day' => true,
                    'model'      => $sr,
                ];
            }

            // Side that becomes a holiday
            $offDate = Carbon::parse($sr->off_date)->format('Y-m-d');
            if ($offDate >= $startDate->format('Y-m-d') && $offDate <= $endDate->format('Y-m-d')) {
                $events[$offDate][] = [
                    'type'       => 'day_swap_request',
                    'id'         => $sr->id,
                    'label'      => "{$badge} {$name}: หยุดแทน (มาทำงาน {$workStr})",
                    'color'      => $color,
                    'is_all_day' => true,
                    'model'      => $sr,
                ];
            }
        }

        // Generate the days for the view (week → 7 days; month → full padded month grid)
        $weekDays = [];
        $d = $startDate->copy();
        while ($d <= $endDate) {
            $weekDays[] = [
                'date'             => $d->copy(),
                'date_str'         => $d->format('Y-m-d'),
                'is_today'         => $d->isToday(),
                'is_current_month' => $d->month === $currentDate->month,
                'is_weekend'       => $d->isWeekend(),
            ];
            $d->addDay();
        }

        // Chunk into weeks of 7 days for the month-grid blade
        $monthWeeks = array_chunk($weekDays, 7);

        // Required meta for action modals
        $employees = Employee::active()->orderBy('first_name')->get();
        $youtubers = Employee::active()
            ->whereIn('payroll_mode', ['youtuber_salary', 'youtuber_settlement'])
            ->orderBy('first_name')->get();
        $activeJobStages = $jobStages->where('is_active', true)->sortBy('sort_order');
        $mediaResources = \App\Models\MediaResource::whereIn('status', ['raw', 'ready_for_edit'])->get();

        // --- Mini calendar ---
        $miniCalendarStart = $currentDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $miniCalendarEnd   = $currentDate->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $miniHolidays   = CompanyHoliday::where('is_active', true)
            ->whereBetween('holiday_date', [$miniCalendarStart, $miniCalendarEnd])
            ->get()->groupBy(fn($h) => Carbon::parse($h->holiday_date)->format('Y-m-d'));
        $miniRecordings = RecordingJob::whereBetween('scheduled_date', [$miniCalendarStart, $miniCalendarEnd])
            ->get()->groupBy(fn($rj) => Carbon::parse($rj->scheduled_date)->format('Y-m-d'));
        $miniEdits      = EditingJob::active()
            ->whereBetween('deadline_date', [$miniCalendarStart, $miniCalendarEnd])
            ->get()->groupBy(fn($ej) => Carbon::parse($ej->deadline_date)->format('Y-m-d'));

        $miniCalendarDays = [];
        $d2 = $miniCalendarStart->copy();
        while ($d2 <= $miniCalendarEnd) {
            $ds   = $d2->format('Y-m-d');
            $dots = [];
            if ($miniHolidays->has($ds))   $dots[] = 'holiday';
            if ($miniRecordings->has($ds)) $dots[] = 'recording';
            if ($miniEdits->has($ds))      $dots[] = 'edit';

            $miniCalendarDays[] = [
                'date'             => $d2->copy(),
                'date_str'         => $ds,
                'is_today'         => $d2->isToday(),
                'is_current_month' => $d2->month === $currentDate->month,
                'in_current_week'  => $d2->gte($startDate) && $d2->lte($endDate),
                'is_weekend'       => $d2->isWeekend(),
                'dots'             => $dots,
            ];
            $d2->addDay();
        }

        // --- Upcoming events (today + next 14 days) ---
        $upcomingStart = Carbon::today();
        $upcomingEnd   = Carbon::today()->addDays(14);

        // Holidays are global — always shown
        $upcomingHolidays = CompanyHoliday::with('holidayType')
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$upcomingStart, $upcomingEnd])
            ->orderBy('holiday_date')->get();

        // Person-scoped: respect $filterEmployeeId same as main calendar
        $upRecQuery = RecordingJob::with('assignees.employee')
            ->whereBetween('scheduled_date', [$upcomingStart, $upcomingEnd])
            ->orderBy('scheduled_date')->orderBy('scheduled_time');
        if ($filterEmployeeId) {
            $upRecQuery->whereHas('assignees', fn($q) => $q->where('employee_id', $filterEmployeeId));
        }
        $upcomingRecording = $upRecQuery->get();

        $upEditQuery = EditingJob::with(['game', 'assignee'])
            ->active()
            ->whereBetween('deadline_date', [$upcomingStart, $upcomingEnd])
            ->orderBy('deadline_date');
        if ($filterEmployeeId) $upEditQuery->where('assigned_to', $filterEmployeeId);
        $upcomingEdits = $upEditQuery->get();

        $upLeaveQuery = LeaveRequest::with('employee')
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('leave_date', [$upcomingStart, $upcomingEnd])
            ->orderBy('leave_date');
        if ($filterEmployeeId) $upLeaveQuery->where('employee_id', $filterEmployeeId);
        $upcomingLeaves = $upLeaveQuery->get();

        $upSwapQuery = DaySwapRequest::with('employee')
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($upcomingStart, $upcomingEnd) {
                $q->whereBetween('work_date', [$upcomingStart, $upcomingEnd])
                  ->orWhereBetween('off_date',  [$upcomingStart, $upcomingEnd]);
            });
        if ($filterEmployeeId) $upSwapQuery->where('employee_id', $filterEmployeeId);
        $upcomingSwaps = $upSwapQuery->get();

        $upcomingEvents = collect();
        foreach ($upcomingHolidays as $h) {
            $c = $h->effective_color;
            $upcomingEvents->push([
                'date'  => Carbon::parse($h->holiday_date),
                'label' => $h->name,
                'type'  => 'company_holiday',
                'color' => "bg-{$c}-100 text-{$c}-700",
                'dot'   => "bg-{$c}-400",
                'icon'  => $h->effective_icon,
                'sub'   => $h->holidayType?->name ?? 'วันหยุดบริษัท',
            ]);
        }
        foreach ($upcomingRecording as $rj) {
            $upcomingEvents->push([
                'date'  => Carbon::parse($rj->scheduled_date),
                'label' => $rj->title,
                'type'  => 'recording_job',
                'color' => 'bg-amber-100 text-amber-700',
                'dot'   => 'bg-amber-400',
                'icon'  => '🎥',
                'sub'   => $rj->scheduled_time ? Carbon::parse($rj->scheduled_time)->format('H:i') . ' น.' : 'ทั้งวัน',
            ]);
        }
        foreach ($upcomingEdits as $ej) {
            $upcomingEvents->push([
                'date'  => Carbon::parse($ej->deadline_date),
                'label' => $ej->job_name,
                'type'  => 'editing_job',
                'color' => 'bg-sky-100 text-sky-700',
                'dot'   => 'bg-sky-400',
                'icon'  => '✂️',
                'sub'   => 'ครบกำหนด',
            ]);
        }
        $leaveTypeLabelsUp = \App\Models\Employee::LEAVE_TYPE_LABELS;
        foreach ($upcomingLeaves as $lr) {
            $name = $lr->employee->nickname ?: trim(($lr->employee->first_name ?? '') . ' ' . ($lr->employee->last_name ?? ''));
            $typeLabel = $leaveTypeLabelsUp[$lr->leave_type] ?? $lr->leave_type;
            $isPending = $lr->status === 'pending';
            $upcomingEvents->push([
                'date'  => Carbon::parse($lr->leave_date),
                'label' => "{$name}: {$typeLabel}",
                'type'  => 'leave_request',
                'color' => $isPending ? 'bg-blue-50 text-blue-600' : 'bg-blue-100 text-blue-700',
                'dot'   => 'bg-blue-400',
                'icon'  => $isPending ? '⏳' : '📅',
                'sub'   => $isPending ? 'รออนุมัติ' : 'อนุมัติแล้ว',
            ]);
        }
        foreach ($upcomingSwaps as $sr) {
            $name = $sr->employee->nickname ?: trim(($sr->employee->first_name ?? '') . ' ' . ($sr->employee->last_name ?? ''));
            $isPending = $sr->status === 'pending';
            $workDate = Carbon::parse($sr->work_date);
            $offDate  = Carbon::parse($sr->off_date);
            // Add one upcoming entry per side that falls inside the window
            foreach ([
                ['date' => $workDate, 'sub' => 'มาทำงาน (สลับกับ ' . $offDate->format('d/m') . ')'],
                ['date' => $offDate,  'sub' => 'หยุดแทน (มาทำงาน ' . $workDate->format('d/m') . ')'],
            ] as $part) {
                if ($part['date']->between($upcomingStart, $upcomingEnd)) {
                    $upcomingEvents->push([
                        'date'  => $part['date'],
                        'label' => "{$name}: สลับวัน",
                        'type'  => 'day_swap_request',
                        'color' => $isPending ? 'bg-orange-50 text-orange-600' : 'bg-orange-100 text-orange-700',
                        'dot'   => 'bg-orange-400',
                        'icon'  => $isPending ? '⏳' : '⇄',
                        'sub'   => $part['sub'],
                    ]);
                }
            }
        }
        $upcomingEvents = $upcomingEvents->sortBy(fn($e) => $e['date']->timestamp)->values();

        $games = \App\Models\Game::where('is_active', true)->orderBy('game_name')->get();

        $leaveTypes = \App\Models\Employee::LEAVE_TYPE_LABELS;

        return view('calendar.index', compact(
            'weekDays', 'monthWeeks', 'viewMode', 'filterEmployeeId',
            'events', 'startDate', 'endDate', 'currentDate',
            'employees', 'youtubers', 'activeJobStages', 'jobStages', 'mediaResources',
            'miniCalendarDays', 'upcomingEvents', 'games', 'isAdmin', 'leaveTypes'
        ));
    }

    protected function getLogLabel($log)
    {
        $labels = \App\Models\Employee::LEAVE_TYPE_LABELS + [
            'not_started' => 'ยังไม่เริ่มงาน',
            'ot_full_day' => 'OT เต็มวัน',
        ];

        return $labels[$log->day_type] ?? $log->day_type;
    }

    protected function getLogColor($type)
    {
        $colors = [
            'sick_leave' => 'bg-blue-100 text-blue-800 border-blue-200',
            'personal_leave' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'vacation_leave' => 'bg-teal-100 text-teal-800 border-teal-200',
            'lwop' => 'bg-red-100 text-red-800 border-red-200',
            'not_started' => 'bg-gray-100 text-gray-500 border-gray-200',
            'ot_full_day' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
        ];

        return $colors[$type] ?? 'bg-gray-50 text-gray-600 border-gray-200';
    }
}
