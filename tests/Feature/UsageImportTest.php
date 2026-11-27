<?php

namespace Tests\Feature;

use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use App\Services\ToolLifeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Import Pemakaian Life Time (Day) dari export Machine Result.
 */
class UsageImportTest extends TestCase
{
    use RefreshDatabase;

    /** Urutan & judul kolom seperti export Machine Result asli (sebagian). */
    private const HEADINGS = [
        'Machine Group', 'Machine Name', 'Input Date', 'Status', 'Start/End', 'Input User', 'Operator',
        'J/O No.', 'Lot No.', 'Part No.', 'Part Name', 'Shift', "Result Q'ty (Pcs)", "Result Q'ty (Kg)",
    ];

    private const PART = '983951-09011-C';

    /** @var list<string> */
    private array $files = [];

    private ToolsInventory $chuck;

    private ToolsInventory $cutDie;

    private ToolsInventory $otherPart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-24 10:00:00');

        // Penulisan Machine Name di master berbeda urutan & tanda baca — tetap satu mesin.
        $this->chuck = ToolsInventory::factory()->create([
            'machine_name' => 'CBP-136 (BF-01)', 'part_no' => self::PART,
            'tools_name_yes' => 'CBP136 1# CUK COLLAR 19.9', 'tools_name' => 'CHUCK FINGER', 'life_time' => 200_000,
        ]);
        $this->cutDie = ToolsInventory::factory()->create([
            'machine_name' => 'BF-01 CBP-136', 'part_no' => self::PART,
            'tools_name_yes' => 'BF-01 CBP-1361# CUT OF DIE', 'tools_name' => 'CUT OF DIE', 'life_time' => 1_000_000,
        ]);
        // Mesin sama, part lain → tidak bertambah.
        $this->otherPart = ToolsInventory::factory()->create([
            'machine_name' => 'CBP-136 (BF-01)', 'part_no' => 'X4609532',
            'tools_name_yes' => 'CBP136 1# CUK X460', 'tools_name' => 'CHUCK FINGER', 'life_time' => 200_000,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /** Pasangan baris Start + End satu J/O (Operator hanya di baris Start, seperti export asli). */
    private function job(string $jo, string $start, string $end, mixed $qty, string $operator = 'nanda ferry', string $machine = 'CBP-136 (BF-01)', string $part = self::PART, string $inputUser = 'admin prod'): array
    {
        $lot = "LOT-{$jo}";

        return [
            ['BF', $machine, $start, 'Running', 'Start', $inputUser, $operator, $jo, $lot, $part, 'Collar', 'DAY', 0, 0],
            ['BF', $machine, $end, 'Running', 'End', $inputUser, '', $jo, $lot, $part, 'Collar', 'DAY', $qty, 0],
        ];
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  list<list<mixed>>  $above  baris tambahan di atas judul
     */
    private function file(array $rows, array $above = []): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Machine Result');

        foreach ([...$above, self::HEADINGS, ...$rows] as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                if ($value === null) {
                    continue;
                }

                // Input Date di export asli = teks "dd/mm/yyyy HH:MM".
                $cell = $sheet->getCell([$c + 1, $r + 1]);
                is_string($value) ? $cell->setValueExplicit($value, DataType::TYPE_STRING) : $cell->setValue($value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'mr').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        return new UploadedFile($path, 'Machine_Result.xlsx', null, null, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(array $rows, string $period = '2026-11', array $above = []): array
    {
        $this->post('/sync/usage-preview', ['usage_file' => $this->file($rows, $above), 'period' => $period])
            ->assertSessionHasNoErrors();

        return session('usage_preview');
    }

    private function apply(array $preview, array $includeRows = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/sync/usage-apply', ['token' => $preview['token'], 'include_rows' => $includeRows]);
    }

    #[Test]
    public function each_end_row_adds_its_result_to_every_tool_of_that_machine_and_part(): void
    {
        $preview = $this->preview([
            ...$this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 22400),
            ...$this->job('42685-1', '02/11/2026 18:00', '03/11/2026 08:00', 21900, operator: 'IIS '),
            ...$this->job('42686-1', '28/10/2026 08:00', '31/10/2026 08:00', 5000),                       // di luar periode
            ...$this->job('50001-1', '05/11/2026 08:00', '05/11/2026 10:00', 4000, machine: 'NT-3B (GR-02)'), // mesin tidak di master
        ]);

        $this->assertSame(2, $preview['summary']['end_rows']);
        $this->assertSame(0, $preview['summary']['error_rows']);
        $this->assertSame(1, $preview['summary']['outside_period_rows']);
        $this->assertSame(2, $preview['summary']['tools']);
        $this->assertSame('nanda ferry', $preview['rows'][0]['pic_name'], 'Operator dari baris Start pasangannya');
        $this->assertSame('IIS', $preview['rows'][1]['pic_name']);
        $this->assertSame([$this->chuck->id, $this->cutDie->id], $preview['rows'][0]['tool_ids']);
        $this->assertSame([['machine_name' => 'NT-3B (GR-02)', 'part_no' => self::PART, 'reason' => 'Mesin tidak ada di master', 'end_rows' => 1, 'qty' => 4000]], $preview['skipped']);
        $this->assertSame(0, ToolUsageLog::query()->count(), 'Preview tidak menulis data');

        $this->apply($preview)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(44_300, $this->chuck->fresh()->life_used);
        $this->assertSame(44_300, $this->cutDie->fresh()->life_used);
        $this->assertSame(0, $this->otherPart->fresh()->life_used);
        $this->assertSame(4, ToolUsageLog::query()->count());

        $log = ToolUsageLog::query()->where('tool_id', $this->chuck->id)->where('qty', 21_900)->firstOrFail();
        $this->assertSame('2026-11-03', $log->usage_date->toDateString(), 'Tanggal = Input Date baris End');
        $this->assertSame('machine_result', $log->source->value);
        $this->assertSame('2026-11', $log->import_period);
        $this->assertStringContainsString('J/O 42685-1', $log->notes);
        $this->assertSame(22_400, $log->life_used_before);
        $this->assertSame(44_300, $log->life_used_after);

        $actual = ToolLifeService::monthlyActuals($this->chuck->id, ['2026-11']);
        $this->assertSame(44_300, $actual['2026-11']['actual_qty']);
    }

    #[Test]
    public function uploading_the_same_period_again_replaces_the_previous_import(): void
    {
        $first = $this->preview($this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 22400));
        $this->assertNull($first['previous']);
        $this->apply($first)->assertSessionHasNoErrors();
        $firstBatch = ToolUsageLog::query()->value('batch_no');

        // Import periode lain tidak ikut diganti.
        $this->apply($this->preview($this->job('41000-1', '20/10/2026 00:00', '20/10/2026 05:00', 1000), '2026-10'))
            ->assertSessionHasNoErrors();

        $this->travel(1)->minutes();
        $second = $this->preview([
            ...$this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 22400),
            ...$this->job('42685-1', '03/11/2026 00:00', '03/11/2026 01:00', 10000),
        ]);
        $this->assertSame(2, $second['previous']['rows']);
        $this->assertSame([$firstBatch], $second['previous']['batches']);
        $this->assertSame(23_400, $second['tools'][0]['life_used_before']);
        $this->assertSame(33_400, $second['tools'][0]['life_used_after'], 'Import lama periode ini tidak ikut dihitung');

        $this->apply($second)->assertSessionHasNoErrors();

        $this->assertSame(33_400, $this->chuck->fresh()->life_used, 'Tidak terhitung dobel');
        $this->assertSame(2, ToolUsageLog::query()->where('batch_no', $firstBatch)->whereNotNull('cancelled_at')->count());
        $this->assertStringContainsString('Diganti import', ToolUsageLog::query()->where('batch_no', $firstBatch)->value('cancel_reason'));
        $this->assertSame(1, ToolUsageLog::query()->active()->where('import_period', '2026-10')->where('tool_id', $this->chuck->id)->count());
    }

    #[Test]
    public function correction_of_an_imported_row_stays_in_its_period_and_is_replaced_on_reupload(): void
    {
        $this->apply($this->preview($this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 22400)));
        $log = ToolUsageLog::query()->where('tool_id', $this->chuck->id)->firstOrFail();

        $this->post("/tool-usages/{$log->id}/correct", [
            'usage_date' => '2026-11-02', 'qty' => 2240, 'pic_name' => 'Admin', 'reason' => 'salah ketik',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-11', ToolUsageLog::query()->latest('id')->value('import_period'));

        $this->apply($this->preview($this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 2240)))->assertSessionHasNoErrors();

        $this->assertSame(2240, $this->chuck->fresh()->life_used);
    }

    #[Test]
    public function a_bad_row_for_a_master_machine_blocks_the_whole_file_but_unknown_machines_are_ignored(): void
    {
        $this->post('/sync/usage-preview', ['period' => '2026-11', 'usage_file' => $this->file([
            ...$this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 22400),
            ...$this->job('42685-1', '03/11/2026 00:00', '31/02/2026 01:00', 'dua ribu'),
            ...$this->job('42686-1', '04/11/2026 00:00', '04/11/2026 01:00', 500, operator: '', inputUser: ''),
            ...$this->job('50001-1', '05/11/2026 08:00', '05/11/2026 10:00', 'rusak', machine: 'NT-3B (GR-02)'),
        ])])->assertSessionHas('error');

        $preview = session('usage_preview');
        $this->assertNull($preview['token']);
        $this->assertSame(2, $preview['summary']['error_rows']);
        $this->assertSame(['input_date', 'qty'], array_column($preview['rows'][1]['problems'], 'field'));
        $this->assertSame(['pic_name'], array_column($preview['rows'][2]['problems'], 'field'));
        $this->assertSame(1, $preview['summary']['skipped_rows']);
        $this->assertSame(0, ToolUsageLog::query()->count());
    }

    #[Test]
    public function operator_falls_back_to_input_user_when_start_row_has_none(): void
    {
        $preview = $this->preview($this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 100, operator: '', inputUser: 'yossi tera'));

        $this->assertSame('yossi tera', $preview['rows'][0]['pic_name']);
    }

