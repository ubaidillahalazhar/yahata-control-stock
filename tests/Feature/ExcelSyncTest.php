<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Exports\StockOpnameTemplateExport;
use App\Models\StockTransaction;
use App\Models\ToolImportIssue;
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

        // 12 baris file asli tidak punya Yes Name (kolom F) → ditahan di "Excel Belum Lengkap".
        $this->assertSame(25, $result['created']);
        $this->assertSame(12, $result['skipped']);
        $this->assertSame(12, $result['held_new']);
        $this->assertSame(25, ToolsInventory::query()->count());
        $this->assertSame(12, ToolImportIssue::query()->count());

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
        $this->assertSame(25, $again['updated']);
        $this->assertSame(12, ToolImportIssue::query()->count(), 'Isi tabel issue diganti, tidak menumpuk');
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
        $this->assertSame(25, $preview['summary']['skipped_blank']);
    }

    #[Test]
    public function master_template_has_examples_no_2_to_5_and_can_be_imported_back(): void
    {
        $this->travelTo('2026-11-23 10:00:00');

        $this->get('/sync/master-template')->assertOk()
            ->assertDownload('template_master_tooling.xlsx');

        Excel::store(new \App\Exports\MasterToolingTemplateExport, 'template-test.xlsx', 'local');
        $path = storage_path('app/private/template-test.xlsx');

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getSheetByName('control stock');
        $this->assertSame('no', $sheet->getCell('A1')->getValue());
        $this->assertSame("Lead time Order \n(Day)", $sheet->getCell('AN1')->getValue());
        $this->assertNull($sheet->getCell('AO1')->getValue(), 'Template hanya sampai kolom AN');
        $this->assertSame('Nov-26', $sheet->getCell('M2')->getFormattedValue());
        $this->assertSame([2, 3, 4, 5], array_map(fn ($r) => $sheet->getCell("A{$r}")->getValue(), range(3, 6)));
        $this->assertNull($sheet->getCell('A7')->getValue());

        // Rumus ikut menghitung: no 3 → R = 397.601, Min Stock = ROUNDUP(1,99 + 1) = 3 → ORDER (stok 1).
        $this->assertEquals(397_601, $sheet->getCell('R4')->getCalculatedValue());
        $this->assertEquals(3, $sheet->getCell('AG4')->getCalculatedValue());
        $this->assertSame('ORDER', $sheet->getCell('AI4')->getCalculatedValue());

        // Upload kembali: 3 tool masuk, no 2 (tanpa Yes Name) ditahan di Excel Belum Lengkap.
        $result = app(ToolingMasterSyncService::class)->import($path);
        @unlink($path);

        $this->assertSame(3, $result['created']);
        $this->assertSame(1, $result['held_new']);
        $this->assertSame(['yes_name'], array_column(ToolImportIssue::query()->sole()->problems, 'field'));
        $this->assertSame(397_601.0, (float) ToolsInventory::query()->where('tools_name_yes', 'CBP136 1# CUK COLLAR 19.9')->firstOrFail()->calculation()['forecast_avg']);
    }
}
