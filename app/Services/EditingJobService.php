<?php

namespace App\Services;

use App\Models\DeadlineNotification;
use App\Models\EditingJob;
use App\Models\Employee;
use App\Models\JobModification;
use App\Models\JobReassignment;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EditingJobService
{
    // ─── Valid status transitions ────────────────────────────────────

    private const TRANSITIONS = [
        'assigned'     => 'in_progress',
        'in_progress'  => 'review_ready',
        'review_ready' => 'final',
    ];

    // ─── Employee Code Generation ────────────────────────────────────

    public function generateEmployeeCode(string $prefix): string
    {
        $datePart = now()->format('ymd');
        $pattern  = "{$prefix}-{$datePart}-%";

        $last = Employee::where('employee_code', 'like', $pattern)
            ->orderByDesc('employee_code')
            ->value('employee_code');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return sprintf('%s-%s-%03d', $prefix, $datePart, $seq);
    }

    // ─── Job Creation ────────────────────────────────────────────────

    public function createJob(array $data): EditingJob
    {
        return DB::transaction(function () use ($data) {
            return $this->performCreateJob($data);
        });
    }

    private function performCreateJob(array $data): EditingJob
    {
        $job = EditingJob::create([
            'job_name'      => $data['job_name'],
            'game_id'       => $data['game_id'],
            'youtuber_id'   => $data['youtuber_id'] ?? null,
            'game_link'     => $data['game_link'] ?? null,
            'assigned_to'   => $data['assigned_to'],
            'assigned_by'   => $data['assigned_by'],
            'assigned_at'   => now(),
            'deadline_days' => $data['deadline_days'],
            'notes'         => $data['notes'] ?? null,
            'status'        => 'assigned',
        ]);

        return $job->load(['game', 'assignee', 'assigner', 'youtuber']);
    }

    public function updateJob(EditingJob $job, array $data): EditingJob
    {
        $old = $job->toArray();

        $job->update([
            'job_name'      => $data['job_name'],
            'game_id'       => $data['game_id'],
            'youtuber_id'   => $data['youtuber_id'] ?? $job->youtuber_id,
            'game_link'     => $data['game_link'] ?? null,
            'assigned_to'   => $data['assigned_to'],
            'deadline_days' => $data['deadline_days'],
            'deadline_date' => $data['deadline_date'] ?? null,
            'layer_count'   => $data['layer_count'] ?? $job->layer_count,
            'video_duration_minutes' => $data['video_duration_minutes'] ?? $job->video_duration_minutes,
            'video_duration_seconds' => $data['video_duration_seconds'] ?? $job->video_duration_seconds,
            'notes'         => $data['notes'] ?? null,
        ]);

        \App\Services\AuditLogService::logUpdated(
            $job->fresh(),
            collect($old)->only(['job_name', 'game_id', 'assigned_to', 'deadline_days', 'notes'])->toArray(),
            'Updated editing job details'
        );

        return $job->load(['game', 'assignee', 'assigner']);
    }

    // ─── Status Transitions ──────────────────────────────────────────

    public function startJob(int $jobId, int $editorId): EditingJob
    {
        $job = EditingJob::findOrFail($jobId);

        if ($job->assigned_to !== $editorId) {
            throw new \DomainException('Only the assigned editor can start this job.');
        }

        $this->assertTransition($job, 'in_progress');

        $startedAt    = now();
        $deadlineDate = $startedAt->copy()->addDays($job->deadline_days)->toDateString();

        $job->update([
            'status'        => 'in_progress',
            'started_at'    => $startedAt,
            'deadline_date' => $deadlineDate,
        ]);

        return $job->fresh(['game', 'assignee']);
    }

    public function markReviewReady(int $jobId, int $editorId): EditingJob
    {
        $job = EditingJob::findOrFail($jobId);

        if ($job->assigned_to !== $editorId) {
            throw new \DomainException('Only the assigned editor can mark this job as review ready.');
        }

        $this->assertTransition($job, 'review_ready');

        $job->update([
            'status'          => 'review_ready',
            'review_ready_at' => now(),
        ]);

        return $job->fresh(['game', 'assignee']);
    }

    public function finalizeJob(
        int $jobId,
        int $editorId,
        ?string $finalizedAt = null,
        ?int $durationMinutes = null,
        ?int $durationSeconds = null,
        ?int $layerCount = null,
        ?string $pricingMode = null,
        ?float $customRate = null,
        ?float $fixAmount = null
    ): array {
        $job = EditingJob::findOrFail($jobId);

        $this->assertTransition($job, 'final');

        $finalizedDate = $finalizedAt ? Carbon::parse($finalizedAt) : now();

        $updateData = [
            'status'       => 'final',
            'finalized_at' => $finalizedDate,
        ];

        if ($durationMinutes !== null) {
            $updateData['video_duration_minutes'] = $durationMinutes;
        }
        if ($durationSeconds !== null) {
            $updateData['video_duration_seconds'] = $durationSeconds;
        }
        if ($layerCount !== null) {
            $updateData['layer_count'] = $layerCount;
        }

        $job->update($updateData);

        $job->load(['game', 'assignee']);

        $this->syncWorkLogFromJob($job, $pricingMode, $customRate, $fixAmount);

        return [
            'job'         => $job,
            'performance' => $this->calculateJobPerformance($job),
        ];
    }

    public function directFinalizeJob(
        int $jobId,
        ?string $reviewReadyAt = null,
        ?string $finalizedAt = null,
        ?int $durationMinutes = null,
        ?int $durationSeconds = null,
        ?int $layerCount = null,
        ?string $pricingMode = null,
        ?float $customRate = null,
        ?float $fixAmount = null
    ): EditingJob {
        $job = EditingJob::findOrFail($jobId);

        if ($job->status === 'final') {
            throw new \DomainException('งานนี้ปิดไปแล้ว');
        }

        $updateData = [
            'status'          => 'final',
            'review_ready_at' => $reviewReadyAt ? Carbon::parse($reviewReadyAt) : ($job->review_ready_at ?? now()),
            'finalized_at'    => $finalizedAt ? Carbon::parse($finalizedAt) : now(),
        ];

        if ($durationMinutes !== null) {
            $updateData['video_duration_minutes'] = $durationMinutes;
        }
        if ($durationSeconds !== null) {
            $updateData['video_duration_seconds'] = $durationSeconds;
        }
        if ($layerCount !== null) {
            $updateData['layer_count'] = $layerCount;
        }
        if ($job->started_at === null) {
            $updateData['started_at'] = $updateData['review_ready_at'];
        }

        $job->update($updateData);
        $job->load(['game', 'assignee']);

        $this->syncWorkLogFromJob($job, $pricingMode, $customRate, $fixAmount);

        return $job;
    }

    // ─── Work Log linkage ────────────────────────────────────────────

    private function syncWorkLogFromJob(EditingJob $job, ?string $pricingMode = null, ?float $customRate = null, ?float $fixAmount = null): void
    {
        if ($job->status !== 'final') {
            return;
        }

        $employee = $job->assignee;
        if (!$employee) {
            return;
        }

        // Idempotent: one WorkLog per finalized job
        if (WorkLog::where('editing_job_id', $job->id)->exists()) {
            return;
        }

        if ($employee->payroll_mode !== 'freelance_layer') {
            return;
        }

        $finalizedAt = $job->finalized_at ?? now();
        $logDate = Carbon::parse($finalizedAt);
        $mins = (int) ($job->video_duration_minutes ?? 0);
        $secs = (int) ($job->video_duration_seconds ?? 0);
        $defaultFlat = (float) ($employee->fixed_rate_per_clip ?? 0);

        $data = [
            'employee_id'    => $employee->id,
            'editing_job_id' => $job->id,
            'log_date'       => $logDate->toDateString(),
            'month'          => $logDate->month,
            'year'           => $logDate->year,
            'entry_type'     => 'auto',
            'source_flag'    => 'job_finalize',
            'notes'          => "งาน #{$job->id}: {$job->job_name}",
            'work_type'      => 'editing',
            'layer'          => (int) ($job->layer_count ?? 1),
            'hours'          => intdiv($mins, 60),
            'minutes'        => $mins % 60,
            'seconds'        => $secs,
            'quantity'       => 1,
            'rate'           => 0,
            'amount'         => 0,
        ];

        if ($pricingMode === 'custom') {
            $data['pricing_mode'] = 'custom';
            $data['custom_rate']  = $fixAmount ?? 0;
            $data['rate']         = $fixAmount ?? 0;
            $data['amount']       = $fixAmount ?? 0;
        } elseif ($pricingMode === 'custom_rate_per_min') {
            $data['pricing_mode'] = 'custom_rate_per_min';
            $data['custom_rate']  = $customRate ?? 0;
            $data['rate']         = $customRate ?? 0;
            $durationMinutes      = $data['hours'] * 60 + $data['minutes'] + ($data['seconds'] / 60);
            $data['amount']       = $durationMinutes * ($customRate ?? 0);
        } elseif ($defaultFlat > 0 && !$pricingMode) {
            // Fallback to default flat if set and no override provided
            $data['pricing_mode'] = 'custom';
            $data['custom_rate']  = $defaultFlat;
            $data['rate']         = $defaultFlat;
            $data['amount']       = $defaultFlat;
        } else {
            // Layer mode
            $data['pricing_mode'] = 'layer';
        }

        WorkLog::create($data);

        // Immediately resolve the rate and amount based on Layer Rules
        app(\App\Services\Payroll\PayrollCalculationService::class)->syncWorkLogAmounts($employee, $logDate->month, $logDate->year);
    }

    // ─── Admin Actions ───────────────────────────────────────────────

    public function reassignJob(int $jobId, int $newAssigneeId, int $adminId, ?string $reason = null): EditingJob
    {
        $job = EditingJob::findOrFail($jobId);

        if ($job->status === 'final') {
            throw new \DomainException('Cannot reassign a finalized job.');
        }

        JobReassignment::create([
            'editing_job_id' => $job->id,
            'old_assignee'   => $job->assigned_to,
            'new_assignee'   => $newAssigneeId,
            'reassigned_by'  => $adminId,
            'reason'         => $reason,
            'reassigned_at'  => now(),
        ]);

        $job->update(['assigned_to' => $newAssigneeId]);

        return $job->fresh(['game', 'assignee']);
    }

    public function updateJobDetails(int $jobId, int $adminId, array $updates): EditingJob
    {
        $job = EditingJob::findOrFail($jobId);

        if ($job->status === 'final') {
            throw new \DomainException('Cannot modify a finalized job.');
        }

        $allowedFields = ['job_name', 'game_id', 'game_link', 'deadline_days', 'notes'];
        $filtered      = collect($updates)->only($allowedFields)->toArray();

        foreach ($filtered as $field => $newValue) {
            $oldValue = $job->getAttribute($field);
            if ((string) $oldValue !== (string) $newValue) {
                JobModification::create([
                    'editing_job_id' => $job->id,
                    'modified_by'    => $adminId,
                    'field_name'     => $field,
                    'old_value'      => (string) $oldValue,
                    'new_value'      => (string) $newValue,
                    'modified_at'    => now(),
                ]);
            }
        }

        $job->update($filtered);

        // Recalculate deadline_date if deadline_days changed while in_progress
        if ($job->wasChanged('deadline_days') && $job->status === 'in_progress' && $job->started_at) {
            $job->update([
                'deadline_date' => $job->started_at->copy()
                    ->addDays($job->deadline_days)
                    ->toDateString(),
            ]);
        }

        return $job->fresh(['game', 'assignee']);
    }

    // ─── Soft Delete ─────────────────────────────────────────────────

    public function deleteJob(int $jobId): void
    {
        DB::transaction(function () use ($jobId) {
            $job = EditingJob::findOrFail($jobId);
            $job->update(['is_deleted' => true]);

            // If it had a synced work log, remove it too so it doesn't stay in payroll
            WorkLog::where('editing_job_id', $jobId)->delete();
        });
    }

    // ─── Performance Metrics ─────────────────────────────────────────

    public function calculateJobPerformance(EditingJob $job): ?array
    {
        if (!$job->started_at || !$job->review_ready_at) {
            return null;
        }

        $workDurationDays = (int) $job->started_at->diffInDays($job->review_ready_at);
        $deadlineDiff     = (int) $job->review_ready_at->startOfDay()->diffInDays($job->deadline_date, false);

        if ($deadlineDiff > 0) {
            $compliance    = 'early';
            $daysDifference = $deadlineDiff;
        } elseif ($deadlineDiff === 0) {
            $compliance    = 'on_time';
            $daysDifference = 0;
        } else {
            $compliance    = 'late';
            $daysDifference = abs($deadlineDiff);
        }

        $totalTurnaround = null;
        if ($job->finalized_at) {
            $totalTurnaround = (int) $job->assigned_at->diffInDays($job->finalized_at);
        }

        return [
            'work_duration_days'    => $workDurationDays,
            'deadline_compliance'   => $compliance,
            'days_difference'       => $daysDifference,
            'total_turnaround_days' => $totalTurnaround,
            'deadline_date'         => $job->deadline_date?->toDateString(),
            'completed_date'        => $job->review_ready_at?->toDateString(),
        ];
    }

    public function getEmployeePerformance(int $employeeId, int $year, int $month): array
    {
        $jobs = EditingJob::where('assigned_to', $employeeId)
            ->where('is_deleted', false)
            ->whereYear('review_ready_at', $year)
            ->whereMonth('review_ready_at', $month)
            ->whereNotNull('review_ready_at')
            ->whereNotNull('started_at')
            ->get();

        $early   = 0;
        $onTime  = 0;
        $late    = 0;

        foreach ($jobs as $job) {
            $metrics = $this->calculateJobPerformance($job);
            if ($metrics) {
                match ($metrics['deadline_compliance']) {
                    'early'   => $early++,
                    'on_time' => $onTime++,
                    default   => $late++,
                };
            }
        }

        $total = $early + $onTime + $late;
        $rate  = $total > 0 ? round(($early + $onTime) / $total * 100, 1) : 0.0;

        return [
            'total_jobs'                => $total,
            'early'                     => $early,
            'on_time'                   => $onTime,
            'late'                      => $late,
            'deadline_compliance_rate'  => $rate,
        ];
    }

    // ─── Query Helpers ───────────────────────────────────────────────

    public function getJobsForEmployee(int $employeeId, ?string $status = null): Collection
    {
        $query = EditingJob::with(['game', 'assignee'])
            ->where('assigned_to', $employeeId)
            ->where('is_deleted', false);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('assigned_at')->get();
    }

    public function getOverdueJobs(): Collection
    {
        return EditingJob::with(['game', 'assignee'])
            ->where('is_deleted', false)
            ->where('status', 'in_progress')
            ->whereNotNull('deadline_date')
            ->where('deadline_date', '<', now()->toDateString())
            ->orderBy('deadline_date')
            ->get();
    }

    // ─── Deadline Notifications ──────────────────────────────────────

    public function createDeadlineNotification(int $jobId, int $employeeId, string $type): ?DeadlineNotification
    {
        $job = EditingJob::findOrFail($jobId);

        // Don't notify if job is already completed
        if (in_array($job->status, ['review_ready', 'final'])) {
            return null;
        }

        return DeadlineNotification::create([
            'editing_job_id'    => $jobId,
            'employee_id'       => $employeeId,
            'notification_type' => $type,
            'is_read'           => false,
        ]);
    }

    public function markNotificationRead(int $notificationId): void
    {
        DeadlineNotification::findOrFail($notificationId)->update(['is_read' => true]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function assertTransition(EditingJob $job, string $targetStatus): void
    {
        $expected = self::TRANSITIONS[$job->status] ?? null;

        if ($expected !== $targetStatus) {
            throw new \DomainException(
                "Invalid transition: cannot move from '{$job->status}' to '{$targetStatus}'."
            );
        }
    }
}
