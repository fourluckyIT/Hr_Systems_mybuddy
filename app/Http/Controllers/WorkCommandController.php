<?php

namespace App\Http\Controllers;

use App\Models\RecordingJob;
use App\Models\RecordingJobAssignee;
use App\Models\MediaResource;
use App\Models\EditJob;
use App\Models\ApprovedWorkOutput;
use App\Models\Employee;
use App\Models\AuditLog;
use App\Models\LayerRateRule;
use App\Models\WorkLog;
use App\Services\AuditLogService;
use App\Support\DurationInput;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkCommandController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'recording');

        $recordings = RecordingJob::with('assignees.employee')
            ->orderByRaw("CASE status
                WHEN 'scheduled' THEN 1
                WHEN 'recording' THEN 2
                WHEN 'draft' THEN 3
                WHEN 'shot' THEN 4
                WHEN 'cancelled' THEN 5
                ELSE 99
            END")
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        $recordingStatusLogs = collect();
        if ($recordings->isNotEmpty()) {
            $recordingStatusLogs = AuditLog::with('user')
                ->where('auditable_type', RecordingJob::class)
                ->where('action', 'updated')
                ->where('field', 'status')
                ->whereIn('auditable_id', $recordings->pluck('id'))
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('auditable_id');
        }

        $resources = MediaResource::with('recordingJob')
            ->withCount('editJobs')
            ->orderByRaw("CASE status
                WHEN 'raw' THEN 1
                WHEN 'uploaded' THEN 2
                WHEN 'ready_for_edit' THEN 3
                WHEN 'in_use' THEN 4
                WHEN 'archived' THEN 5
                ELSE 99
            END")
            ->orderByDesc('id')
            ->get();

        $editJobs = EditJob::with(['mediaResource', 'editor'])
            ->orderByRaw("CASE status
                WHEN 'assigned' THEN 1
                WHEN 'editing' THEN 2
                WHEN 'pending_resource' THEN 3
                WHEN 'submitted' THEN 4
                WHEN 'approved' THEN 5
                WHEN 'done' THEN 6
                ELSE 99
            END")
            ->orderBy('due_date')
            ->get();

        $employees = Employee::where('is_active', true)->orderBy('first_name')->get();
        $youtubers = Employee::where('is_active', true)
            ->whereIn('payroll_mode', ['youtuber_salary', 'youtuber_settlement'])
            ->orderBy('first_name')->get();

        // Summary counts
        $summary = [
            'recording_active' => $recordings->whereIn('status', ['scheduled', 'recording'])->count(),
            'recording_total' => $recordings->count(),
            'resource_ready' => $resources->where('status', 'ready_for_edit')->count(),
            'resource_total' => $resources->count(),
            'edit_active' => $editJobs->whereIn('status', ['assigned', 'editing', 'submitted'])->count(),
            'edit_total' => $editJobs->count(),
        ];

        // Build Alpine.js resource data (for auto-fill in edit job form)
        $resourcesForAlpine = $resources->where('status', 'ready_for_edit')
            ->map(fn($r) => [
                'id' => $r->id,
                'title' => $r->title ?? '',
                'footage_code' => $r->footage_code,
                'edit_job_title' => $this->buildDefaultEditJobTitle($r),
            ])
            ->keyBy('id')
            ->toArray();

        // Build employee data for Alpine.js (pricing group selection)
        $freelanceEmployeeIds = $employees->whereIn('payroll_mode', ['freelance_layer', 'freelance_fixed'])->pluck('id');
        $layerRatesByEmployee = LayerRateRule::where('is_active', true)
            ->whereIn('employee_id', $freelanceEmployeeIds)
            ->orderBy('layer_from')
            ->get()
            ->groupBy('employee_id');

        $employeesForAlpine = $employees->mapWithKeys(fn($emp) => [
            $emp->id => [
                'id' => $emp->id,
                'payroll_mode' => $emp->payroll_mode,
                'layer_rates' => ($layerRatesByEmployee[$emp->id] ?? collect())->map(fn($r) => [
                    'label' => 'L' . $r->layer_from . '-' . $r->layer_to,
                    'layer_from' => (int) $r->layer_from,
                    'layer_to' => (int) $r->layer_to,
                    'rate' => (float) $r->rate_per_minute,
                ])->values()->toArray(),
            ],
        ])->toArray();
        $jobStages = \App\Models\JobStage::orderBy('sort_order')->get();

        return view('work.index', compact('tab', 'recordings', 'resources', 'editJobs', 'employees', 'youtubers', 'summary', 'recordingStatusLogs', 'resourcesForAlpine', 'employeesForAlpine', 'jobStages'));
    }

    // === Recording Jobs ===

    public function storeRecording(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'game_type' => 'nullable|in:ผี,FUNNY,SIMULATOR,MOBA,RPG,FPS,PUZZLE,__custom__',
            'game_type_custom' => 'nullable|string|max:100|required_if:game_type,__custom__',
            'game' => 'nullable|string|max:255',
            'map' => 'nullable|string|max:255',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'planned_duration_hms' => ['nullable', 'regex:/^\d{1,2}:\d{2}(?::\d{2})?$/'],
            'resource_title' => 'nullable|string|max:255',
            'planned_duration_minutes' => 'nullable|integer|min:1',
            'priority' => 'in:low,normal,high,urgent',
            'notes' => 'nullable|string|max:1000',
            'assignees' => 'nullable|array',
            'assignees.*.employee_id' => 'required|exists:employees,id',
            'assignees.*.role' => 'required|string|max:50',
        ]);

        $normalizedGameType = $validated['game_type'] ?? null;
        if ($normalizedGameType === '__custom__') {
            $normalizedGameType = trim((string) ($validated['game_type_custom'] ?? ''));
        }
        $validated['game_type'] = $normalizedGameType !== '' ? $normalizedGameType : null;

        if (!empty($validated['planned_duration_hms'])) {
            $plannedDurationMinutes = DurationInput::minutesFromHms($validated['planned_duration_hms']);

            if ($plannedDurationMinutes === null || $plannedDurationMinutes <= 0) {
                return back()->withErrors([
                    'planned_duration_hms' => 'รูปแบบระยะเวลาต้องเป็น HH:MM หรือ HH:MM:SS',
                ])->withInput();
            }

            $validated['planned_duration_minutes'] = max(1, (int) round($plannedDurationMinutes));
        }

        $resourceTitleOverride = trim((string) ($validated['resource_title'] ?? ''));

        unset($validated['game_type_custom']);
        unset($validated['resource_title']);
        unset($validated['planned_duration_hms']);

        $job = DB::transaction(function () use ($validated, $resourceTitleOverride) {
            $job = RecordingJob::create(array_merge($validated, ['created_by' => auth()->id()]));

            if (!empty($validated['assignees'])) {
                foreach ($validated['assignees'] as $a) {
                    $job->assignees()->create($a);
                }
            }

            $footageCode = $this->generateFootageCode($job);
            $resourceTitle = $resourceTitleOverride !== ''
                ? $resourceTitleOverride
                : $this->buildDefaultResourceTitle($job);

            $resource = MediaResource::create([
                'recording_job_id' => $job->id,
                'footage_code' => $footageCode,
                'title' => $resourceTitle,
                'status' => 'ready_for_edit',
                'notes' => 'Auto-created from recording job',
            ]);

            AuditLogService::logCreated($resource, 'Auto-created resource from recording job: ' . $job->title);

            return $job;
        });

        AuditLogService::logCreated($job, 'Recording job created: ' . $job->title);

        $redirect = $request->input('_redirect');
        return ($redirect ? redirect($redirect) : back())->with('success', 'สร้างคิวถ่ายและ Resource อัตโนมัติสำเร็จ');
    }

    private function buildDefaultResourceTitle(RecordingJob $job, ?Carbon $referenceDate = null): string
    {
        $baseDate = $referenceDate ?? ($job->scheduled_date ? Carbon::parse($job->scheduled_date) : now());
        $datePart = $baseDate->format('d/m/Y');
        return trim($job->title . ' | RAW | ' . $datePart);
    }

    private function categoryToCode(?string $category): string
    {
        $normalized = strtoupper(trim((string) $category));

        $map = [
            'FUNNY' => 'F',
            'MOBA' => 'M',
            'RPG' => 'R',
            'FPS' => 'P',
            'PUZZLE' => 'Z',
            'SIMULATOR' => 'S',
            'ผี' => 'H',
            'HORROR' => 'H',
        ];

        return $map[$normalized] ?? 'X';
    }

    private function generateShortFootageCode(string $categoryCode, Carbon $date): string
    {
        $datePart = $date->format('ymd');
        $prefix = strtoupper($categoryCode) . $datePart;

        $latestCode = MediaResource::where('footage_code', 'like', $prefix . '%')
            ->orderByDesc('footage_code')
            ->value('footage_code');

        $seq = 1;
        if ($latestCode && preg_match('/^(?:[A-Z])(\d{6})(\d{2})$/', $latestCode, $matches)) {
            $seq = ((int) $matches[2]) + 1;
        }

        if ($seq > 99) {
            $seq = 99;
        }

        $code = sprintf('%s%02d', $prefix, $seq);

        while (MediaResource::where('footage_code', $code)->exists()) {
            $seq++;
            if ($seq > 99) {
                throw new \RuntimeException('Footage code sequence exceeded for category/date key');
            }
            $code = sprintf('%s%02d', $prefix, $seq);
        }

        return $code;
    }

    private function buildDefaultEditJobTitle(MediaResource $resource): string
    {
        $recordingTitle = $resource->recordingJob?->title;
        $baseTitle = $recordingTitle ?: ($resource->title ?: $resource->footage_code);

        $datePart = now()->format('d/m/Y');
        if ($resource->title && preg_match('/\|\s*RAW\s*\|\s*(\d{2}\/\d{2}\/\d{4})$/', $resource->title, $matches)) {
            $datePart = $matches[1];
        }

        return trim($baseTitle . ' | ' . $datePart);
    }

    /**
     * Auto-create a WorkLog entry when a freelance employee's EditJob is marked as done.
     */
    private function autoCreateWorkLogFromEditJob(EditJob $editJob): void
    {
        if (!$editJob->assigned_to || !$editJob->pricing_group) {
            return;
        }

        $employee = Employee::find($editJob->assigned_to);
        if (!$employee || !in_array($employee->payroll_mode, ['freelance_layer', 'freelance_fixed'], true)) {
            return;
        }

        // If a work log already exists for this edit job, delete it first to recreate with new values
        $existing = WorkLog::where('edit_job_id', $editJob->id)->first();
        if ($existing) {
            \App\Services\AuditLogService::logDeleted($existing, 'System deleted old work log to sync new info from edit job #' . $editJob->id);
            $existing->delete();
        }

        $finishedDate = $editJob->finished_date ?? now();
        $month = (int) $finishedDate->format('m');
        $year = (int) $finishedDate->format('Y');

        if ($employee->payroll_mode === 'freelance_layer') {
            $this->createLayerWorkLogFromEditJob($editJob, $employee, $month, $year, $finishedDate);
        } elseif ($employee->payroll_mode === 'freelance_fixed') {
            $this->createFixedWorkLogFromEditJob($editJob, $employee, $month, $year, $finishedDate);
        }
    }

    private function createLayerWorkLogFromEditJob(EditJob $editJob, Employee $employee, int $month, int $year, $finishedDate): void
    {
        $durationSeconds = $editJob->output_duration_seconds ?? 0;
        $hours = intdiv($durationSeconds, 3600);
        $minutes = intdiv($durationSeconds % 3600, 60);
        $seconds = $durationSeconds % 60;
        $durationMinutes = ($durationSeconds / 60);

        $rate = (float) ($editJob->assigned_rate ?? 0);
        $amount = round($durationMinutes * $rate, 2);

        $pricingMode = $editJob->pricing_group === 'template' ? 'template' : 'custom';

        // Determine layer from template label
        $layer = null;
        if ($editJob->pricing_template_label) {
            $layerRule = LayerRateRule::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->get()
                ->first(function ($r) use ($editJob) {
                    return 'L' . $r->layer_from . '-' . $r->layer_to === $editJob->pricing_template_label;
                });
            $layer = $layerRule?->layer_from;
        }

        $workLog = WorkLog::create([
            'employee_id' => $employee->id,
            'month' => $month,
            'year' => $year,
            'log_date' => $finishedDate,
            'work_type' => 'layer',
            'layer' => $layer,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'quantity' => 0,
            'rate' => $rate,
            'amount' => $amount,
            'pricing_mode' => $pricingMode,
            'custom_rate' => $pricingMode === 'custom' ? $rate : null,
            'pricing_template_label' => $editJob->pricing_template_label,
            'sort_order' => 0,
            'notes' => 'Auto: ' . $editJob->title,
            'entry_type' => 'income',
            'source_flag' => 'auto',
            'edit_job_id' => $editJob->id,
        ]);

        AuditLogService::logCreated($workLog, 'Auto-created work log from edit job #' . $editJob->id);
    }

    private function createFixedWorkLogFromEditJob(EditJob $editJob, Employee $employee, int $month, int $year, $finishedDate): void
    {
        $quantity = $editJob->assigned_quantity ?? 1;
        $fixedRate = (float) ($editJob->assigned_fixed_rate ?? 0);
        $amount = round($quantity * $fixedRate, 2);

        $workLog = WorkLog::create([
            'employee_id' => $employee->id,
            'month' => $month,
            'year' => $year,
            'log_date' => $finishedDate,
            'work_type' => $editJob->title,
            'layer' => null,
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0,
            'quantity' => $quantity,
            'rate' => $fixedRate,
            'amount' => $amount,
            'pricing_mode' => 'template',
            'custom_rate' => null,
            'pricing_template_label' => null,
            'sort_order' => 0,
            'notes' => 'Auto: ' . $editJob->title,
            'entry_type' => 'income',
            'source_flag' => 'auto',
            'edit_job_id' => $editJob->id,
        ]);

        AuditLogService::logCreated($workLog, 'Auto-created fixed work log from edit job #' . $editJob->id);
    }

    private function parseDurationToSeconds(?string $duration): ?int
    {
        return DurationInput::secondsFromHms($duration);
    }

    private function generateFootageCode(RecordingJob $job): string
    {
        $date = $job->scheduled_date ? Carbon::parse($job->scheduled_date) : now();
        $categoryCode = $this->categoryToCode($job->game_type);
        return $this->generateShortFootageCode($categoryCode, $date);
    }

    public function updateRecordingStatus(RecordingJob $recording, Request $request)
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::exists('job_stages', 'code')->where('type', 'recording')],
            'footage_count' => 'nullable|integer|min:1|required_if:status,shot',
            'longest_footage_hms' => ['nullable', 'regex:/^\d{1,2}:\d{2}:\d{2}$/', 'required_if:status,shot'],
        ]);

        $longestFootageSeconds = $this->parseDurationToSeconds($validated['longest_footage_hms'] ?? null);
        if (($validated['status'] ?? null) === 'shot' && $longestFootageSeconds === null) {
            return back()->withErrors([
                'longest_footage_hms' => 'รูปแบบระยะเวลาต้องเป็น HH:MM:SS และนาที/วินาทีต้องไม่เกิน 59',
            ])->withInput();
        }

        $old = $recording->status;
        $oldFootageCount = $recording->footage_count;
        $oldLongestFootageSeconds = $recording->longest_footage_seconds;
        $payload = ['status' => $validated['status']];
        if (($validated['status'] ?? null) === 'shot') {
            $payload['footage_count'] = (int) ($validated['footage_count'] ?? 0);
            $payload['longest_footage_seconds'] = $longestFootageSeconds;
        }
        $recording->update($payload);
        AuditLogService::log($recording, 'updated', 'status', $old, $validated['status'], 'Status changed');

        if (($validated['status'] ?? null) === 'shot') {
            if ((int) ($oldFootageCount ?? 0) !== (int) ($payload['footage_count'] ?? 0)) {
                AuditLogService::log($recording, 'updated', 'footage_count', (string) $oldFootageCount, (string) $payload['footage_count'], 'Footage count set on shot');
            }
            if ((int) ($oldLongestFootageSeconds ?? 0) !== (int) ($payload['longest_footage_seconds'] ?? 0)) {
                AuditLogService::log($recording, 'updated', 'longest_footage_seconds', (string) $oldLongestFootageSeconds, (string) $payload['longest_footage_seconds'], 'Longest footage duration set on shot');
            }
        }

        // Auto-promote linked resources when recording is marked as shot
        if (($validated['status'] ?? null) === 'shot') {
            $shotAt = now();
            $promoted = $recording->mediaResources()
                ->where('status', '!=', 'archived')
                ->get();

            if ($promoted->isEmpty()) {
                // No linked resource yet — create one now
                $footageCode = $this->generateFootageCode($recording);
                $resource = MediaResource::create([
                    'recording_job_id' => $recording->id,
                    'footage_code'     => $footageCode,
                    'title'            => $this->buildDefaultResourceTitle($recording, $shotAt),
                    'footage_count'    => $recording->footage_count,
                    'raw_length_seconds' => $longestFootageSeconds,
                    'status'           => 'ready_for_edit',
                    'notes'            => 'Auto-created when recording marked as shot',
                ]);
                AuditLogService::logCreated($resource, 'Auto-created resource on shot: ' . $recording->title);
            } else {
                foreach ($promoted as $resource) {
                    $oldStatus = $resource->status;
                    $resource->update([
                        'status' => 'ready_for_edit',
                        'title' => $this->buildDefaultResourceTitle($recording, $shotAt),
                        'footage_count' => $recording->footage_count,
                        'raw_length_seconds' => $longestFootageSeconds,
                    ]);
                    AuditLogService::log($resource, 'updated', 'status', $oldStatus, 'ready_for_edit', 'Auto-promoted: recording marked as shot');
                }
            }

            return redirect()->route('work.index', ['tab' => 'resource'])->with('success', 'ถ่ายเสร็จแล้ว — Resource พร้อมสำหรับตัดต่อ');
        }

        return back()->with('success', 'อัปเดตสถานะสำเร็จ');
    }

    public function updateRecordingSchedule(RecordingJob $recording, Request $request)
    {
        $validated = $request->validate([
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable|date_format:H:i',
        ]);

        $oldDate = optional($recording->scheduled_date)->format('Y-m-d');
        $oldTime = $recording->scheduled_time ? substr((string) $recording->scheduled_time, 0, 5) : null;

        $recording->update([
            'scheduled_date' => $validated['scheduled_date'],
            'scheduled_time' => $validated['scheduled_time'] ?? null,
        ]);

        if ($oldDate !== $validated['scheduled_date']) {
            AuditLogService::log($recording, 'updated', 'scheduled_date', $oldDate, $validated['scheduled_date'], 'Schedule date changed');
        }

        $newTime = $validated['scheduled_time'] ?? null;
        if ($oldTime !== $newTime) {
            AuditLogService::log($recording, 'updated', 'scheduled_time', $oldTime, $newTime, 'Schedule time changed');
        }

        return back()->with('success', 'อัปเดตวันเวลา schedule สำเร็จ');
    }

    public function deleteRecording(RecordingJob $recording)
    {
        AuditLogService::logDeleted($recording, 'Recording job deleted: ' . $recording->title);
        $recording->delete();
        return back()->with('success', 'ลบคิวถ่ายสำเร็จ');
    }

    // === Media Resources ===

    public function assignToRecording(RecordingJob $recording, Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'role' => 'required|string|max:50',
        ]);

        if ($recording->assignees()->where('employee_id', $validated['employee_id'])->exists()) {
            return back()->with('error', 'พนักงานคนนี้ถูก assign ไปแล้ว');
        }

        $recording->assignees()->create($validated);
        return back()->with('success', 'Assign ทีมถ่ายสำเร็จ');
    }

    public function removeRecordingAssignee(RecordingJobAssignee $assignee)
    {
        $assignee->delete();
        return back()->with('success', 'ลบออกจากทีมสำเร็จ');
    }

    public function storeResource(Request $request)
    {
        $validated = $request->validate([
            'recording_job_id' => 'nullable|exists:recording_jobs,id',
            'game_category' => 'nullable|string|max:100|required_without:recording_job_id',
            'title' => 'nullable|string|max:255',
            'footage_count' => 'nullable|integer|min:1',
            'duration_hms' => ['nullable', 'regex:/^\d{1,2}:\d{2}:\d{2}$/'],
            'notes' => 'nullable|string|max:1000',
        ]);

        $recording = null;
        if (!empty($validated['recording_job_id'])) {
            $recording = RecordingJob::find($validated['recording_job_id']);
        }

        $categorySource = $recording?->game_type ?? ($validated['game_category'] ?? null);
        $categoryCode = $this->categoryToCode($categorySource);
        $referenceDate = $recording?->scheduled_date ? Carbon::parse($recording->scheduled_date) : now();
        $footageCode = $this->generateShortFootageCode($categoryCode, $referenceDate);

        $title = $validated['title'] ?? null;
        if (($title === null || trim($title) === '') && $recording) {
            $title = $this->buildDefaultResourceTitle($recording, $referenceDate);
        }

        $durationSeconds = $this->parseDurationToSeconds($validated['duration_hms'] ?? null);

        $resource = MediaResource::create([
            'recording_job_id' => $validated['recording_job_id'] ?? null,
            'footage_code' => $footageCode,
            'title' => $title,
            'footage_count' => $recording?->footage_count ?? ($validated['footage_count'] ?? null),
            'raw_length_seconds' => $durationSeconds,
            'usable_length_seconds' => null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'ready_for_edit',
        ]);

        AuditLogService::logCreated($resource, 'Media resource created: ' . $resource->footage_code);
        return back()->with('success', 'เพิ่ม Resource สำเร็จ');
    }

    public function updateResourceStatus(MediaResource $resource, Request $request)
    {
        $request->validate(['status' => 'required|in:raw,uploaded,ready_for_edit,in_use,archived']);
        $old = $resource->status;
        $resource->update(['status' => $request->status]);
        AuditLogService::log($resource, 'updated', 'status', $old, $request->status, 'Status changed');
        return back()->with('success', 'อัปเดตสถานะ Resource สำเร็จ');
    }

    public function deleteResource(MediaResource $resource)
    {
        AuditLogService::logDeleted($resource, 'Media resource deleted: ' . $resource->footage_code);
        $resource->delete();
        return back()->with('success', 'ลบ Resource สำเร็จ');
    }

    // === Edit Jobs ===

    public function storeEditJob(Request $request)
    {
        $validated = $request->validate([
            'media_resource_id' => 'nullable|exists:media_resources,id',
            'assigned_to' => 'nullable|exists:employees,id',
            'title' => 'required|string|max:255',
            'priority' => 'in:low,normal,high,urgent',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            // Freelance pricing fields
            'pricing_group' => 'nullable|string|in:template,isolated,fixed',
            'pricing_template_label' => 'nullable|string|max:50',
            'assigned_rate' => 'nullable|numeric|min:0',
            'assigned_quantity' => 'nullable|integer|min:1',
            'assigned_fixed_rate' => 'nullable|numeric|min:0',
        ]);

        $status = $validated['media_resource_id'] ? ($validated['assigned_to'] ? 'assigned' : 'pending_resource') : 'pending_resource';
        $editJob = EditJob::create(array_merge($validated, ['status' => $status, 'created_by' => auth()->id()]));

        // Update resource status if linked
        if ($editJob->media_resource_id) {
            $editJob->mediaResource?->update(['status' => 'in_use']);
        }

        AuditLogService::logCreated($editJob, 'Edit job created: ' . $editJob->title);

        $redirect = $request->input('_redirect');
        return ($redirect ? redirect($redirect) : back())->with('success', 'สร้างงานตัดต่อสำเร็จ');
    }

    public function updateEditJobStatus(EditJob $editJob, Request $request)
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::exists('job_stages', 'code')->where('type', 'edit')],
            'output_duration_hms' => ['nullable', 'regex:/^\d{1,2}:\d{2}(?::\d{2})?$/']
        ]);
        
        $old = $editJob->status;
        $editJob->update(['status' => $validated['status']]);

        if ($validated['status'] === 'done') {
            $updateData = ['finished_date' => now()];
            
            if (!empty($validated['output_duration_hms'])) {
                $durationSeconds = $this->parseDurationToSeconds($validated['output_duration_hms']);
                if ($durationSeconds !== null) {
                    $updateData['output_duration_seconds'] = $durationSeconds;
                    $editJob->output_duration_seconds = $durationSeconds; // Ensure instance is updated for autoCreateWorkLog
                }
            }
            
            $editJob->update($updateData);
            $editJob->mediaResource?->update(['status' => 'archived']);

            // Auto-create WorkLog for freelance employees
            $this->autoCreateWorkLogFromEditJob($editJob);
        } elseif ($validated['status'] !== 'pending_resource') {
            $editJob->mediaResource?->update(['status' => 'in_use']);
        }

        AuditLogService::log($editJob, 'updated', 'status', $old, $request->status, 'Status changed');
        return back()->with('success', 'อัปเดตสถานะงานตัดต่อสำเร็จ');
    }

    public function deleteEditJob(EditJob $editJob)
    {
        AuditLogService::logDeleted($editJob, 'Edit job deleted: ' . $editJob->title);
        $editJob->delete();
        return back()->with('success', 'ลบงานตัดต่อสำเร็จ');
    }

    // === Approved Output ===

    public function storeApprovedOutput(Request $request)
    {
        $validated = $request->validate([
            'edit_job_id' => 'required|exists:edit_jobs,id',
            'title' => 'required|string|max:255',
            'platform' => 'nullable|string|max:100',
            'publish_date' => 'nullable|date',
            'final_duration_seconds' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $output = ApprovedWorkOutput::create(array_merge($validated, ['approved_by' => auth()->id()]));

        // Mark edit job as approved/done
        $editJob = EditJob::find($validated['edit_job_id']);
        if ($editJob && $editJob->status !== 'done') {
            $editJob->update(['status' => 'approved']);
        }

        AuditLogService::logCreated($output, 'Approved output: ' . $output->title);
        return back()->with('success', 'บันทึกผลงานสำเร็จ');
    }
}
