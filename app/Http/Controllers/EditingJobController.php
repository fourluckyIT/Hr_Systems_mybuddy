<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateEditingJobRequest;
use App\Http\Requests\UpdateEditingJobRequest;
use App\Models\EditingJob;
use App\Services\EditingJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditingJobController extends Controller
{
    public function __construct(
        protected EditingJobService $service
    ) {}

    private function domainAction(\Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/jobs/create
     */
    public function store(CreateEditingJobRequest $request): JsonResponse
    {
        $job = $this->service->createJob($request->validated());

        return response()->json([
            'status' => 'success',
            'job'    => $this->formatJob($job),
        ], 201);
    }

    /**
     * POST /api/jobs/{job}/start
     */
    public function start(Request $request, int $job): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        return $this->domainAction(function () use ($request, $job) {
            $updated = $this->service->startJob($job, (int) $request->employee_id);

            return response()->json([
                'status' => 'success',
                'job'    => $this->formatJob($updated),
            ]);
        });
    }

    /**
     * POST /api/jobs/{job}/mark-ready
     */
    public function markReady(Request $request, int $job): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        return $this->domainAction(function () use ($request, $job) {
            $updated = $this->service->markReviewReady($job, (int) $request->employee_id);

            return response()->json([
                'status' => 'success',
                'job'    => $this->formatJob($updated),
            ]);
        });
    }

    /**
     * POST /api/jobs/{job}/finalize
     */
    public function finalize(Request $request, int $job): JsonResponse
    {
        $request->validate([
            'employee_id'  => ['required', 'integer', 'exists:employees,id'],
            'finalized_at' => ['nullable', 'date'],
        ]);

        return $this->domainAction(function () use ($request, $job) {
            $result = $this->service->finalizeJob(
                $job,
                (int) $request->employee_id,
                $request->finalized_at,
            );

            return response()->json([
                'status'      => 'success',
                'job'         => $this->formatJob($result['job']),
                'performance' => $result['performance'],
            ]);
        });
    }

    /**
     * PUT /api/jobs/{job}/reassign
     */
    public function reassign(Request $request, int $job): JsonResponse
    {
        $request->validate([
            'new_assignee'  => ['required', 'integer', 'exists:employees,id'],
            'reassigned_by' => ['required', 'integer', 'exists:employees,id'],
            'reason'        => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->service->reassignJob(
            $job,
            (int) $request->new_assignee,
            (int) $request->reassigned_by,
            $request->reason,
        );

        return response()->json([
            'status' => 'success',
            'job'    => $this->formatJob($updated),
        ]);
    }

    /**
     * PUT /api/jobs/{job}/update
     */
    public function update(UpdateEditingJobRequest $request, int $job): JsonResponse
    {
        $data = $request->validated();
        $modifiedBy = $data['modified_by'];
        unset($data['modified_by']);

        $updated = $this->service->updateJobDetails($job, $modifiedBy, $data);

        return response()->json([
            'status' => 'success',
            'job'    => $this->formatJob($updated),
        ]);
    }

    /**
     * DELETE /api/jobs/{job}
     */
    public function destroy(int $job): JsonResponse
    {
        $this->service->deleteJob($job);

        return response()->json(['status' => 'deleted']);
    }

    /**
     * GET /api/jobs/{job}
     */
    public function show(int $job): JsonResponse
    {
        $editingJob = EditingJob::with(['game', 'assignee', 'assigner', 'reassignments', 'modifications'])
            ->where('is_deleted', false)
            ->findOrFail($job);

        return response()->json($this->formatJobDetailed($editingJob));
    }

    /**
     * GET /api/jobs?status=in_progress&assigned_to=1
     */
    public function index(Request $request): JsonResponse
    {
        $query = EditingJob::with(['game', 'assignee'])
            ->where('is_deleted', false);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('game_id')) {
            $query->where('game_id', $request->game_id);
        }

        $jobs = $query->orderByDesc('assigned_at')->get();

        return response()->json([
            'total' => $jobs->count(),
            'jobs'  => $jobs->map(fn ($j) => $this->formatJob($j)),
        ]);
    }

    /**
     * GET /api/performance/{employee}?year=2026&month=4
     */
    public function performance(Request $request, int $employee): JsonResponse
    {
        $request->validate([
            'year'  => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $data = $this->service->getEmployeePerformance(
            $employee,
            (int) $request->year,
            (int) $request->month,
        );

        return response()->json($data);
    }

    /**
     * GET /api/jobs/overdue
     */
    public function overdue(): JsonResponse
    {
        $jobs = $this->service->getOverdueJobs();

        return response()->json([
            'total' => $jobs->count(),
            'jobs'  => $jobs->map(fn ($j) => $this->formatJob($j)),
        ]);
    }

    // ─── Formatters ──────────────────────────────────────────────────

    private function formatJob(EditingJob $job): array
    {
        return [
            'job_id'          => $job->id,
            'job_name'        => $job->job_name,
            'game'            => $job->game?->game_name,
            'game_link'       => $job->game_link,
            'assigned_to'     => $job->assigned_to,
            'assignee_name'   => $job->assignee?->full_name,
            'status'          => $job->status,
            'deadline_days'   => $job->deadline_days,
            'deadline_date'   => $job->deadline_date?->toDateString(),
            'started_at'      => $job->started_at?->toIso8601String(),
            'review_ready_at' => $job->review_ready_at?->toIso8601String(),
            'finalized_at'    => $job->finalized_at?->toIso8601String(),
            'is_overdue'      => $job->isOverdue(),
            'notes'           => $job->notes,
        ];
    }

    private function formatJobDetailed(EditingJob $job): array
    {
        $base = $this->formatJob($job);
        $base['assigned_by']   = $job->assigned_by;
        $base['assigner_name'] = $job->assigner?->full_name;
        $base['reassignments'] = $job->reassignments->map(fn ($r) => [
            'old_assignee'  => $r->old_assignee,
            'new_assignee'  => $r->new_assignee,
            'reassigned_by' => $r->reassigned_by,
            'reassigned_at' => $r->reassigned_at?->toIso8601String(),
            'reason'        => $r->reason,
        ]);
        $base['modifications'] = $job->modifications->map(fn ($m) => [
            'field_name'  => $m->field_name,
            'old_value'   => $m->old_value,
            'new_value'   => $m->new_value,
            'modified_by' => $m->modified_by,
            'modified_at' => $m->modified_at?->toIso8601String(),
        ]);

        if ($job->status === 'final' || $job->status === 'review_ready') {
            $base['performance'] = $this->service->calculateJobPerformance($job);
        }

        return $base;
    }
}
