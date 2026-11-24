<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Http\Requests\Concerns\IndonesianMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransactionRequest extends FormRequest
{
    use IndonesianMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tool_id' => ['required', 'integer', Rule::exists('tools_inventory', 'id')],
            'type' => ['required', Rule::in([TransactionType::In->value, TransactionType::Out->value])],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'pic_name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tool_id' => 'Item tooling',
            'type' => 'Jenis transaksi',
            'qty' => 'Jumlah',
            'reference_no' => 'No. referensi',
            'pic_name' => 'Nama PIC',
            'notes' => 'Catatan',
        ];
    }

    public function transactionType(): TransactionType
    {
        return TransactionType::from($this->validated('type'));
    }

    /**
     * IN = positif, OUT = negatif.
     */
    public function qtyChange(): int
    {
        $qty = (int) $this->validated('qty');

        return $this->transactionType() === TransactionType::In ? $qty : -$qty;
    }
}
