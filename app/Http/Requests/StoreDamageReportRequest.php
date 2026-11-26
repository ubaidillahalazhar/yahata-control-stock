<?php

namespace App\Http\Requests;

use App\Enums\DamageAction;
use App\Enums\DamageReason;
use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDamageReportRequest extends FormRequest
{
    use IndonesianMessages {
        messages as indonesianMessages;
    }

    public const MAX_SHOT_COUNT = 1_000_000_000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * PIC melapor atas nama akunnya sendiri → nama operator diambil dari akun, bukan dari input.
     * Admin boleh mengisi nama operator lain (melapor atas nama operator).
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user && ! $user->isAdmin()) {
            $this->merge(['operator_name' => $user->name]);
        }
    }

    public function rules(): array
    {
        return [
            'tool_id' => ['required', 'integer', Rule::exists('tools_inventory', 'id')],
            'operator_name' => ['required', 'string', 'max:100'],
            'shift' => ['required', 'integer', Rule::in([1, 2, 3])],
            'qty_replaced' => ['required', 'integer', 'min:1', 'max:1000'],
            // "Count belum dibawa" hanya untuk ganti baru; angka diisi belakangan dari riwayat laporan.
            'count_pending' => ['boolean', Rule::prohibitedIf(fn (): bool => $this->boolean('count_pending')
                && $this->input('action') !== DamageAction::ReplaceNew->value)],
            'actual_shot_count' => [
                Rule::excludeIf(fn (): bool => $this->boolean('count_pending')),
                'required', 'integer', 'min:0', 'max:'.self::MAX_SHOT_COUNT,
            ],
            'damage_reason' => ['required', Rule::enum(DamageReason::class)],
            'action' => ['required', Rule::enum(DamageAction::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->indonesianMessages(),
            'count_pending.prohibited' => '"Count belum dibawa" hanya bisa dipilih untuk tindakan Ganti Baru.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tool_id' => 'Item tooling',
            'operator_name' => 'Nama operator',
            'shift' => 'Shift',
            'qty_replaced' => 'Qty diganti',
            'actual_shot_count' => 'Aktual shot count',
            'count_pending' => 'Count belum dibawa',
            'damage_reason' => 'Alasan kerusakan',
            'action' => 'Tindakan',
            'notes' => 'Catatan',
        ];
    }
}
