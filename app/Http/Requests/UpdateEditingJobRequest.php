<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEditingJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modified_by'   => ['required', 'integer', 'exists:employees,id'],
            'job_name'      => ['sometimes', 'string', 'max:200'],
            'game_id'       => ['sometimes', 'integer', 'exists:games,id'],
            'game_link'     => ['nullable', 'string', 'max:500'],
            'deadline_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'notes'         => ['nullable', 'string', 'max:5000'],
        ];
    }
}
