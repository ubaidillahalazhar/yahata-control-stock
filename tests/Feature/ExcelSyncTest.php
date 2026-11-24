<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Exports\StockOpnameTemplateExport;
use App\Models\StockTransaction;
use App\Models\ToolsInventory;
use App\Services\StockOpnameService;
use App\Services\ToolingMasterSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExcelSyncTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/Fixtures/yahata_tools.xlsx');
    }

    #[Test]
    public function master_import_reads_the_original_yahata_file(): void
    {
        $result = app(ToolingMasterSyncService::class)->import($this->fixture());

        $this->assertSame(37, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(37, ToolsInventory::query()->count());

        $tool = ToolsInventory::query()->where('tools_name_yes', 'CBP136 1# CUK COLLAR 19.9')->firstOrFail();

        $this->assertSame('1#', $tool->step, 'Step harus di-trim');
        $this->assertSame(200000, $tool->life_time);
        $this->assertSame(30, $tool->lead_time_days);
        $this->assertEquals([410568, 393279, 393279, 393279, 0], $tool->forecasts);
        $this->assertSame('ORDER', $tool->calculation()['status_info']);
        $this->assertSame(3, $tool->calculation()['min_stock_e']);
        $this->assertSame(['2026-11-26', '2026-12-26', '2027-01-27', '2027-02-27', '2027-03-27'], $tool->forecast_months);

        // Stok awal tercatat di audit log.
        $this->assertSame(
            ToolsInventory::query()->where('current_stock', '>', 0)->count(),
            StockTransaction::query()->where('type', TransactionType::OpnameAdjust->value)->count(),
        );
    }

    #[Test]
    public function reimport_updates_master_without_touching_stock_unless_requested(): void
    {
        $sync = app(ToolingMasterSyncService::class);
        $sync->import($this->fixture());

        $tool = ToolsInventory::query()->where('tools_name_yes', 'CBP136 1# COK COLLAR 19.9')->firstOrFail();
        $tool->update(['current_stock' => 10]);

        $again = $sync->import($this->fixture());
        $this->assertSame(0, $again['created']);
        $this->assertSame(37, $again['updated']);
        $this->assertSame(10, $tool->fresh()->current_stock);

        $synced = $sync->import($this->fixture(), syncStock: true, picName: 'Admin');
        $this->assertSame(1, $synced['stock_adjusted']);
        $this->assertSame(4, $tool->fresh()->current_stock);
    }

    #[Test]
    public function opname_template_can_be_downloaded_and_previewed(): void
    {
        app(ToolingMasterSyncService::class)->import($this->fixture());

        $this->get('/sync/opname-template')->assertOk();

        Excel::store(new StockOpnameTemplateExport, 'opname-test.xlsx', 'local');
        $preview = app(StockOpnameService::class)->preview(storage_path('app/private/opname-test.xlsx'));

        // Template kosong → belum ada baris "Actual Physical Stock" yang terisi.
        $this->assertSame(0, $preview['summary']['rows_with_input']);
        $this->assertSame(37, $preview['summary']['skipped_blank']);
    }
}
