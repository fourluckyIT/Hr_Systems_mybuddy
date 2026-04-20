<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cycle_id'          => ['required', 'integer', 'exists:bonus_cycles,id'],
            'approved_by'       => ['required', 'string', 'max:50'],
            'calculation_ids'   => ['required', 'array', 'min:1'],
            'calculation_ids.*' => ['required', 'integer', 'exists:bonus_calculations,id'],
        ];
    }
}
