<?php

namespace App\Services;

use App\Exceptions\IllegalTransitionException;
use App\Models\EditingJob;
use Illuminate\Support\Facades\DB;

/**
 * Fixes BUG-12. Adds cancelled / rejected terminal states and a single
 * source of truth for legal state transitions. Any illegal transition
 * attempted from a controller, import, or job will throw — no more
 * silent mutations through phpMyAdmin.
 */
class EditingJobService
{
    /**
     * Legal transitions. Key = current status, value = list of legal next statuses.
     */
    public const TRANSITIONS = [
        'assigned'      => ['in_progress', 'cancelled'],
        'in_progress'   => ['review_ready', 'cancelled'],
        'review_ready'  => ['final', 'rejected'],
        'rejected'      => ['in_progress'],              // back for rework
        'final'         => [],                           // terminal
        'cancelled'     => [],                           // terminal
    ];

    public function __construct(private readonly AuditLogService $audit) {}

    public function transition(EditingJob $job, string $next, int $actorUserId, ?string $reason = null): EditingJob
    {
        return DB::transaction(function () use ($job, $next, $actorUserId, $reason) {

            $fresh = EditingJob::where('id', $job->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($fresh->status, $next);

            // Special enforcement: BUG-17/ADR — video_duration required when entering 'final'.
            if ($next === 'final' && empty($fresh->video_duration)) {
                throw new IllegalTransitionException(
                    "Cannot finalise job {$fresh->id} — video_duration is required for payroll linkage."
                );
            }

            $before = $fresh->status;
            $fresh->status = $next;

            match ($next) {
                'in_progress' => $fresh->started_at      ??= now(),
                'review_ready' => $fresh->review_ready_at = now(),
                'final'       => $fresh->finalized_at    = now(),
                'cancelled',
                'rejected'    => $fresh->closed_at       = now(),
                default       => null,
            };

            $fresh->save();

            $this->audit->record(
                action: 'editing_job_transition',
                subjectType: EditingJob::class,
                subjectId: $fresh->id,
                meta: compact('before', 'next', 'actorUserId', 'reason'),
            );

            return $fresh;
        });
    }

    public function assertTransition(string $from, string $to): void
    {
        $legal = self::TRANSITIONS[$from] ?? null;

        if ($legal === null) {
            throw new IllegalTransitionException("Unknown source status: {$from}");
        }

        if (!in_array($to, $legal, true)) {
            throw new IllegalTransitionException(
                "Illegal transition {$from} → {$to}. Legal: " . (empty($legal) ? '(terminal)' : implode(', ', $legal))
            );
        }
    }
}
