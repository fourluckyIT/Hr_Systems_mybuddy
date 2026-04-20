<?php

namespace App\Services\Agents;

use App\Models\Employee;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fixes BUG-10 (priority-aware dedupe; Critical events no longer silently suppressed)
 * and BUG-14 (registers payroll.guard_blocked event emitted by PayrollGuardAgent).
 *
 * Dedupe rules by priority:
 *   Critical: 1 hour window   AND state-hash must match
 *   High    : 6 hour window   AND state-hash must match
 *   Medium/Low: 24 hour window
 *
 * State-hash means: a critical alert whose metadata changed (e.g., consecutive_days went
 * from 7 → 8) is NOT treated as a duplicate — it fires again, because the situation escalated.
 */
class NotificationDispatchAgent
{
    /**
     * Event → [audience roles, priority].
     * BUG-14 fix: payroll.guard_blocked included.
     */
    public const REGISTRY = [
        'job.stalled'                   => [['admin'],             'Medium'],
        'job.overdue'                   => [['admin', 'editor'],   'High'],
        'job.review_ready'              => [['admin'],             'Medium'],
        'payslip.finalized'             => [['employee'],          'Low'],
        'bonus.ready_for_review'        => [['admin'],             'Medium'],
        'compliance.critical_violation' => [['admin'],             'Critical'],
        'finance.reconciliation_ready'  => [['admin', 'owner'],    'Medium'],
        'leave.pending_review'          => [['admin'],             'Low'],
        'payroll.guard_blocked'         => [['admin'],             'High'],
    ];

    public function dispatch(
        string $eventKey,
        int|string|null $entityId,
        array $payload = [],
        ?string $priority = null,
        ?array $audienceOverride = null,
    ): Collection {
        [$roles, $defaultPriority] = self::REGISTRY[$eventKey] ?? [['admin'], 'Medium'];
        $priority = $priority ?? $defaultPriority;
        $roles    = $audienceOverride ?? $roles;

        $dedupeKey = $this->dedupeKey($eventKey, $entityId, $payload);
        $window    = $this->windowSeconds($priority);

        $delivered = collect();

        DB::transaction(function () use (
            $eventKey, $priority, $entityId, $payload, $dedupeKey, $window, $roles, &$delivered
        ) {
            $recipients = $this->resolveRecipients($roles, $entityId, $eventKey);

            foreach ($recipients as $recipient) {
                if ($this->recentlySent($dedupeKey, $recipient->id, $window)) {
                    continue;
                }

                $log = NotificationLog::create([
                    'event_key'      => $eventKey,
                    'priority'       => $priority,
                    'dedupe_key'     => $dedupeKey,
                    'recipient_id'   => $recipient->id,
                    'recipient_role' => $recipient->role ?? 'admin',
                    'channel'        => 'in_app',
                    'sent_at'        => now(),
                    'payload'        => $payload,
                ]);

                $delivered->push($log);
            }
        });

        return $delivered;
    }

    /** State-hash included so escalating alerts aren't suppressed. */
    private function dedupeKey(string $eventKey, int|string|null $entityId, array $payload): string
    {
        $state = hash('xxh64', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return "{$eventKey}:" . ($entityId ?? 'null') . ":{$state}";
    }

    private function windowSeconds(string $priority): int
    {
        return match ($priority) {
            'Critical' => 60 * 60,
            'High'     => 6 * 60 * 60,
            default    => 24 * 60 * 60,
        };
    }

    private function recentlySent(string $dedupeKey, int $recipientId, int $windowSec): bool
    {
        return NotificationLog::query()
            ->where('dedupe_key', $dedupeKey)
            ->where('recipient_id', $recipientId)
            ->where('sent_at', '>=', Carbon::now()->subSeconds($windowSec))
            ->exists();
    }

    /**
     * Audience resolution:
     *  - 'employee' role means the single employee referenced by $entityId
     *    (e.g. for payslip.finalized, notify the payslip owner).
     *  - Any other role means every active user with that role.
     */
    private function resolveRecipients(array $roles, int|string|null $entityId, string $eventKey): Collection
    {
        $recipients = collect();

        foreach ($roles as $role) {
            if ($role === 'employee' && $entityId !== null) {
                $emp = Employee::find($entityId);
                if ($emp && $emp->user_id) {
                    $recipients->push(User::find($emp->user_id));
                }
                continue;
            }

            $recipients = $recipients->merge(
                User::query()->where('role', $role)->where('status', 'active')->get()
            );
        }

        return $recipients->filter()->unique('id')->values();
    }
}
