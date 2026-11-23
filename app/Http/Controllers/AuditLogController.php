<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Exports\AuditLogExport;
use App\Http\Requests\ListFilterRequest;
use App\Http\Resources\StockTransactionResource;
use App\Models\StockTransaction;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditLogController extends Controller
{
    private const FILTER_KEYS = ['date_from', 'date_to', 'type', 'pic', 'search', 'tool_id'];

    /**
     * Module 5: Audit Log History seluruh pergerakan stok.
     */
    public function index(ListFilterRequest $request): Response
    {
        $filters = array_intersect_key($request->filters(), array_flip(self::FILTER_KEYS));

        $transactions = StockTransaction::query()
            ->with('tool:id,tools_name_yes,tools_name,machine_name,rack_location')
            ->filter($filters)
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $summary = StockTransaction::query()
            ->filter($filters)
            ->selectRaw('type, COUNT(*) AS total_rows, COALESCE(SUM(qty_change), 0) AS total_qty')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(fn (StockTransaction $row): array => [
                $row->type->value => ['rows' => (int) $row->getAttribute('total_rows'), 'qty' => (int) $row->getAttribute('total_qty')],
            ]);

        return Inertia::render('AuditLog/Index', [
            'transactions' => StockTransactionResource::collection($transactions)->response()->getData(true),
            'summary' => $summary,
            'filters' => (object) $filters,
            'types' => TransactionType::options(),
        ]);
    }

    public function export(ListFilterRequest $request): BinaryFileResponse
    {
        $filters = array_intersect_key($request->filters(), array_flip(self::FILTER_KEYS));

        return Excel::download(new AuditLogExport($filters), 'Audit_Log_Stok_'.now()->format('Ymd_His').'.xlsx');
    }
}
