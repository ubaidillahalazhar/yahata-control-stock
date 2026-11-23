<?php

namespace App\Http\Resources;

use App\Models\ToolsInventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi tunggal item tooling + seluruh hasil rumus Excel (ToolingCalculatorService).
 * Dipakai konsisten oleh halaman Inertia maupun REST API.
 *
 * @mixin ToolsInventory
 */
class ToolingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ToolsInventory $tool */
        $tool = $this->resource;
        $calc = $tool->calculation();
        $life = $tool->lifeSummary();
        $currentStock = (int) $tool->current_stock;

        return [
            'id' => $tool->id,
            'no' => $tool->no,
            'customer' => $tool->customer,
            'machine_name' => $tool->machine_name,
            'step' => $tool->step,
            'tools_name' => $tool->tools_name,
            'yes_name' => $tool->yes_name,
            'part_name' => $tool->part_name,
            'tools_name_yes' => $tool->tools_name_yes,
            'rack_location' => $tool->rack_location,
            'part_no' => $tool->part_no,
            'current_stock' => $currentStock,
            'life_time' => (int) $tool->life_time,
            'lead_time_days' => (int) $tool->lead_time_days,
            'forecasts' => $calc['forecasts'],
            'forecast_months' => $tool->forecast_months ?? [],

            // === Hasil kalkulasi (identik dengan kolom Excel) ===
            'forecast_avg' => $calc['forecast_avg'],                         // R
            'monthly_need' => $calc['monthly_need'],                         // S..W
            'monthly_balance' => $calc['monthly_balance'],                   // X..AB
            'tooling_need_a' => $calc['tooling_need_a'],                     // AC
            'safety_stock_lead_time_b' => $calc['safety_stock_lead_time_b'], // AD
            'safety_stock_fluct_c' => $calc['safety_stock_fluct_c'],         // AE
            'safety_stock_add_d' => $calc['safety_stock_add_d'],             // AF
            'min_stock_e' => $calc['min_stock_e'],                           // AG
            'max_total_stand_by' => $calc['max_total_stand_by'],             // AH
            'status_info' => $calc['status_info'],                           // AI
            'est_prod_qty_min' => $calc['est_prod_qty_min'],                 // AJ
            'est_prod_qty_stand_by' => $calc['est_prod_qty_stand_by'],       // AK
            'koreksi_min_stock' => $calc['koreksi_min_stock'],               // AL
            'koreksi_stand_by' => $calc['koreksi_stand_by'],                 // AM

            // === Life time (input pemakaian harian, bukan kolom Excel) ===
            'life_used' => $life['life_used'],
            'life_remaining' => $life['life_remaining'],
            'life_over' => $life['life_over'],
            'life_usage_percent' => $life['life_usage_percent'],
            'life_level' => $life['life_level'],                             // green | yellow | red
            'life_status' => $life['life_status'],                           // SECURE | ORDER (≥ 80%)

            // === Turunan untuk UI (bukan kolom Excel) ===
            'is_empty' => $currentStock <= 0,
            'shortage_qty' => max(0, $calc['min_stock_e'] - $currentStock),
            'suggested_order_qty' => max(0, $calc['max_total_stand_by'] - $currentStock),

            'updated_at' => $tool->updated_at?->toIso8601String(),
        ];
    }
}
