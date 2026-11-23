<?php

namespace App\Http\Controllers;

use App\Enums\UsageType;
use App\Http\Requests\CancelToolUsageRequest;
use App\Http\Requests\StoreToolUsageRequest;
use App\Http\Resources\ToolingResource;
use App\Http\Resources\ToolUsageLogResource;
use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use App\Services\ToolLifeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input Forecast / Pemakaian life time harian (tanggal acak, 1 baris = 1 input).
 */
class ToolUsageController extends Controller
{
    public function __construct(private readonly ToolLifeService $life) {}

    public function index(Request $request): Response
    {
        $filters = array_filter($request->validate([
            'tool_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
            'type' => ['nullable', Rule::enum(UsageType::class)],
            'state' => ['nullable', Rule::in(['active', 'cancelled'])],
        ]), fn ($value): bool => $value !== null && $value !== '');

        $logs = ToolUsageLog::query()
            ->with('tool:id,tools_name_yes,tools_name,machine_name,life_time')
            ->filter($filters)
            ->orderByDesc('usage_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $monthTotal = ToolUsageLog::query()
            ->filter([...$filters, 'state' => 'active', 'type' => UsageType::Usage->value])
            ->selectRaw('COUNT(*) AS total_rows, COALESCE(SUM(qty), 0) AS total_qty')
            ->first();

        return Inertia::render('ToolUsages/Index', [
            'tools' => ToolingResource::collection(ToolsInventory::query()->ordered()->get())->resolve(),
            'logs' => ToolUsageLogResource::collection($logs)->response()->getData(true),
            'filters' => (object) $filters,
            'totals' => [
                'rows' => (int) $monthTotal?->getAttribute('total_rows'),
                'qty' => (int) $monthTotal?->getAttribute('total_qty'),
            ],
            'initialToolId' => $request->integer('tool_id') ?: null,
        ]);
    }

    public function store(StoreToolUsageRequest $request): RedirectResponse
    {
        $log = $this->life->recordUsage(
            toolId: (int) $request->validated('tool_id'),
            usageDate: $request->validated('usage_date'),
            qty: (int) $request->validated('qty'),
            picName: $request->validated('pic_name'),
            notes: $request->validated('notes'),
        );

        return back()->with('success', $this->message($log, sprintf('Pemakaian %s pcs tercatat', number_format($log->qty, 0, ',', '.'))));
    }

    public function reset(StoreToolUsageRequest $request, ToolsInventory $tool): RedirectResponse
    {
        $log = $this->life->reset(
            toolId: $tool->id,
            usageDate: $request->validated('usage_date'),
            picName: $request->validated('pic_name'),
            notes: $request->validated('notes'),
        );

        return back()->with('success', $this->message($log, 'Tool diganti, life terpakai di-reset'));
    }

    public function cancel(CancelToolUsageRequest $request, ToolUsageLog $usage): RedirectResponse
    {
        $log = $this->life->cancel($usage, $request->validated('pic_name'), $request->validated('cancel_reason'));

        return back()->with('success', $this->message($log, 'Input dibatalkan'));
    }

    private function message(ToolUsageLog $log, string $prefix): string
    {
        $tool = ToolsInventory::query()->findOrFail($log->tool_id);
        $life = $tool->lifeSummary();

        return sprintf(
            '%s — %s. Life terpakai %s%% (sisa %s pcs) · Status Life %s.',
            $prefix,
            $tool->tools_name_yes,
            number_format($life['life_usage_percent'], 1, ',', '.'),
            number_format($life['life_remaining'], 0, ',', '.'),
            $life['life_status'],
        );
    }
}
