<?php

namespace App\Http\Controllers;

use App\Models\RecordingJob;
use App\Models\RecordingJobAssignee;
use App\Models\MediaResource;
use App\Models\EditingJob;
use App\Models\Game;
use App\Models\Employee;
use App\Models\AuditLog;
use App\Models\LayerRateRule;
use App\Models\WorkLog;
use App\Services\AuditLogService;
use App\Services\EditingJobService;
use App\Support\DurationInput;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class WorkCommandController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'editing');

        $employees = Employee::where('is_active', true)->orderBy('first_name')->get();
        $youtubers = Employee::where('is_active', true)
            ->whereIn('payroll_mode', ['youtuber_salary', 'youtuber_settlement'])
            ->orderBy('first_name')->get();

        $editors = Employee::where('is_active', true)
            ->whereIn('payroll_mode', ['monthly_staff', 'freelance_layer', 'freelance_fixed'])
            ->orderBy('first_name')->get();

        $games = Game::where('is_active', true)->orderBy('game_name')->get();

        $editingJobs = EditingJob::with(['game', 'assignee'])
            ->active()
            ->orderByRaw("CASE status
                WHEN 'assigned' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'review_ready' THEN 3
                WHEN 'final' THEN 4
                ELSE 99
            END")
            ->orderBy('assigned_at', 'desc')
            ->get();

        $summary = [
            'recording_active' => 0,
            'recording_total' => 0,
            'resource_ready' => 0,
            'resource_total' => 0,
            'editing_active' => $editingJobs->whereIn('status', ['assigned', 'in_progress', 'review_ready'])->count(),
            'editing_total' => $editingJobs->count(),
        ];

        $recordings = collect();
        $resources = collect();
        $recordingStatusLogs = collect();

        return view('work.index', compact('tab', 'recordings', 'resources', 'employees', 'youtubers', 'summary', 'recordingStatusLogs', 'editingJobs', 'games', 'editors'));
    }

    // === Recording Jobs ===

    public function storeRecording(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'game_type' => 'nullable|string|max:100',
            'game' => 'nullable|string|max:255',
            'map' => 'nullable|string|max:255',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'planned_duration_hms' => ['nullable', 'regex:/^\d{1,2}:\d{2}(?::\d{2})?$/'],
            'priority' => 'in:low,normal,high,urgent',
            'notes' => 'nullable|string|max:1000',
            'assignees' => 'nullable|array',
            'assignees.*.employee_id' => 'required|exists:employees,id',
            'assignees.*.role' => 'required|string|max:50',
        ]);

        if (!empty($validated['planned_duration_hms'])) {
            $validated['planned_duration_minutes'] = DurationInput::minutesFromHms($validated['planned_duration_hms']);
        }
        unset($validated['planned_duration_hms']);

        $job = DB::transaction(function () use ($validated) {
            $job = RecordingJob::create(array_merge($validated, ['created_by' => auth()->id()]));

            if (!empty($validated['assignees'])) {
                foreach ($validated['assignees'] as $a) {
                    $job->assignees()->create($a);
                }
            }

            // Auto-create Resource
            MediaResource::create([
                'recording_job_id' => $job->id,
                'footage_code' => $this->generateFootageCode($job),
                'title' => $job->title . ' | RAW',
                'status' => 'ready_for_edit',
            ]);

            return $job;
        });

        AuditLogService::logCreated($job, 'Recording job created: ' . $job->title);

        return back()->with('success', 'สร้างคิวถ่ายและ Resource สำเร็จ');
    }

    private function generateFootageCode(RecordingJob $job): string
    {
        $date = $job->scheduled_date ? Carbon::parse($job->scheduled_date) : now();
        $datePart = $date->format('ymd');
        $prefix = 'MR' . $datePart; // Simplified prefix

        $latestCode = MediaResource::where('footage_code', 'like', $prefix . '%')
            ->orderByDesc('footage_code')
            ->first();

        $seq = 1;
        if ($latestCode && preg_match('/(\d{2})$/', $latestCode->footage_code, $matches)) {
            $seq = (int)$matches[1] + 1;
        }

        return $prefix . sprintf('%02d', $seq);
    }

    public function updateRecordingStatus(RecordingJob $recording, Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|string',
            'footage_count' => 'nullable|integer',
            'longest_footage_hms' => ['nullable', 'regex:/^\d{1,2}:\d{2}:\d{2}$/'],
        ]);

        $old = $recording->status;
        $recording->update($validated);

        AuditLogService::log($recording, 'updated', 'status', $old, $validated['status'], 'Status changed');

        return back()->with('success', 'อัปเดตสถานะสำเร็จ');
    }

    public function deleteRecording(RecordingJob $recording)
    {
        AuditLogService::logDeleted($recording, 'Recording job deleted');
        $recording->delete();
        return back()->with('success', 'ลบคิวถ่ายสำเร็จ');
    }

    // === Editing Jobs (Workflow) ===

    public function storeEditingJob(Request $request)
    {
        $validated = $request->validate([
            'job_name' => 'required|string|max:255',
            'game_id' => 'required|exists:games,id',
            'game_link' => 'nullable|url|max:500',
            'deadline_days' => 'nullable|integer|min:1',
            'deadline_date' => 'nullable|date',
            'assigned_to' => 'required|exists:employees,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['deadline_days']) && !empty($validated['deadline_date'])) {
            $deadlineDate = Carbon::parse($validated['deadline_date']);
            $validated['deadline_days'] = max(1, now()->startOfDay()->diffInDays($deadlineDate->startOfDay()));
        }

        if (empty($validated['deadline_days'])) {
            $validated['deadline_days'] = 3; // Default
        }

        $service = app(EditingJobService::class);
        $job = $service->createJob(array_merge($validated, ['assigned_by' => auth()->id()]));

        AuditLogService::logCreated($job, 'Assigned editing job: ' . $job->job_name);

        return back()->with('success', 'มอบหมายงานสำเร็จ');
    }

    public function startEditingJob(EditingJob $editingJob)
    {
        $this->assertCanActOnEditingJob($editingJob);

        $service = app(EditingJobService::class);
        $service->startJob($editingJob);

        AuditLogService::log($editingJob, 'updated', 'status', 'assigned', 'in_progress', 'Started job');

        return back()->with('success', 'เริ่มงานแล้ว');
    }

    public function markEditingJobReady(Request $request, EditingJob $editingJob)
    {
        $this->assertCanActOnEditingJob($editingJob);

        $validated = $request->validate([
            'layer_count' => 'nullable|integer|min:1',
        ]);

        if ($editingJob->assignee?->payroll_mode === 'freelance_layer' && !empty($validated['layer_count'])) {
            $editingJob->update(['layer_count' => $validated['layer_count']]);
        }

        $service = app(EditingJobService::class);
        $service->markReviewReady($editingJob);

        AuditLogService::log($editingJob, 'updated', 'status', 'in_progress', 'review_ready', 'Marked as review ready');

        return back()->with('success', 'ส่งงานพร้อมตรวจแล้ว');
    }

    public function finalizeEditingJob(EditingJob $editingJob)
    {
        $this->assertCanActOnEditingJob($editingJob);

        $service = app(EditingJobService::class);
        $service->finalizeJob($editingJob);

        AuditLogService::log($editingJob, 'updated', 'status', 'review_ready', 'final', 'Finalized job');

        return back()->with('success', 'ปิดงานสำเร็จ');
    }

    public function deleteEditingJob(EditingJob $editingJob)
    {
        $service = app(EditingJobService::class);
        $service->deleteJob($editingJob);

        AuditLogService::logDeleted($editingJob, 'Deleted job');

        return back()->with('success', 'ลบงานสำเร็จ');
    }

    private function assertCanActOnEditingJob(EditingJob $editingJob): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        if ($user->hasRole('admin')) {
            return;
        }

        if ((int) ($user->employee?->id) === (int) $editingJob->assigned_to) {
            return;
        }

        abort(403, 'คุณไม่มีสิทธิ์ดำเนินการกับงานนี้');
    }
}
