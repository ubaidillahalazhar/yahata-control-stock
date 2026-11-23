<?php

namespace App\Http\Controllers;

use App\Models\ToolsInventory;
use Inertia\Inertia;
use Inertia\Response;

class QrLabelController extends Controller
{
    /**
     * Label QR siap cetak: per item (?tool_id=) dan per mesin + rak (?machine=&rack=).
     */
    public function __invoke(): Response
    {
        $tools = ToolsInventory::query()->ordered()->get(['id', 'no', 'tools_name_yes', 'tools_name', 'machine_name', 'step', 'rack_location', 'part_no']);

        $groups = $tools
            ->groupBy(fn (ToolsInventory $tool): string => ($tool->machine_name ?? '').'|'.($tool->rack_location ?? ''))
            ->map(fn ($items): array => [
                'machine_name' => $items->first()->machine_name,
                'rack_location' => $items->first()->rack_location,
                'count' => $items->count(),
            ])
            ->values();

        return Inertia::render('QrLabels/Index', [
            'tools' => $tools->map->only(['id', 'no', 'tools_name_yes', 'tools_name', 'machine_name', 'step', 'rack_location', 'part_no'])->values(),
            'groups' => $groups,
        ]);
    }
}
