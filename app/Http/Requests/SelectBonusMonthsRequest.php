<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectBonusMonthsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'months' => ['required', 'array', 'min:1'],
            'months.*' => ['required', 'date_format:Y-m', 'distinct'],
        ];
    }
}
