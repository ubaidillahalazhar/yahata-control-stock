<?php

namespace App\Http\Resources;

use App\Models\ToolUsageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ToolUsageLog
 */
class ToolUsageLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'usage_date' => $this->usage_date?->toDateString(),
            'usage_date_display' => $this->usage_date?->format('d/m/Y'),
            'qty' => $this->qty,
            'life_used_before' => $this->life_used_before,
            'life_used_after' => $this->life_used_after,
            'pic_name' => $this->pic_name,
            'notes' => $this->notes,
            'is_cancelled' => $this->isCancelled(),
            'cancelled_at_display' => $this->cancelled_at?->format('d/m/Y H:i'),
            'cancelled_by' => $this->cancelled_by,
            'cancel_reason' => $this->cancel_reason,
            'created_at_display' => $this->created_at?->format('d/m/Y H:i'),
            'tool' => $this->whenLoaded('tool', fn (): ?array => $this->tool ? [
                'id' => $this->tool->id,
                'tools_name_yes' => $this->tool->tools_name_yes,
                'tools_name' => $this->tool->tools_name,
                'machine_name' => $this->tool->machine_name,
                'life_time' => (int) $this->tool->life_time,
            ] : null),
        ];
    }
}
