<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fixes BUG-19. Explicit $fillable + $guarded so a crafted form
 * submission can't flip sensitive fields via mass assignment.
 *
 * Sensitive fields (never set via $request->all() or ->fill()):
 *   - status             (active/inactive/terminated)
 *   - probation_end_date (controls bonus eligibility)
 *   - role               (admin/owner/editor)
 *   - salary             (monthly salary)
 *   - company_id         (multi-tenant scope)
 *   - user_id            (login linkage)
 *
 * These must be set only through dedicated service methods that go through
 * policy checks (e.g. EmployeeStatusService::terminate()).
 */
class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        // safe to accept from UI forms
        'first_name', 'last_name', 'nickname', 'email', 'phone',
        'date_of_birth', 'hire_date', 'bank_account', 'bank_name',
        'tax_id', 'sso_id', 'vacation_entitlement',
        'payroll_mode',              // safe; changing mode is a user-facing edit
        'default_rate', 'default_layer_multiplier',
        'note',
    ];

    /** These cannot be mass-assigned under any circumstance. */
    protected $guarded = [
        'id',
        'status',
        'probation_end_date',
        'role',
        'salary',
        'company_id',
        'user_id',
        'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'date_of_birth'      => 'date',
        'hire_date'          => 'date',
        'probation_end_date' => 'date',
        'salary'             => 'decimal:2',
        'default_rate'       => 'decimal:2',
    ];

    /* ------------------------------------------------------------------ */
    /* Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInProbation($query, ?\Carbon\Carbon $asOf = null)
    {
        $asOf ??= now();
        return $query->whereNotNull('probation_end_date')
                     ->where('probation_end_date', '>', $asOf);
    }
}
