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
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'damage_report_id' => $this->damage_report_id,
            'usage_date' => $this->usage_date?->toDateString(),
            'usage_date_display' => $this->usage_date?->format('d/m/Y'),
            'qty' => $this->qty,
            'life_used_before' => $this->life_used_before,
            'life_used_after' => $this->life_used_after,
            'pic_name' => $this->pic_name,
            'notes' => $this->notes,
            'batch_no' => $this->batch_no,
            'is_cancelled' => $this->isCancelled(),
            'cancelled_at_display' => $this->cancelled_at?->format('d/m/Y H:i'),
            'cancelled_by' => $this->cancelled_by,
            'cancel_reason' => $this->cancel_reason,
            'created_at_display' => $this->created_at?->format('d/m/Y H:i'),
            'corrects_id' => $this->corrects_id,
            'corrects' => $this->whenLoaded('corrects', fn (): ?array => $this->corrects ? [
                'id' => $this->corrects->id,
                'usage_date_display' => $this->corrects->usage_date?->format('d/m/Y'),
                'qty' => (int) $this->corrects->qty,
            ] : null),
            'is_corrected' => $this->isCancelled() && str_starts_with((string) $this->cancel_reason, 'Dikoreksi:'),
            // Hak ubah untuk user yang sedang melihat (aturan 24 jam PIC ada di model).
            'can_change' => $this->canBeChangedBy($request->user()),
            'correctable' => $this->isCorrectable(),
            'change_until_display' => $request->user()?->isAdmin() ? null : $this->changeWindowEndsAt()?->format('d/m/Y H:i'),
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
