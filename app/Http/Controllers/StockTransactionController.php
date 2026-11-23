<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreStockTransactionRequest;
use App\Http\Resources\StockTransactionResource;
use App\Http\Resources\ToolingResource;
use App\Models\StockTransaction;
use App\Models\ToolsInventory;
use App\Services\StockMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransactionController extends Controller
{
    public function __construct(private readonly StockMutationService $mutations) {}

    /**
     * Module 2: Quick Stock Transaction (IN / OUT).
     */
    public function create(Request $request): Response
    {
        $tools = ToolsInventory::query()->ordered()->get();
        $recent = StockTransaction::query()->with('tool')->latest('id')->limit(15)->get();
        $type = strtoupper((string) $request->query('type', 'IN'));

        return Inertia::render('Transactions/Create', [
            'tools' => ToolingResource::collection($tools)->resolve(),
            'recentTransactions' => StockTransactionResource::collection($recent)->resolve(),
            'initialToolId' => $request->integer('tool_id') ?: null,
            'initialType' => in_array($type, ['IN', 'OUT'], true) ? $type : 'IN',
        ]);
    }

    public function store(StoreStockTransactionRequest $request): RedirectResponse
    {
        try {
            $transaction = $this->mutations->record(
                toolId: (int) $request->validated('tool_id'),
                type: $request->transactionType(),
                qtyChange: $request->qtyChange(),
                attributes: $request->safe()->only(['reference_no', 'pic_name', 'notes']),
            );
        } catch (InsufficientStockException $exception) {
            throw $exception->toValidationException('qty');
        }

        $transaction->load('tool');

        return back()->with('success', sprintf(
            '%s %d pcs — %s. Stok: %d → %d.',
            $transaction->type->value,
            abs($transaction->qty_change),
            $transaction->tool->tools_name_yes,
            $transaction->stock_before,
            $transaction->stock_after,
        ));
    }
}
