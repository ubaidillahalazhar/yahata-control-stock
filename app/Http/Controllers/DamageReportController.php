<?php

namespace App\Http\Controllers;

use App\Enums\DamageAction;
use App\Enums\DamageReason;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\ListFilterRequest;
use App\Http\Requests\StoreDamageReportRequest;
use App\Http\Resources\DamageReportResource;
use App\Http\Resources\ToolingResource;
use App\Models\DamageReport;
use App\Models\ToolsInventory;
use App\Services\DamageReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DamageReportController extends Controller
{
    private const FILTER_KEYS = ['date_from', 'date_to', 'shift', 'damage_reason', 'action', 'operator', 'search'];

    public function __construct(private readonly DamageReportService $reports) {}

    /**
     * Riwayat seluruh laporan kerusakan.
     */
    public function index(ListFilterRequest $request): Response
    {
        $filters = array_intersect_key($request->filters(), array_flip(self::FILTER_KEYS));

        $reports = DamageReport::query()
            ->with('tool')
            ->filter($filters)
            ->latest('reported_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $summary = DamageReport::query()
            ->filter($filters)
            ->selectRaw('damage_reason, COUNT(*) AS total_reports, COALESCE(SUM(qty_replaced), 0) AS total_qty')
            ->groupBy('damage_reason')
            ->get()
            ->mapWithKeys(fn (DamageReport $row): array => [
                $row->damage_reason->value => [
                    'reports' => (int) $row->getAttribute('total_reports'),
                    'qty' => (int) $row->getAttribute('total_qty'),
                ],
            ]);

        return Inertia::render('DamageReports/Index', [
            'reports' => DamageReportResource::collection($reports)->response()->getData(true),
            'summary' => $summary,
            'filters' => (object) $filters,
            'reasons' => DamageReason::options(),
            'actions' => DamageAction::options(),
        ]);
    }

    /**
     * Module 3: Form mobile yang dibuka via scan QR (?tool_id=… atau ?machine=…&rack=…).
     */
    public function create(Request $request): Response
    {
        $tools = ToolsInventory::query()->ordered()->get();
        $toolId = $request->integer('tool_id');

        return Inertia::render('DamageReports/Create', [
            'tools' => ToolingResource::collection($tools)->resolve(),
            'selectedToolId' => $toolId && $tools->contains('id', $toolId) ? $toolId : null,
            'scope' => [
                'machine' => $request->string('machine')->trim()->toString() ?: null,
                'rack' => $request->string('rack')->trim()->toString() ?: null,
            ],
            'reasons' => DamageReason::options(),
            'actions' => DamageAction::options(),
        ]);
    }

    public function store(StoreDamageReportRequest $request): RedirectResponse
    {
        try {
            $report = $this->reports->submit($request->validated());
        } catch (InsufficientStockException $exception) {
            throw $exception->toValidationException('qty_replaced');
        }

        $message = $report->action->cutsStock()
            ? sprintf('Laporan %s tersimpan. Stok dipotong %d pcs (%d → %d).', $report->report_no, $report->qty_replaced, $report->stock_before, $report->stock_after)
            : sprintf('Laporan %s tersimpan (Regrind / Repair — stok tidak berubah).', $report->report_no);

        return to_route('damage-reports.show', $report)->with('success', $message);
    }

    /**
     * Slip / formulir resmi siap cetak.
     */
    public function show(DamageReport $damageReport): Response
    {
        $damageReport->load(['tool', 'stockTransaction']);

        return Inertia::render('DamageReports/Show', [
            'report' => (new DamageReportResource($damageReport))->resolve(),
        ]);
    }
}
