<?php

namespace App\Http\Requests;

use App\Enums\DamageAction;
use App\Enums\DamageReason;
use App\Enums\TransactionType;
use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi query string filter untuk Audit Log & Riwayat Kerusakan.
 */
class ListFilterRequest extends FormRequest
{
    use IndonesianMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'pic' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'tool_id' => ['nullable', 'integer'],
            'shift' => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'damage_reason' => ['nullable', Rule::enum(DamageReason::class)],
            'action' => ['nullable', Rule::enum(DamageAction::class)],
            'operator' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['date_from' => 'Tanggal dari', 'date_to' => 'Tanggal sampai'];
    }

    /**
     * Hanya filter yang terisi.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter($this->validated(), fn ($value): bool => $value !== null && $value !== '');
    }
}
