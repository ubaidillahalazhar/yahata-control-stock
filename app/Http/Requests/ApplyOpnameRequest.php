<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\IndonesianMessages;
use App\Models\ToolsInventory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApplyOpnameRequest extends FormRequest
{
    use IndonesianMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20000'],
            'items.*.tool_id' => ['required', 'integer', 'distinct'],
            'items.*.actual_stock' => ['required', 'integer', 'min:0', 'max:10000000'],
            'items.*.template_system_stock' => ['nullable', 'integer'],
            'pic_name' => ['required', 'string', 'max:100'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Validasi keberadaan seluruh tool_id dengan SATU query (bukan N query per baris).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = collect($this->input('items', []))->pluck('tool_id')->map(fn ($id): int => (int) $id)->unique();
                $found = ToolsInventory::query()->whereIn('id', $ids->all())->count();

                if ($found !== $ids->count()) {
                    $validator->errors()->add('items', 'Sebagian item opname sudah tidak ada di master. Upload ulang file opname.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => 'Data opname',
            'items.*.tool_id' => 'ID tooling',
            'items.*.actual_stock' => 'Actual physical stock',
            'pic_name' => 'Nama PIC',
            'reference_no' => 'No. referensi',
            'notes' => 'Catatan',
        ];
    }
}
