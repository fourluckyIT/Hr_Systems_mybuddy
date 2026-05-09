<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DaySwapRequest extends Model
{
    use HasAttachments;

    protected $fillable = [
        'employee_id',
        'work_date',
        'off_date',
        'reason',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'off_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function getDocumentNumberAttribute(): string
    {
        $ym = optional($this->work_date)->format('ym') ?: now()->format('ym');
        return 'SW-' . $ym . '-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
