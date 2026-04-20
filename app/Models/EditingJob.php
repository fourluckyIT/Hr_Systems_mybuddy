<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditingJob extends Model
{
    protected $fillable = [
        'job_name',
        'game_id',
        'game_link',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'deadline_days',
        'layer_count',
        'deadline_date',
        'status',
        'started_at',
        'review_ready_at',
        'finalized_at',
        'video_duration_minutes',
        'video_duration_seconds',
        'notes',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'deadline_days'    => 'integer',
            'layer_count'      => 'integer',
            'deadline_date'    => 'date',
            'assigned_at'      => 'datetime',
            'started_at'       => 'datetime',
            'review_ready_at'  => 'datetime',
            'finalized_at'     => 'datetime',
            'video_duration_minutes' => 'integer',
            'video_duration_seconds' => 'integer',
            'is_deleted'       => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class, 'editing_job_id');
    }

    public function reassignments()
    {
        return $this->hasMany(JobReassignment::class);
    }

    public function modifications()
    {
        return $this->hasMany(JobModification::class);
    }

    public function deadlineNotifications()
    {
        return $this->hasMany(DeadlineNotification::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('assigned_to', $employeeId);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    public function isOverdue(): bool
    {
        if (!$this->deadline_date || $this->status === 'final') {
            return false;
        }
        if (in_array($this->status, ['review_ready', 'final'])) {
            return false;
        }

        return now()->toDateString() > $this->deadline_date->toDateString();
    }

    public function getWorkDurationDaysAttribute(): ?int
    {
        if (!$this->started_at || !$this->review_ready_at) {
            return null;
        }

        return $this->started_at->diffInDays($this->review_ready_at);
    }

    public function getDeadlineComplianceAttribute(): ?string
    {
        if (!$this->review_ready_at || !$this->deadline_date) {
            return null;
        }

        $diff = $this->review_ready_at->toDateString() <=> $this->deadline_date->toDateString();

        return match ($diff) {
            -1 => 'early',
             0 => 'on_time',
             1 => 'late',
        };
    }
}
