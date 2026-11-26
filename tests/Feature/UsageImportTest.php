<?php

namespace Tests\Feature;

use App\Exports\UsageTemplateExport;
use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use App\Services\ToolLifeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADINGS = ['Tools Name YES', 'Tanggal Pemakaian', 'Jumlah Terpakai (pcs)', 'Nama PIC', 'Catatan'];

    /** @var list<string> */
    private array $files = [];

    private ToolsInventory $chuck;

    private ToolsInventory $punch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-24 10:00:00');

        $this->chuck = ToolsInventory::factory()->create([
            'tools_name_yes' => 'CBP136 1# CUK COLLAR 19.9',
            'tools_name' => 'CHUCK FINGER',
            'life_time' => 200_000,
        ]);
        $this->punch = ToolsInventory::factory()->create([
            'tools_name_yes' => 'CBP136 2# PPN COLLAR 19.9',
            'tools_name' => 'PUNCH PIN',
            'life_time' => 1_000_000,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * @param  list<list<mixed>>  $rows  [Tools Name YES, Tanggal, Jumlah, PIC, Catatan]
     */
    private function upload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pemakaian');
        $sheet->fromArray([self::HEADINGS], null, 'A1', true);

        // String disimpan apa adanya sebagai teks (mis. "180.000"), angka / serial tanggal sebagai angka.
        foreach ($rows as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                if ($value === null) {
                    continue;
                }

                $cell = $sheet->getCell([$c + 1, $r + 2]);
                is_string($value)
                    ? $cell->setValueExplicit($value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
                    : $cell->setValue($value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'plt').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        return new UploadedFile($path, 'pemakaian.xlsx', null, null, true);
    }

    private function serial(string $date): float
    {
        return ExcelDate::PHPToExcel(new \DateTime($date));
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(array $rows): array
    {
        $this->post('/sync/usage-preview', ['usage_file' => $this->upload($rows)])->assertSessionHasNoErrors();

        return session('usage_preview');
    }

    #[Test]
    public function rows_dated_on_or_before_latest_replace_new_report_are_held_until_admin_includes_them(): void
    {
        // Laporan kerusakan Ganti Baru chuck tgl 22/11 (count mesin dibawa) → pemakaian s.d. 22/11 sudah tercakup.
        $admin = auth()->user();
        $this->travelTo('2026-11-22 08:00:00');
        $this->actingAs($this->createPic())->post('/damage-reports', [
            'tool_id' => $this->chuck->id,
            'operator_name' => 'Operator',
            'shift' => 1,
            'qty_replaced' => 1,
            'actual_shot_count' => 5_000,
            'damage_reason' => 'CHIPPING',
            'action' => 'REPLACE_NEW',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin);
        $this->travelTo('2026-11-24 10:00:00');

        $preview = $this->preview([
            ['CBP136 1# CUK COLLAR 19.9', $this->serial('2026-11-21'), 1200, 'Sari', null], // baris 2 → ditahan
            ['CBP136 1# CUK COLLAR 19.9', $this->serial('2026-11-22'), 800, 'Sari', null],  // baris 3 → ditahan (sama tanggal)
            ['CBP136 1# CUK COLLAR 19.9', $this->serial('2026-11-23'), 900, 'Sari', null],  // baris 4 → OK
            ['CBP136 2# PPN COLLAR 19.9', $this->serial('2026-11-21'), 700, 'Sari', null],  // baris 5 → tool lain, OK
        ]);

        $this->assertSame('counter', $preview['rows'][0]['duplicate']['kind']);
        $this->assertStringContainsString('22/11/2026', $preview['rows'][0]['duplicate']['message']);
        $this->assertSame('counter', $preview['rows'][1]['duplicate']['kind']);
        $this->assertNull($preview['rows'][2]['duplicate']);
        $this->assertNull($preview['rows'][3]['duplicate']);
        $this->assertSame(2, $preview['summary']['duplicate_rows']);

        // Admin hanya mencentang baris 3 → baris 2 tidak pernah masuk database (tidak perlu dihapus).
        $this->post('/sync/usage-apply', ['token' => $preview['token'], 'include_duplicates' => [3]])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('tool_usage_logs', ['tool_id' => $this->chuck->id, 'qty' => 1200]);
        $this->assertDatabaseHas('tool_usage_logs', ['tool_id' => $this->chuck->id, 'qty' => 800, 'source' => 'excel']);
        $this->assertDatabaseHas('tool_usage_logs', ['tool_id' => $this->chuck->id, 'qty' => 900, 'source' => 'excel']);
        $this->assertDatabaseHas('tool_usage_logs', ['tool_id' => $this->punch->id, 'qty' => 700]);
    }

    #[Test]
    public function template_has_entry_sheet_tool_list_dropdown_and_guide(): void
    {
        $this->get('/sync/usage-template')->assertOk()->assertDownload('template_pemakaian_life_time.xlsx');

        Excel::store(new UsageTemplateExport, 'usage-template-test.xlsx', 'local');
        $path = storage_path('app/private/usage-template-test.xlsx');
        $book = IOFactory::load($path);
        @unlink($path);

        $this->assertSame(['Pemakaian', 'Daftar Tool', 'Petunjuk'], $book->getSheetNames());

        $entry = $book->getSheetByName('Pemakaian');
        $this->assertSame(self::HEADINGS, $entry->rangeToArray('A1:E1')[0]);
        $this->assertNull($entry->getCell('A2')->getValue(), 'Tanpa contoh data agar tidak ikut ter-import');
        $this->assertSame("'Daftar Tool'!\$A\$2:\$A\$3", $entry->getCell('A2')->getDataValidation()->getFormula1());
        $this->assertSame('list', $entry->getCell('A500')->getDataValidation()->getType());

        $list = $book->getSheetByName('Daftar Tool');
        $this->assertEqualsCanonicalizing(
            ['CBP136 1# CUK COLLAR 19.9', 'CBP136 2# PPN COLLAR 19.9'],
            [$list->getCell('A2')->getValue(), $list->getCell('A3')->getValue()],
        );
    }

    #[Test]
    public function valid_file_is_previewed_then_applied_as_one_batch_and_fills_actual(): void
    {
        $preview = $this->preview([
            ['CBP136 1# CUK COLLAR 19.9', $this->serial('2026-11-12'), 120_000, 'Budi', 'Shift 1'],
            ['cbp136   1# cuk collar 19.9', '19/11/2026', '180.000', 'Sari', null],   // huruf kecil + spasi ganda tetap cocok
            ['CBP136 2# PPN COLLAR 19.9', '5/11/2026', 300_000, 'Budi', null],
            [null, null, null, null, null],                                            // baris kosong dilewati
        ]);

        $this->assertNotNull($preview['token']);
        $this->assertSame(['total_rows' => 3, 'valid_rows' => 3, 'error_rows' => 0, 'duplicate_rows' => 0, 'tools' => 2], $preview['summary']);
        $this->assertSame(['2026-11-12', '2026-11-19', '2026-11-05'], array_column($preview['rows'], 'usage_date'));
        $this->assertSame([120_000, 180_000, 300_000], array_column($preview['rows'], 'qty'));
        $this->assertSame(0, ToolUsageLog::query()->count(), 'Preview belum menulis data');

        $this->post('/sync/usage-apply', ['token' => $preview['token']])->assertSessionHasNoErrors();

        $result = session('usage_result');
        $this->assertMatchesRegularExpression('/^PLT-20261124-100000$/', $result['batch_no']);
        $this->assertSame(3, $result['inserted']);
        $this->assertSame(600_000, $result['total_qty']);

        $this->assertSame(3, ToolUsageLog::query()->where('batch_no', $result['batch_no'])->count());
        $this->assertSame(300_000, $this->chuck->fresh()->life_used);
        $this->assertSame('ORDER', $this->chuck->fresh()->lifeSummary()['life_status']);
        $this->assertSame('Shift 1', ToolUsageLog::query()->where('qty', 120_000)->value('notes'));

        // Actual Using Tools bulan November ikut terisi.
        $this->assertSame(300_000, ToolLifeService::monthlyActuals($this->chuck->id, ['2026-11'])['2026-11']['actual_qty']);

        // Token sekali pakai: klik "Terapkan" dua kali tidak membuat data dobel.
        $this->post('/sync/usage-apply', ['token' => $preview['token']])->assertSessionHasErrors('usage_import');
        $this->assertSame(3, ToolUsageLog::query()->count());
    }

    #[Test]
    public function one_bad_row_blocks_the_whole_file(): void
    {
        $preview = $this->preview([
            ['CBP136 1# CUK COLLAR 19.9', '12/11/2026', 1000, 'Budi', null],          // valid
            [null, '12/11/2026', 1000, 'Budi', null],                                  // Tools Name YES kosong
            ['CHUCK FINGER', '12/11/2026', 1000, 'Budi', null],                        // Tools Name, bukan Tools Name YES
            ['CBP136 1# CUK COLLAR 19.9', '25/11/2026', 1000, 'Budi', null],           // tanggal masa depan
            ['CBP136 1# CUK COLLAR 19.9', '31/02/2026', 'abc', null, null],            // tanggal tidak valid, qty bukan angka, PIC kosong
            ['CBP136 1# CUK COLLAR 19.9', '12/11/2026', 0, 'Budi', null],              // qty 0
            ['CBP136 1# CUK COLLAR 19.9', null, 1.5, 'Budi', null],                    // tanggal kosong, qty desimal
        ]);

        $this->assertNull($preview['token'], 'Tidak bisa diterapkan bila ada baris salah');
        $this->assertSame(6, $preview['summary']['error_rows']);

        $reasons = array_map(
            fn (array $row): array => array_column($row['problems'], 'reason', 'field'),
            $preview['rows'],
        );

        $this->assertSame([], $reasons[0]);
        $this->assertSame(['tools_name_yes' => 'kosong'], $reasons[1]);
        $this->assertStringContainsString('tidak terdaftar', $reasons[2]['tools_name_yes']);
        $this->assertSame(['usage_date' => 'tidak boleh tanggal yang akan datang'], $reasons[3]);
        $this->assertSame(['usage_date', 'qty', 'pic_name'], array_keys($reasons[4]));
        $this->assertSame(['qty' => 'harus lebih dari 0'], $reasons[5]);
        $this->assertSame(['usage_date' => 'kosong', 'qty' => 'harus bilangan bulat'], $reasons[6]);

        $this->post('/sync/usage-apply', ['token' => '00000000-0000-4000-8000-000000000000'])->assertSessionHasErrors('usage_import');
        $this->assertSame(0, ToolUsageLog::query()->count());
    }

    #[Test]
    public function duplicates_are_detected_by_tools_name_yes_date_qty_and_pic(): void
    {
        app(ToolLifeService::class)->recordUsage($this->chuck->id, '2026-11-10', 50_000, 'Budi');

        $preview = $this->preview([
            ['CBP136 1# CUK COLLAR 19.9', '10/11/2026', 50_000, 'BUDI', null],   // sama dengan input aplikasi (PIC beda huruf)
            ['CBP136 1# CUK COLLAR 19.9', '10/11/2026', 50_000, 'Sari', null],   // PIC beda → bukan duplikat
            ['CBP136 2# PPN COLLAR 19.9', '10/11/2026', 50_000, 'Budi', null],   // Tools Name YES beda → bukan duplikat
            ['CBP136 2# PPN COLLAR 19.9', '10/11/2026', 50_000, 'Budi', null],   // sama dengan baris 4 di file
        ]);

        $this->assertSame(2, $preview['summary']['duplicate_rows']);
        $this->assertSame(['existing', null, null, 'file'], array_map(fn ($row) => $row['duplicate']['kind'] ?? null, $preview['rows']));
        $this->assertStringContainsString('baris 4', $preview['rows'][3]['duplicate']['message']);

        // Duplikat tidak dimasukkan kecuali dicentang (baris Excel 5 = duplikat dalam file).
        $this->post('/sync/usage-apply', ['token' => $preview['token'], 'include_duplicates' => [5]])->assertSessionHasNoErrors();

        $this->assertSame(1, session('usage_result')['skipped_duplicates']);
        $this->assertSame(3, ToolUsageLog::query()->whereNotNull('batch_no')->count());
        $this->assertSame(100_000, $this->chuck->fresh()->life_used);
        $this->assertSame(100_000, $this->punch->fresh()->life_used);

        // Upload ulang file yang sama → semua baris kini terdeteksi duplikat.
        $again = $this->preview([['CBP136 1# CUK COLLAR 19.9', '10/11/2026', 50_000, 'Sari', null]]);
        $this->assertSame('existing', $again['rows'][0]['duplicate']['kind']);
        $this->assertStringContainsString('import PLT-', $again['rows'][0]['duplicate']['message']);
    }

    #[Test]
    public function batch_can_be_cancelled_at_once_and_backdated_rows_respect_tool_change(): void
    {
        $life = app(ToolLifeService::class);
        $life->recordUsage($this->chuck->id, '2026-11-01', 150_000, 'Budi');
        $life->reset($this->chuck->id, '2026-11-15', 'Budi');                // Ganti Tool tgl 15

        $preview = $this->preview([
            ['CBP136 1# CUK COLLAR 19.9', '10/11/2026', 40_000, 'Budi', null],   // sebelum ganti tool → tool lama
            ['CBP136 1# CUK COLLAR 19.9', '20/11/2026', 30_000, 'Budi', null],   // tool baru
        ]);

        $this->assertSame([false, true], array_column($preview['rows'], 'counts_toward_life'));
        $this->assertSame('2026-11-15', $preview['tools'][0]['last_reset_date']);
        $this->assertSame(0, $preview['tools'][0]['life_used_before']);

        $this->post('/sync/usage-apply', ['token' => $preview['token']]);
        $batch = session('usage_result')['batch_no'];

        $this->assertSame(30_000, $this->chuck->fresh()->life_used, 'Hanya pemakaian setelah ganti tool');
        $this->assertSame(220_000, ToolLifeService::monthlyActuals($this->chuck->id, ['2026-11'])['2026-11']['actual_qty']);

        $this->get('/tool-usages?batch='.$batch)->assertOk()->assertInertia(fn ($page) => $page
            ->has('logs.data', 2)
            ->where('logs.data.0.batch_no', $batch));

        $this->post("/tool-usages/batches/{$batch}/cancel", ['pic_name' => 'Sari', 'cancel_reason' => 'File salah'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ToolUsageLog::query()->where('batch_no', $batch)->whereNotNull('cancelled_at')->count());
        $this->assertSame(0, $this->chuck->fresh()->life_used);
        $this->assertSame(150_000, ToolLifeService::monthlyActuals($this->chuck->id, ['2026-11'])['2026-11']['actual_qty']);

        $this->post("/tool-usages/batches/{$batch}/cancel", ['pic_name' => 'Sari'])->assertSessionHasErrors('usage');
    }

    #[Test]
    public function wrong_format_file_is_rejected_with_a_clear_message(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['ID', 'Actual Physical Stock'], [1, 5]]);
        $path = tempnam(sys_get_temp_dir(), 'plt').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        $this->post('/sync/usage-preview', ['usage_file' => new UploadedFile($path, 'opname.xlsx', null, null, true)])
            ->assertSessionHasErrors('usage_file');

        $this->get('/sync')->assertOk()->assertInertia(fn ($page) => $page->component('Sync/Index'));
    }
}
