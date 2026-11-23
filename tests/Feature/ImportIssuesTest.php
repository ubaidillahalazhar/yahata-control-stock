<?php

namespace Tests\Feature;

use App\Models\ToolImportIssue;
use App\Models\ToolsInventory;
use App\Services\ToolingMasterSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportIssuesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * Baris lengkap (kolom A..Q). Override per kolom huruf, null = sel kosong.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(string $name, array $overrides = []): array
    {
        return [
            'A' => 1, 'B' => 'PT. AISIN', 'C' => 'BF-01', 'D' => '1#', 'E' => 'PUNCH PIN', 'F' => 'PPN',
            'G' => 'COLLAR 19.9', 'H' => $name, 'I' => 'D6', 'J' => '983951', 'K' => 4, 'L' => 200000,
            'M' => 1000, 'N' => 1000, 'O' => 1000, 'P' => 1000, 'Q' => 0,
            ...$overrides,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function excel(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('control stock');
        $sheet->setCellValue('H1', 'Tools Name YES');
        $sheet->setCellValue('K1', 'Current Stock');
        $sheet->setCellValue('L1', 'Life Time');

        foreach ($rows as $index => $row) {
            foreach ($row as $column => $value) {
                if ($value !== null) {
                    $sheet->setCellValue($column.($index + 3), $value);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'yht').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $this->files[] = $path;
    }

    private function import(array $rows): array
    {
        return app(ToolingMasterSyncService::class)->import($this->excel($rows));
    }

    #[Test]
    public function incomplete_new_rows_are_held_and_never_reach_the_main_table(): void
    {
        $result = $this->import([
            $this->row('TOOL OK'),
            $this->row('NO RACK', ['I' => null, 'J' => null]),
            $this->row('NO LIFE', ['L' => null]),
            $this->row('NO NAME', ['H' => null]),
        ]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['held_new']);
        $this->assertSame(['TOOL OK'], ToolsInventory::query()->pluck('tools_name_yes')->all());

        $issue = ToolImportIssue::query()->where('tools_name_yes', 'NO RACK')->firstOrFail();
        $this->assertSame(['rack_location', 'part_no'], array_column($issue->problems, 'field'));
        $this->assertSame(4, $issue->excel_row);
        $this->assertNull(ToolImportIssue::query()->where('excel_row', 6)->value('tools_name_yes'));
    }

    #[Test]
    public function blank_is_not_zero_for_stock_and_forecast(): void
    {
        $this->import([
            $this->row('STOCK ZERO', ['K' => 0]),
            $this->row('FORECAST ZERO', ['M' => 0, 'N' => 0, 'O' => 0, 'P' => 0, 'Q' => 0]),
            $this->row('ONE MONTH', ['M' => null, 'N' => null, 'O' => 500, 'P' => null, 'Q' => null]),
            $this->row('STOCK BLANK', ['K' => null]),
            $this->row('FORECAST BLANK', ['M' => null, 'N' => null, 'O' => null, 'P' => null, 'Q' => null]),
            $this->row('BAD NUMBERS', ['K' => -1, 'L' => 'abc']),
        ]);

        $this->assertEqualsCanonicalizing(
            ['STOCK ZERO', 'FORECAST ZERO', 'ONE MONTH'],
            ToolsInventory::query()->pluck('tools_name_yes')->all(),
        );

        $problems = ToolImportIssue::query()->get()->mapWithKeys(fn (ToolImportIssue $issue): array => [
            $issue->tools_name_yes => array_column($issue->problems, 'reason', 'field'),
        ]);

        $this->assertSame(['current_stock' => 'kosong'], $problems['STOCK BLANK']);
        $this->assertSame(['forecasts' => 'kosong semua (5 bulan)'], $problems['FORECAST BLANK']);
        $this->assertSame(['current_stock' => 'tidak boleh negatif', 'life_time' => 'bukan angka'], $problems['BAD NUMBERS']);
    }

    #[Test]
    public function existing_tool_keeps_old_data_when_new_excel_row_is_incomplete(): void
    {
        $this->import([$this->row('TOOL A', ['I' => 'D6', 'L' => 200000])]);
        $tool = ToolsInventory::query()->where('tools_name_yes', 'TOOL A')->firstOrFail();

        $result = $this->import([$this->row('TOOL A', ['I' => null, 'L' => 999999])]);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['held_update']);
        $this->assertSame('D6', $tool->fresh()->rack_location, 'Data lama tetap dipakai');
        $this->assertSame(200000, $tool->fresh()->life_time, 'Update ditahan');

        $issue = ToolImportIssue::query()->sole();
        $this->assertSame('UPDATE_HELD', $issue->status->value);
        $this->assertSame($tool->id, $issue->tool_id);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('importIssues.total', 1)
            ->where('importIssues.update', 1)
            ->where('heldToolIds', [$tool->id]));

        // Excel diperbaiki & upload ulang → issue hilang, update diterapkan.
        $fixed = $this->import([$this->row('TOOL A', ['I' => 'E1', 'L' => 999999])]);

        $this->assertSame(1, $fixed['updated']);
        $this->assertSame(0, ToolImportIssue::query()->count());
        $this->assertSame('E1', $tool->fresh()->rack_location);
    }

    #[Test]
    public function sheets_formatted_up_to_column_xfd_are_read_without_exhausting_memory(): void
    {
        $rows = array_map(fn (int $i): array => $this->row("TOOL {$i}"), range(1, 300));
        $path = $this->excel($rows);

        // Simulasi file client: warna diterapkan sampai kolom terakhir (XFD) di setiap baris.
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        for ($row = 1; $row <= 302; $row++) {
            $sheet->getCell("XFD{$row}")->getStyle()->getFill()->setFillType('solid')->getStartColor()->setRGB('FFFF00');
        }

        (new Xlsx($spreadsheet))->save($path);
        $info = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->listWorksheetInfo($path)[0];
        $this->assertSame('XFD', $info['lastColumnLetter']);

        $before = memory_get_usage(true);
        $result = app(ToolingMasterSyncService::class)->import($path);

        $this->assertSame(300, $result['created']);
        $this->assertLessThan(128 * 1024 * 1024, memory_get_peak_usage(true) - $before);
    }

    #[Test]
    public function an_excel_with_only_incomplete_rows_is_not_rejected(): void
    {
        $result = $this->import([$this->row('X', ['F' => null]), $this->row('Y', ['G' => null])]);

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(0, ToolsInventory::query()->count());

        $this->get('/import-issues')->assertOk()->assertInertia(fn ($page) => $page
            ->component('ImportIssues/Index')
            ->where('counts.total', 2)
            ->has('issues', 2));

        $this->get('/import-issues?field=yes_name')->assertInertia(fn ($page) => $page->has('issues', 1));
    }
}
