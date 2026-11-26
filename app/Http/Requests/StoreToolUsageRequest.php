<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input pemakaian life time (USAGE) atau ganti tool / reset life (RESET).
 */
class StoreToolUsageRequest extends FormRequest
{
    use IndonesianMessages {
        messages as baseMessages;
    }

    public const MAX_QTY = 100_000_000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isReset = $this->routeIs('tools.life-reset');

        return [
            'tool_id' => $isReset ? [] : ['required', 'integer', Rule::exists('tools_inventory', 'id')],
            'usage_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'qty' => $isReset ? [] : ['required', 'integer', 'min:1', 'max:'.self::MAX_QTY],
            'pic_name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm_unusual' => ['nullable', 'boolean'],
        ];
    }

    /** PIC mencatat atas nama akunnya sendiri; hanya admin yang boleh mengisi nama PIC lain. */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user !== null && ! $user->isAdmin()) {
            $this->merge(['pic_name' => $user->name]);
        }
    }

    public function attributes(): array
    {
        return [
            'tool_id' => 'Item tooling',
            'usage_date' => 'Tanggal pemakaian',
            'qty' => 'Jumlah pemakaian',
            'pic_name' => 'Nama PIC',
            'notes' => 'Catatan',
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->baseMessages(),
            'usage_date.before_or_equal' => 'Tanggal pemakaian tidak boleh melewati hari ini.',
        ];
    }
}
