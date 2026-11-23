<?php

namespace App\Http\Controllers;

use App\Enums\ImportIssueStatus;
use App\Http\Resources\ToolingResource;
use App\Models\ToolImportIssue;
use App\Models\ToolsInventory;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Module 1: Live Stock Control Dashboard.
     * Filtering, pencarian & KPI dihitung di sisi React (useMemo) agar instan.
     */
    public function __invoke(): Response
    {
        $tools = ToolsInventory::query()->ordered()->get();
        $issues = ToolImportIssue::query()->get(['tool_id', 'status']);

        return Inertia::render('Dashboard', [
            'tools' => ToolingResource::collection($tools)->resolve(),
            'racks' => $tools->pluck('rack_location')->filter()->unique()->sort()->values(),
            'forecastMonths' => $tools->first(fn (ToolsInventory $tool): bool => ! empty($tool->forecast_months))?->forecast_months ?? [],
            'lastUpdatedAt' => $tools->max('updated_at')?->format('d/m/Y H:i'),
            'importIssues' => [
                'total' => $issues->count(),
                'new' => $issues->where('status', ImportIssueStatus::NewHeld)->count(),
                'update' => $issues->where('status', ImportIssueStatus::UpdateHeld)->count(),
            ],
            // Tool lama yang update Excel terakhirnya ditahan → diberi ikon peringatan di tabel.
            'heldToolIds' => $issues->where('status', ImportIssueStatus::UpdateHeld)->pluck('tool_id')->filter()->unique()->values(),
        ]);
    }
}
