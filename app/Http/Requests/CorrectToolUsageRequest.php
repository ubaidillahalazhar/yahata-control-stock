<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Koreksi satu input Pemakaian: tanggal / jumlah baru + alasan wajib.
 */
class CorrectToolUsageRequest extends FormRequest
{
    use IndonesianMessages {
        messages as baseMessages;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usage_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'qty' => ['required', 'integer', 'min:1', 'max:'.StoreToolUsageRequest::MAX_QTY],
            'pic_name' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
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
            'usage_date' => 'Tanggal pemakaian',
            'qty' => 'Jumlah pemakaian',
            'pic_name' => 'Nama PIC',
            'reason' => 'Alasan koreksi',
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
