<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    protected $fillable = [
        'name', 'is_default', 'is_active',
        'vacation_days', 'sick_days', 'personal_days',
        'allow_carryover', 'max_carryover_days', 'carryover_expires_months',
        'allow_encashment', 'max_encash_days_per_year', 'encash_rate_formula',
        'available_during_probation', 'apply_to_payroll_modes', 'note',
        'sick_during_probation', 'personal_during_probation', 'vacation_during_probation',
        'vacation_eligibility_months',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'allow_carryover' => 'boolean',
            'allow_encashment' => 'boolean',
            'available_during_probation' => 'boolean',
            'sick_during_probation' => 'boolean',
            'personal_during_probation' => 'boolean',
            'vacation_during_probation' => 'boolean',
            'vacation_eligibility_months' => 'integer',
            'apply_to_payroll_modes' => 'array',
        ];
    }

    /**
     * Returns true if the policy allows the given leave type during probation.
     *
     * Thai labor law baseline:
     *   - sick_leave (ม.32) — allowed from day 1 (toggleable via policy)
     *   - personal_leave (ม.34) — allowed from day 1 (toggleable via policy)
     *   - vacation_leave (ม.30) — NOT allowed during probation (toggleable)
     *   - maternity_leave (ม.41) — allowed regardless of probation (statutory right)
     *   - paternity_leave — company discretion → falls back to `available_during_probation`
     *   - lwop — unpaid leave, doesn't draw quota → allowed regardless
     */
    public function allowsDuringProbation(string $leaveType): bool
    {
        return match ($leaveType) {
            'sick_leave'     => (bool) $this->sick_during_probation,
            'personal_leave' => (bool) $this->personal_during_probation,
            'vacation_leave' => (bool) $this->vacation_during_probation,
            'maternity_leave' => true,   // statutory — cannot be restricted
            'lwop'            => true,   // unpaid leave, never restricted
            default          => (bool) $this->available_during_probation,
        };
    }

    public const ENCASH_FORMULAS = [
        'salary_div_30' => 'ฐานเงินเดือน ÷ 30',
        'salary_div_22' => 'ฐานเงินเดือน ÷ 22 (วันทำงาน)',
        'manual'        => 'ระบุเอง (ทุกครั้ง)',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /** Compute encashment rate per day for a given employee based on this policy's formula */
    public function computeEncashRate(Employee $employee): float
    {
        $base = (float) ($employee->salaryProfile?->base_salary ?? 0);
        return match ($this->encash_rate_formula) {
            'salary_div_22' => round($base / 22, 2),
            'salary_div_30' => round($base / 30, 2),
            default         => 0.0,
        };
    }

    /** Find the policy that should apply to an employee using fallback chain */
    public static function resolveFor(Employee $employee): ?self
    {
        if ($employee->leave_policy_id) {
            $p = self::where('id', $employee->leave_policy_id)->where('is_active', true)->first();
            if ($p) return $p;
        }
        return self::where('is_default', true)->where('is_active', true)->first();
    }
}
