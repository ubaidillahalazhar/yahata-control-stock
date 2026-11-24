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

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', sprintf(
                '%s %d pcs — %s. Stok: %d → %d. Slip bukti mutasi siap dicetak.',
                $transaction->type->value,
                abs($transaction->qty_change),
                $transaction->tool->tools_name_yes,
                $transaction->stock_before,
                $transaction->stock_after,
            ));
    }

    public function show(StockTransaction $stockTransaction): Response
    {
        $stockTransaction->load('tool');

        return Inertia::render('Transactions/Show', [
            'transaction' => [
                'id' => $stockTransaction->id,
                'reference_no' => $stockTransaction->reference_no ?: sprintf('TRX-%06d', $stockTransaction->id),
                'type' => $stockTransaction->type->value,
                'qty_change' => $stockTransaction->qty_change,
                'stock_before' => $stockTransaction->stock_before,
                'stock_after' => $stockTransaction->stock_after,
                'pic_name' => $stockTransaction->pic_name,
                'notes' => $stockTransaction->notes,
                'created_at' => $stockTransaction->created_at?->format('d/m/Y H:i'),
                'tool' => [
                    'tools_name_yes' => $stockTransaction->tool?->tools_name_yes,
                    'item_name' => $stockTransaction->tool?->item_name ?? $stockTransaction->tool?->part_name,
                    'rack_location' => $stockTransaction->tool?->rack_location,
                    'unit' => $stockTransaction->tool?->unit ?? 'PCS',
                ],
            ],
        ]);
    }
}