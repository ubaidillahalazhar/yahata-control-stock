<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;

class CancelToolUsageRequest extends FormRequest
{
    use IndonesianMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pic_name' => ['required', 'string', 'max:100'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
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
            'pic_name' => 'Nama PIC',
            'cancel_reason' => 'Alasan pembatalan',
        ];
    }
}
