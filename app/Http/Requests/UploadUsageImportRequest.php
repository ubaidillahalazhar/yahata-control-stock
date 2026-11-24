<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;

class UploadUsageImportRequest extends FormRequest
{
    use IndonesianMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usage_file' => ['required', 'file', 'extensions:xlsx,xls', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return ['usage_file' => 'File pemakaian'];
    }
}
