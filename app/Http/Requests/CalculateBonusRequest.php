<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id'           => ['required', 'integer', 'exists:employees,id'],
            'cycle_id'              => ['required', 'integer', 'exists:bonus_cycles,id'],
            'base_reference'        => ['required', 'numeric', 'gt:0'],
            'tier_id'               => ['nullable', 'string', 'exists:performance_tiers,tier_code'],
            'clip_duration_minutes_per_month' => ['nullable', 'integer', 'min:0'],
            'qualified_months'      => ['nullable', 'integer', 'min:0'],
            'attendance_adjustment' => ['sometimes', 'numeric', 'between:-1,1'],
            'absent_days'           => ['sometimes', 'integer', 'min:0'],
            'late_count'            => ['sometimes', 'integer', 'min:0'],
            'leave_days'            => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
