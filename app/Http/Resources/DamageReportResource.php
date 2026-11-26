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
            'system_shot_count' => $this->system_shot_count,
            'life_time_snapshot' => $this->life_time_snapshot,
            'shot_variance' => $this->shot_variance,
            'count_pending' => $this->count_pending,
            'count_overdue' => $this->isCountOverdue(),
            'count_filled_at_display' => $this->count_filled_at?->format('d/m/Y H:i'),
            'needs_review' => $this->needs_review,
            'awaiting_review' => $this->isAwaitingReview(),
            'reviewed_at_display' => $this->reviewed_at?->format('d/m/Y H:i'),
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'review_note' => $this->review_note,
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
            'life_time_usage_pct' => $this->lifeTimeUsagePercent(),
            'stock_transaction_id' => $this->whenLoaded('stockTransaction', fn () => $this->stockTransaction?->id),
        ];
    }

    /** Pencapaian life = counter mesin / life time standar saat laporan dibuat (snapshot, fallback master). */
    private function lifeTimeUsagePercent(): ?float
    {
        $lifeTime = $this->life_time_snapshot
            ?? ($this->relationLoaded('tool') && $this->tool ? (int) $this->tool->life_time : null);

        if ($this->actual_shot_count === null || ! $lifeTime) {
            return null;
        }

        return round($this->actual_shot_count / $lifeTime * 100, 1);
    }
}
