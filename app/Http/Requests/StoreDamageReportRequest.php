<?php

namespace App\Http\Requests;

use App\Enums\DamageAction;
use App\Enums\DamageReason;
use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDamageReportRequest extends FormRequest
{
    use IndonesianMessages;

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
            'actual_shot_count' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'damage_reason' => ['required', Rule::enum(DamageReason::class)],
            'action' => ['required', Rule::enum(DamageAction::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'damage_reason' => 'Alasan kerusakan',
            'action' => 'Tindakan',
            'notes' => 'Catatan',
        ];
    }
}
