<?php

namespace App\Http\Controllers;

use App\Http\Resources\DamageReportResource;
use App\Http\Resources\StockTransactionResource;
use App\Http\Resources\ToolingResource;
use App\Http\Resources\ToolUsageLogResource;
use App\Models\ToolsInventory;
use App\Services\ToolingCalculatorService;
use App\Services\ToolLifeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ToolController extends Controller
{
    /**
     * Halaman Cek Stok & QR Tooling khusus operator PIC.
     */
    public function picIndex(Request $request): Response
    {
        $tools = ToolsInventory::query()->ordered()->get();

        return Inertia::render('Pic/Tools', [
            'tools' => ToolingResource::collection($tools)->resolve(),
            'defaultMachine' => $request->user()?->machine_name,
        ]);
    }

    /**
     * Detail item: breakdown seluruh rumus per bulan + histori transaksi & kerusakan.
     */
    public function show(ToolsInventory $tool): Response
    {
        $transactions = $tool->stockTransactions()->latest('id')->limit(50)->get();
        $damageReports = $tool->damageReports()->latest('id')->limit(20)->get();
        $usageLogs = $tool->usageLogs()->with('corrects:id,usage_date,qty')->orderByDesc('usage_date')->orderByDesc('id')->limit(30)->get();

        return Inertia::render('Tools/Show', [
            'tool' => (new ToolingResource($tool))->resolve(),
            ...$this->forecastVsActual($tool),
            'usageLogs' => ToolUsageLogResource::collection($usageLogs)->resolve(),
            'transactions' => StockTransactionResource::collection($transactions)->resolve(),
            'damageReports' => DamageReportResource::collection($damageReports)->resolve(),
        ]);
    }

    /**
     * Forecast (rencana dari Excel) vs Actual (jumlah input Pemakaian Life Time per bulan).
     *
     * - forecastActuals: sejajar dengan 5 kolom forecast (M..Q).
     * - monthlyActuals : 6 bulan terakhir s.d. bulan ini, karena bulan yang sudah lewat
     *                    biasanya sudah tidak ada di window forecast Excel terbaru.
     *
     * Actual hanya untuk evaluasi — rumus Min Stock / Status Stok tetap memakai Forecast.
     *
     * @return array{forecastActuals: list<array<string, mixed>>, monthlyActuals: list<array<string, mixed>>}
     */
    private function forecastVsActual(ToolsInventory $tool): array
    {
        $currentMonth = now()->format('Y-m');
        $forecasts = ToolingCalculatorService::normalizeForecasts((array) ($tool->forecasts ?? []));

        $forecastMonths = [];
        $forecastByMonth = [];

        for ($i = 0; $i < ToolingCalculatorService::FORECAST_MONTHS; $i++) {
            $date = $tool->forecast_months[$i] ?? null;
            $month = $date ? substr((string) $date, 0, 7) : null;
            $forecastMonths[] = $month;

            if ($month !== null) {
                $forecastByMonth[$month] = $forecasts[$i];
            }
        }

        $recentMonths = array_map(
            fn (int $back): string => now()->startOfMonth()->subMonthsNoOverflow($back)->format('Y-m'),
            range(5, 0),
        );

        $actuals = ToolLifeService::monthlyActuals($tool->id, [...$forecastMonths, ...$recentMonths]);

        $row = fn (string $month, ?float $forecast): array => [
            'month' => $month,
            'forecast' => $forecast,
            ...$actuals[$month],
            'percent' => $forecast > 0 ? (int) round($actuals[$month]['actual_qty'] / $forecast * 100) : null,
            'is_current' => $month === $currentMonth,
            'is_future' => $month > $currentMonth,
        ];

        return [
            'forecastActuals' => array_map(
                fn (?string $month, int $i): ?array => $month === null ? null : $row($month, $forecasts[$i]),
                $forecastMonths,
                array_keys($forecastMonths),
            ),
            'monthlyActuals' => array_map(
                fn (string $month): array => $row($month, $forecastByMonth[$month] ?? null),
                array_reverse($recentMonths),
            ),
        ];
    }
}