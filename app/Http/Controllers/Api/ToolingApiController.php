<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ToolingResource;
use App\Models\ToolsInventory;
use App\Services\ToolingCalculatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * REST API read-only (integrasi / perangkat lain). Kalkulasi identik dengan dashboard.
 */
class ToolingApiController extends Controller
{
    /**
     * GET /api/v1/tools?status=ORDER|SECURE&rack=D6&search=CBP136
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = strtoupper($request->string('status', 'ALL')->toString());

        $tools = ToolsInventory::query()
            ->ordered()
            ->when($request->string('rack')->toString(), fn (Builder $q, string $rack) => $q->where('rack_location', $rack))
            ->when($request->string('search')->trim()->toString(), function (Builder $q, string $search): void {
                $like = '%'.mb_strtolower($search).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(tools_name_yes) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(tools_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(machine_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(part_no) LIKE ?', [$like]));
            })
            ->get();

        if (in_array($status, [ToolingCalculatorService::STATUS_ORDER, ToolingCalculatorService::STATUS_SECURE], true)) {
            $tools = $tools->filter(fn (ToolsInventory $tool): bool => $tool->calculation()['status_info'] === $status)->values();
        }

        return ToolingResource::collection($tools);
    }

    public function show(ToolsInventory $tool): ToolingResource
    {
        return new ToolingResource($tool);
    }

    /**
     * GET /api/v1/tools/summary — KPI ringkas.
     */
    public function summary(): JsonResponse
    {
        $tools = ToolsInventory::query()->get();
        $order = $tools->filter(fn (ToolsInventory $tool): bool => $tool->calculation()['status_info'] === ToolingCalculatorService::STATUS_ORDER)->count();

        return response()->json([
            'data' => [
                'total_items' => $tools->count(),
                'order' => $order,
                'secure' => $tools->count() - $order,
                'empty_stock' => $tools->where('current_stock', '<=', 0)->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
