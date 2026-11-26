<?php

namespace Tests\Feature;

use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use App\Services\ToolLifeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolLifeTest extends TestCase
{
    use RefreshDatabase;

    private function useLife(ToolsInventory $tool, int $qty, string $date): \Illuminate\Testing\TestResponse
    {
        return $this->post('/tool-usages', [
            'tool_id' => $tool->id,
            'usage_date' => $date,
            'qty' => $qty,
            'pic_name' => 'Budi',
            // Angka uji sengaja besar (> 10% life time) → konfirmasi angka janggal.
            'confirm_unusual' => true,
        ]);
    }

    #[Test]
    public function thresholds_are_fixed_green_yellow_red(): void
    {
        $cases = [
            [0, 'green', 'SECURE', 0.0],
            [499_999, 'green', 'SECURE', 49.9],
            [500_000, 'yellow', 'SECURE', 50.0],
            [799_999, 'yellow', 'SECURE', 79.9],
            [800_000, 'red', 'ORDER', 80.0],
            [1_200_000, 'red', 'ORDER', 120.0],
        ];

        foreach ($cases as [$used, $level, $status, $percent]) {
            $summary = ToolLifeService::summary(1_000_000, $used);

            $this->assertSame($level, $summary['life_level'], "used {$used}");
            $this->assertSame($status, $summary['life_status'], "used {$used}");
            $this->assertSame($percent, $summary['life_usage_percent'], "used {$used}");
        }

        $this->assertSame(0, ToolLifeService::summary(1_000_000, 1_200_000)['life_remaining']);
        $this->assertSame(200_000, ToolLifeService::summary(1_000_000, 1_200_000)['life_over']);
    }

    #[Test]
    public function random_day_inputs_accumulate_into_life_used(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000]);

        $this->useLife($tool, 600_000, now()->subDays(10)->toDateString())->assertSessionHasNoErrors();
        $this->useLife($tool, 100_000, now()->toDateString())->assertSessionHasNoErrors();

        $tool->refresh();
        $this->assertSame(700_000, $tool->life_used);
        $this->assertSame('yellow', $tool->lifeSummary()['life_level']);
        $this->assertSame(300_000, $tool->lifeSummary()['life_remaining']);
        $this->assertDatabaseCount('tool_usage_logs', 2);
    }

    #[Test]
    public function future_dates_are_rejected(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000]);

        $this->useLife($tool, 10, now()->addDay()->toDateString())->assertSessionHasErrors('usage_date');

        $this->assertDatabaseCount('tool_usage_logs', 0);
    }

    #[Test]
    public function cancelling_an_input_restores_life_used_and_keeps_the_row(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000]);
        $this->useLife($tool, 300_000, now()->toDateString());
        $this->useLife($tool, 600_000, now()->toDateString());
        $this->assertSame('red', $tool->fresh()->lifeSummary()['life_level']);

        $log = ToolUsageLog::query()->where('qty', 600_000)->firstOrFail();

        $this->post("/tool-usages/{$log->id}/cancel", ['pic_name' => 'Sari', 'cancel_reason' => 'Salah ketik'])
            ->assertSessionHasNoErrors();

        $this->assertSame(300_000, $tool->fresh()->life_used);
        $this->assertNotNull($log->fresh()->cancelled_at);
        $this->assertSame('Sari', $log->fresh()->cancelled_by);
        $this->assertDatabaseCount('tool_usage_logs', 2);

        $this->post("/tool-usages/{$log->id}/cancel", ['pic_name' => 'Sari'])->assertSessionHasErrors('usage');
    }

    #[Test]
    public function reset_starts_a_new_tool_and_older_backdated_usage_does_not_count(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000, 'current_stock' => 4]);
        $this->useLife($tool, 900_000, now()->subDays(5)->toDateString());

        $this->post("/tools/{$tool->id}/life-reset", ['usage_date' => now()->subDays(2)->toDateString(), 'pic_name' => 'Budi'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $tool->fresh()->life_used);
        $this->assertSame(4, $tool->fresh()->current_stock, 'Reset tidak mengubah stok');
        $this->assertDatabaseHas('tool_usage_logs', ['type' => 'RESET', 'qty' => 900_000, 'life_used_after' => 0]);

        // Input mundur sebelum tanggal reset = milik tool lama.
        $this->useLife($tool, 50_000, now()->subDays(4)->toDateString());
        $this->assertSame(0, $tool->fresh()->life_used);

        // Input setelah reset = tool baru.
        $this->useLife($tool, 120_000, now()->toDateString());
        $this->assertSame(120_000, $tool->fresh()->life_used);

        // Reset dibatalkan → pemakaian tool lama kembali dihitung.
        $reset = ToolUsageLog::query()->where('type', 'RESET')->firstOrFail();
        $this->post("/tool-usages/{$reset->id}/cancel", ['pic_name' => 'Budi']);
        $this->assertSame(1_070_000, $tool->fresh()->life_used);
    }

    #[Test]
    public function pages_render_with_life_data(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000]);
        $this->useLife($tool, 820_000, now()->toDateString());

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('tools.0.life_status', 'ORDER')
            ->where('tools.0.life_level', 'red')
            ->where('tools.0.life_remaining', 180_000));

        $this->get("/tools/{$tool->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Tools/Show')
            ->has('usageLogs', 1));

        $this->get('/tool-usages?month='.now()->format('Y-m'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('ToolUsages/Index')
            ->where('totals.qty', 820_000)
            ->has('logs.data', 1));
    }

    #[Test]
    public function detail_page_compares_forecast_with_monthly_actual(): void
    {
        $this->travelTo('2026-11-23 10:00:00');

        $tool = ToolsInventory::factory()->create([
            'life_time' => 200_000,
            'forecasts' => [400_000, 420_000, 0, 0, 0],
            'forecast_months' => ['2026-11-01', '2026-12-01', '2027-01-01', null, null],
        ]);

        $this->useLife($tool, 100_000, '2026-10-31');           // bulan lalu
        $this->useLife($tool, 180_000, '2026-11-01');
        $this->post("/tools/{$tool->id}/life-reset", ['usage_date' => '2026-11-10', 'pic_name' => 'Budi']);
        $this->useLife($tool, 150_000, '2026-11-12');
        $this->useLife($tool, 120_000, '2026-11-30');           // ditolak: tanggal masa depan
        $this->useLife($tool, 120_000, '2026-11-23');
        $this->useLife($tool, 999_999, '2026-11-20');
        $wrong = ToolUsageLog::query()->where('qty', 999_999)->firstOrFail();
        $this->post("/tool-usages/{$wrong->id}/cancel", ['pic_name' => 'Budi']);   // dibatalkan → tidak dihitung

        $this->get("/tools/{$tool->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Tools/Show')
            ->where('forecastActuals.0.month', '2026-11')
            ->where('forecastActuals.0.actual_qty', 450_000)
            ->where('forecastActuals.0.usage_inputs', 3)
            ->where('forecastActuals.0.tools_replaced', 1)
            ->where('forecastActuals.0.percent', 113)
            ->where('forecastActuals.0.is_current', true)
            ->where('forecastActuals.1.is_future', true)
            ->where('forecastActuals.1.actual_qty', 0)
            ->where('forecastActuals.2.percent', null)              // forecast 0 → tidak ada %
            ->where('forecastActuals.3', null)                      // bulan kosong di baris 2 Excel
            ->has('monthlyActuals', 6)
            ->where('monthlyActuals.0.month', '2026-11')
            ->where('monthlyActuals.1.month', '2026-10')
            ->where('monthlyActuals.1.actual_qty', 100_000)
            ->where('monthlyActuals.1.forecast', null)              // Okt sudah tidak ada di window forecast
            ->where('monthlyActuals.5.month', '2026-06'));
    }

    #[Test]
    public function control_stock_export_has_actual_using_tools_per_forecast_month(): void
    {
        $this->travelTo('2026-11-23 10:00:00');

        $tool = ToolsInventory::factory()->create([
            'life_time' => 200_000,
            'forecasts' => [400_000, 420_000, 0, 0, 0],
            'forecast_months' => ['2026-10-01', '2026-11-01', '2026-12-01', '2027-01-01', '2027-02-01'],
        ]);
        $other = ToolsInventory::factory()->create(['forecast_months' => $tool->forecast_months]);

        $this->useLife($tool, 375_000, '2026-10-15');
        $this->useLife($tool, 300_000, '2026-11-02');
        $this->useLife($tool, 150_000, '2026-11-20');
        $this->useLife($tool, 777_777, '2026-11-21');
        $wrong = ToolUsageLog::query()->where('qty', 777_777)->firstOrFail();
        $this->post("/tool-usages/{$wrong->id}/cancel", ['pic_name' => 'Budi']);

        $this->get('/sync/control-stock-export')->assertOk();

        \Maatwebsite\Excel\Facades\Excel::store(new \App\Exports\ControlStockExport, 'control-test.xlsx', 'local');
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(storage_path('app/private/control-test.xlsx'))->getActiveSheet();
        @unlink(storage_path('app/private/control-test.xlsx'));

        $this->assertSame('Actual Using Tools (pcs)', $sheet->getCell('AO1')->getValue());
        $this->assertSame('AO1:AS1', collect($sheet->getMergeCells())->first(fn ($range) => str_starts_with($range, 'AO1')));
        $this->assertSame('Oct-26', $sheet->getCell('AO2')->getFormattedValue());

        $rowOf = fn (ToolsInventory $t): int => collect(range(3, 4))->first(fn ($r) => $sheet->getCell("H{$r}")->getValue() === $t->tools_name_yes);

        $row = $rowOf($tool);
        $this->assertSame(375_000, $sheet->getCell("AO{$row}")->getValue());   // Okt
        $this->assertSame(450_000, $sheet->getCell("AP{$row}")->getValue());   // Nov (yang dibatalkan tidak dihitung)
        $this->assertNull($sheet->getCell("AQ{$row}")->getValue());            // Des belum berjalan → kosong

        $this->assertSame(0, $sheet->getCell('AP'.$rowOf($other))->getValue()); // tanpa input → 0
    }
}
