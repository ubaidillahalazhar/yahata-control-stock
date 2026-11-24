<?php

namespace App\Http\Resources;

use App\Models\DamageReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DamageReport
 */
class DamageReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_no' => $this->report_no,
            'operator_name' => $this->operator_name,
            'shift' => $this->shift,
            'qty_replaced' => $this->qty_replaced,
            'actual_shot_count' => $this->actual_shot_count,
            'damage_reason' => $this->damage_reason->value,
            'damage_reason_label' => $this->damage_reason->label(),
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'cuts_stock' => $this->action->cutsStock(),
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'notes' => $this->notes,
            'reported_at' => $this->reported_at?->toIso8601String(),
            'reported_at_display' => $this->reported_at?->format('d/m/Y H:i'),
            'reported_date' => $this->reported_at?->translatedFormat('l, d F Y'),
            'reported_time' => $this->reported_at?->format('H:i'),
            'tool' => $this->whenLoaded('tool', fn (): ?array => $this->tool ? [
                'id' => $this->tool->id,
                'tools_name_yes' => $this->tool->tools_name_yes,
                'tools_name' => $this->tool->tools_name,
                'yes_name' => $this->tool->yes_name,
                'customer' => $this->tool->customer,
                'machine_name' => $this->tool->machine_name,
                'step' => $this->tool->step,
                'part_name' => $this->tool->part_name,
                'part_no' => $this->tool->part_no,
                'rack_location' => $this->tool->rack_location,
                'life_time' => (int) $this->tool->life_time,
                'current_stock' => (int) $this->tool->current_stock,
            ] : null),
            'life_time_usage_pct' => $this->relationLoaded('tool') && $this->tool && $this->tool->life_time > 0
                ? round($this->actual_shot_count / $this->tool->life_time * 100, 1)
                : null,
            'stock_transaction_id' => $this->whenLoaded('stockTransaction', fn () => $this->stockTransaction?->id),
        ];
    }
}
