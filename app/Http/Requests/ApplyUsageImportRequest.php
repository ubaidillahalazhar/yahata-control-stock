<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;

class ApplyUsageImportRequest extends FormRequest
{
    use IndonesianMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'include_rows' => ['nullable', 'array'],
            'include_rows.*' => ['integer', 'min:2'],
        ];
    }

    public function attributes(): array
    {
        return [
            'token' => 'Preview import',
            'include_rows' => 'Baris ditahan',
        ];
    }
}
