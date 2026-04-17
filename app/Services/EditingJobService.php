<?php

namespace App\Services;

use App\Models\DeadlineNotification;
use App\Models\EditingJob;
use App\Models\Employee;
use App\Models\JobModification;
use App\Models\JobReassignment;
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
            'game_link'     => $data['game_link'] ?? null,
            'assigned_to'   => $data['assigned_to'],
            'assigned_by'   => $data['assigned_by'],
            'assigned_at'   => now(),
            'deadline_days' => $data['deadline_days'],
            'notes'         => $data['notes'] ?? null,
            'status'        => 'assigned',
        ]);

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

    public function finalizeJob(int $jobId, int $editorId, ?string $finalizedAt = null): array
    {
        $job = EditingJob::findOrFail($jobId);

        $this->assertTransition($job, 'final');

        $finalizedDate = $finalizedAt ? Carbon::parse($finalizedAt) : now();

        $job->update([
            'status'       => 'final',
            'finalized_at' => $finalizedDate,
        ]);

        $job->load(['game', 'assignee']);

        return [
            'job'         => $job,
            'performance' => $this->calculateJobPerformance($job),
        ];
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
        $job = EditingJob::findOrFail($jobId);
        $job->update(['is_deleted' => true]);
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
