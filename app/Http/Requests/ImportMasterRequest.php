<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportMasterRequest extends FormRequest
{
    use IndonesianMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'master_file' => ['required', 'file', 'extensions:xlsx,xls', 'max:10240'],
            'sync_stock' => ['nullable', 'boolean'],
            'pic_name' => [Rule::requiredIf(fn (): bool => $this->boolean('sync_stock')), 'nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'master_file' => 'File master Excel',
            'sync_stock' => 'Sinkron stok',
            'pic_name' => 'Nama PIC',
        ];
    }
}
