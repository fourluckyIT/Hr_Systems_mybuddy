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

        // We want a 7-day week (Sunday to Saturday)
        $startDate = $currentDate->copy()->startOfWeek(Carbon::SUNDAY);
        $endDate = $currentDate->copy()->endOfWeek(Carbon::SATURDAY);

        // Fetch Company Holidays
        $holidays = CompanyHoliday::where('is_active', true)
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->get();

        // Fetch Attendance Logs (Leaves/LWOP/Not Started)
        $logs = AttendanceLog::with('employee')
            ->whereBetween('log_date', [$startDate, $endDate])
            ->whereNotIn('day_type', ['workday', 'holiday', 'company_holiday', 'not_started'])
            ->get();

        // Fetch Recording Jobs
        $recordingJobs = RecordingJob::with('assignees.employee')
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->get();

        // Fetch Editing Jobs (Consolidated Pipeline)
        $editingJobs = EditingJob::with(['game', 'assignee'])
            ->active()
            ->whereBetween('deadline_date', [$startDate, $endDate])
            ->get();

        // Fetch personal leave / swap requests
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $leaveQuery = LeaveRequest::with('employee')
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('leave_date', [$startDate, $endDate]);
        $swapQuery = DaySwapRequest::with('employee')
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('work_date', [$startDate, $endDate]);

        if (!$isAdmin) {
            $myEmployeeId = $user->employee?->id;
            $leaveQuery->where('employee_id', $myEmployeeId);
            $swapQuery->where('employee_id', $myEmployeeId);
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
                'label' => '🏢 วันหยุด: ' . $h->name,
                'color' => 'bg-purple-100 text-purple-800 border-purple-200',
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
        $leaveTypeLabels = ['sick_leave' => 'ลาป่วย', 'personal_leave' => 'ลากิจ', 'vacation_leave' => 'ลาพักร้อน', 'lwop' => 'LWOP'];
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

        // Add Day-Swap Requests to events (personal)
        foreach ($swapRequests as $sr) {
            $dateStr = Carbon::parse($sr->work_date)->format('Y-m-d');
            $isPending = $sr->status === 'pending';
            $color = $isPending ? 'bg-orange-50 text-orange-600 border-orange-200' : 'bg-orange-100 text-orange-800 border-orange-200';
            $badge = $isPending ? '⏳' : '⇄';

            $events[$dateStr][] = [
                'type'       => 'day_swap_request',
                'id'         => $sr->id,
                'label'      => "{$badge} {$sr->employee->nickname}: สลับวัน",
                'color'      => $color,
                'is_all_day' => true,
                'model'      => $sr,
            ];
        }

        // Generate the 7 days for the view
        $weekDays = [];
        $d = $startDate->copy();
        while ($d <= $endDate) {
            $weekDays[] = [
                'date' => $d->copy(),
                'date_str' => $d->format('Y-m-d'),
                'is_today' => $d->isToday(),
            ];
            $d->addDay();
        }

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

        $upcomingHolidays = CompanyHoliday::where('is_active', true)
            ->whereBetween('holiday_date', [$upcomingStart, $upcomingEnd])
            ->orderBy('holiday_date')->get();

        $upcomingRecording = RecordingJob::with('assignees.employee')
            ->whereBetween('scheduled_date', [$upcomingStart, $upcomingEnd])
            ->orderBy('scheduled_date')->orderBy('scheduled_time')
            ->get();

        $upcomingEdits = EditingJob::with(['game', 'assignee'])
            ->active()
            ->whereBetween('deadline_date', [$upcomingStart, $upcomingEnd])
            ->orderBy('deadline_date')->get();

        $upcomingEvents = collect();
        foreach ($upcomingHolidays as $h) {
            $upcomingEvents->push([
                'date'  => Carbon::parse($h->holiday_date),
                'label' => $h->name,
                'type'  => 'company_holiday',
                'color' => 'bg-purple-100 text-purple-700',
                'dot'   => 'bg-purple-400',
                'icon'  => '🏢',
                'sub'   => 'วันหยุดบริษัท',
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
        $upcomingEvents = $upcomingEvents->sortBy(fn($e) => $e['date']->timestamp)->values();

        $games = \App\Models\Game::where('is_active', true)->orderBy('game_name')->get();

        return view('calendar.index', compact(
            'weekDays', 'events', 'startDate', 'endDate', 'currentDate',
            'employees', 'youtubers', 'activeJobStages', 'jobStages', 'mediaResources',
            'miniCalendarDays', 'upcomingEvents', 'games', 'isAdmin'
        ));
    }

    protected function getLogLabel($log)
    {
        $labels = [
            'sick_leave' => 'ลาป่วย',
            'personal_leave' => 'ลากิจ',
            'vacation_leave' => 'ลาพักร้อน',
            'lwop' => 'LWOP',
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
