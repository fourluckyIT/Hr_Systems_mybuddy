<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveEncashment extends Model
{
    protected $fillable = [
        'employee_id', 'year', 'leave_type', 'days', 'rate_per_day', 'amount',
        'payout_month', 'payout_year', 'extra_income_entry_id', 'note', 'created_by',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'days'         => 'decimal:2',
            'rate_per_day' => 'decimal:2',
            'amount'       => 'decimal:2',
            'year'         => 'integer',
            'payout_month' => 'integer',
            'payout_year'  => 'integer',
            'approved_at'  => 'datetime',
        ];
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments()
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }

    public function deleteAttachment(DocumentAttachment $attachment): void
    {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function extraIncomeEntry()
    {
        return $this->belongsTo(ExtraIncomeEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
