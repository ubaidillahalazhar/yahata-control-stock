<?php

namespace App\Http\Controllers;

use App\Http\Resources\DamageReportResource;
use App\Http\Resources\StockTransactionResource;
use App\Http\Resources\ToolingResource;
use App\Models\ToolsInventory;
use Inertia\Inertia;
use Inertia\Response;

class ToolController extends Controller
{
    /**
     * Detail item: breakdown seluruh rumus per bulan + histori transaksi & kerusakan.
     */
    public function show(ToolsInventory $tool): Response
    {
        $transactions = $tool->stockTransactions()->latest('id')->limit(50)->get();
        $damageReports = $tool->damageReports()->latest('id')->limit(20)->get();

        return Inertia::render('Tools/Show', [
            'tool' => (new ToolingResource($tool))->resolve(),
            'transactions' => StockTransactionResource::collection($transactions)->resolve(),
            'damageReports' => DamageReportResource::collection($damageReports)->resolve(),
        ]);
    }
}
