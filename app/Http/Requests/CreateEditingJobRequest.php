<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEditingJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_name'      => ['required', 'string', 'max:200'],
            'game_id'       => ['required', 'integer', 'exists:games,id'],
            'game_link'     => ['nullable', 'string', 'max:500'],
            'assigned_to'   => ['required', 'integer', 'exists:employees,id'],
            'assigned_by'   => ['required', 'integer', 'exists:employees,id'],
            'deadline_days' => ['required', 'integer', 'min:1', 'max:365'],
            'notes'         => ['nullable', 'string', 'max:5000'],
        ];
    }
}
