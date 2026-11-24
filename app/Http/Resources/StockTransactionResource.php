<?php

namespace App\Http\Resources;

use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransaction
 */
class StockTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'qty_change' => $this->qty_change,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'reference_no' => $this->reference_no,
            'pic_name' => $this->pic_name,
            'notes' => $this->notes,
            'damage_report_id' => $this->damage_report_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_display' => $this->created_at?->format('d/m/Y H:i'),
            'tool' => $this->whenLoaded('tool', fn (): ?array => $this->tool ? [
                'id' => $this->tool->id,
                'tools_name_yes' => $this->tool->tools_name_yes,
                'tools_name' => $this->tool->tools_name,
                'machine_name' => $this->tool->machine_name,
                'rack_location' => $this->tool->rack_location,
            ] : null),
        ];
    }
}
