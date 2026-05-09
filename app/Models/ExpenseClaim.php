<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseClaim extends Model
{
    use SoftDeletes;
    use HasAttachments;

    protected $fillable = [
        'employee_id',
        'description',
        'amount',
        'type',
        'claim_date',
        'status',
        'month',
        'year',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'claim_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getDocumentNumberAttribute(): string
    {
        $prefix = $this->type === 'advance' ? 'ADV' : 'EXP';
        $ym = optional($this->claim_date)->format('ym') ?: now()->format('ym');
        return $prefix . '-' . $ym . '-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