    #[Test]
    public function rows_already_covered_by_a_replace_new_report_are_held_until_included(): void
    {
        // Laporan Ganti Baru chuck tgl 22/11 → pemakaian chuck s.d. 22/11 sudah tercakup counter.
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
            ...$this->job('1-1', '21/11/2026 00:00', '21/11/2026 05:00', 1200), // baris 3 → chuck ditahan
            ...$this->job('2-1', '22/11/2026 00:00', '22/11/2026 05:00', 800),  // baris 5 → chuck ditahan (tanggal sama)
            ...$this->job('3-1', '23/11/2026 00:00', '23/11/2026 05:00', 900),  // baris 7 → OK
        ]);

        $this->assertSame([(string) $this->chuck->id => '22/11/2026'], array_map('strval', $preview['rows'][0]['held_tools']));
        $this->assertSame([], $preview['rows'][2]['held_tools']);
        $this->assertSame(2, $preview['summary']['held_rows']);

        $this->apply($preview, includeRows: [5])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('tool_usage_logs', ['tool_id' => $this->chuck->id, 'qty' => 1200]);
        $this->assertDatabaseHas('tool_usage_logs', ['tool_id' => $this->chuck->id, 'qty' => 800, 'source' => 'machine_result']);
        $this->assertDatabaseHas('tool_usage_logs', ['tool_id' => $this->chuck->id, 'qty' => 900]);
        // Tool lain di mesin yang sama tidak punya laporan → tidak ditahan.
        $this->assertDatabaseHas('tool_usage_logs', ['tool_id' => $this->cutDie->id, 'qty' => 1200]);
        $this->assertSame(900 + 800, $this->chuck->fresh()->life_used, 'Pemakaian bertanggal = Ganti Tool dihitung setelahnya');
    }

    #[Test]
    public function the_same_job_twice_in_one_file_is_held_as_duplicate(): void
    {
        $job = $this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 22400);
        $preview = $this->preview([...$job, $job[1]]);

        $this->assertStringContainsString('baris End 3', $preview['rows'][1]['duplicate']);
        $this->apply($preview)->assertSessionHasNoErrors();
        $this->assertSame(22_400, $this->chuck->fresh()->life_used);
    }

    #[Test]
    public function other_inputs_in_the_period_are_reported_but_not_replaced(): void
    {
        app(ToolLifeService::class)->recordUsage($this->chuck->id, '2026-11-10', 500, 'Budi');

        $preview = $this->preview($this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 1000));
        $this->assertSame(['rows' => 1, 'tools' => 1, 'total_qty' => 500], $preview['other_inputs']);

        $this->apply($preview)->assertSessionHasNoErrors();
        $this->assertSame(1_500, $this->chuck->fresh()->life_used);
    }

    #[Test]
    public function header_row_is_found_below_title_rows(): void
    {
        $preview = $this->preview(
            $this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 100),
            above: [['Machine Result Report'], ['Periode: November 2026'], []],
        );

        $this->assertSame(6, $preview['rows'][0]['row']);
    }

    #[Test]
    public function wrong_file_or_missing_period_is_rejected_with_a_clear_message(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['Tools Name YES', 'Tanggal Pemakaian'], ['X', '01/11/2026']]);
        $path = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        $this->post('/sync/usage-preview', ['period' => '2026-11', 'usage_file' => new UploadedFile($path, 'salah.xlsx', null, null, true)])
            ->assertSessionHasErrors(['usage_file' => 'Format tidak sesuai. Upload export "Machine Result" apa adanya (kolom wajib: Machine Name, Input Date, Start/End, Operator, Part No., Result Q\'ty (Pcs)).']);

        $rows = $this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 100);
        $this->post('/sync/usage-preview', ['usage_file' => $this->file($rows)])->assertSessionHasErrors('period');
        $this->post('/sync/usage-preview', ['usage_file' => $this->file($rows), 'period' => '2026-12'])->assertSessionHasErrors('period');

        $this->post('/sync/usage-preview', ['usage_file' => $this->file($rows), 'period' => '2026-09'])
            ->assertSessionHasErrors('usage_file');
    }

    #[Test]
    public function applied_batch_can_still_be_cancelled_at_once(): void
    {
        $this->apply($this->preview($this->job('42684-1', '02/11/2026 00:00', '02/11/2026 01:00', 22400)));
        $batch = ToolUsageLog::query()->value('batch_no');

        $this->post("/tool-usages/batches/{$batch}/cancel", ['pic_name' => 'Admin'])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->chuck->fresh()->life_used);
        $this->assertSame(0, $this->cutDie->fresh()->life_used);
    }

    #[Test]
    public function the_real_machine_result_export_layout_is_understood(): void
    {
        // Potongan baris asli (judul lengkap 30 kolom, nama operator berspasi di akhir).
        $headings = ['Machine Group', 'Machine Name', 'Input Date', 'Status', 'Start/End', 'Input User', 'Operator', 'J/O No.', 'Lot No.', 'Plan Q\'ty', 'Material Code', 'Material Lot No.', 'Process', 'Customer', 'Customer Name', 'Part No.', 'Part Name', 'Part Size', 'Material', 'Category', 'Surface', 'Unit weight (g)', 'Shift', "Result Q'ty (Pcs)", "Result Q'ty (Kg)", 'Scrap (Kg)', 'NG (Pcs)', 'Hold (Pcs)', 'Remarks', 'Location'];
        $start = ['BF', 'CBP-136 (BF-01)', '02/11/2026 18:00', 'Running', 'Start', 'IIS ', 'IIS ', '42693-1', '26B0006-01/01', 19188, 'W10A1200', 'MA26A0084-001', 'FR', 'AIA', 'PT. AISIN INDONESIA', self::PART, 'Collar', '19.9 x 14', 'SWCH10A', 'COLLAR', 'CR+3 YELLOW', 13.8, 'DAY', 0, 0, 0, 0, 0, '', ''];
        $end = [...$start];
        [$end[2], $end[4], $end[6], $end[23], $end[24], $end[29]] = ['03/11/2026 08:00', 'End', '', 21900, 302.22, 'free'];

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$headings, $start, $end], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'mr').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        $this->post('/sync/usage-preview', ['period' => '2026-11', 'usage_file' => new UploadedFile($path, 'Machine_Result.xlsx', null, null, true)])
            ->assertSessionHasNoErrors();
        $row = session('usage_preview')['rows'][0];

        $this->assertSame(['2026-11-03', 21_900, 'IIS', 2], [$row['usage_date'], $row['qty'], $row['pic_name'], $row['tools_count']]);
    }
}
