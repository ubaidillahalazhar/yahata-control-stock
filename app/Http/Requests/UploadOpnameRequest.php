<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;

class UploadOpnameRequest extends FormRequest
{
    use IndonesianMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opname_file' => ['required', 'file', 'extensions:xlsx,xls', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return ['opname_file' => 'File hasil opname'];
    }
}
