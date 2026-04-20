<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchCalculateBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cycle_id'                          => ['required', 'integer', 'exists:bonus_cycles,id'],
            'employees'                         => ['required', 'array', 'min:1'],
            'employees.*.employee_id'           => ['required', 'integer', 'exists:employees,id'],
            'employees.*.base_reference'        => ['required', 'numeric', 'gt:0'],
            'employees.*.tier_id'               => ['nullable', 'string', 'exists:performance_tiers,tier_code'],
            'employees.*.clip_duration_minutes_per_month' => ['nullable', 'integer', 'min:0'],
            'employees.*.qualified_months'      => ['nullable', 'integer', 'min:0'],
            'employees.*.attendance_adjustment' => ['sometimes', 'numeric', 'between:-1,1'],
            'employees.*.absent_days'           => ['sometimes', 'integer', 'min:0'],
            'employees.*.late_count'            => ['sometimes', 'integer', 'min:0'],
            'employees.*.leave_days'            => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
