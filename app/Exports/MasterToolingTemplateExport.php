<?php

namespace App\Exports;

use App\Services\ToolingCalculatorService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Template upload "Import Master Tooling": layout sheet "control stock" kolom A (no) s.d.
 * AN (Lead time Order), 2 baris header, dengan contoh data no 2–5 dari file Excel Yahata.
 *
 * Kolom input diisi nilai; kolom hitungan (R, S..AM) berisi rumus Excel yang sama dengan
 * aplikasi, jadi user langsung melihat hasilnya. Saat di-import, kolom rumus diabaikan.
 */
class MasterToolingTemplateExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private const LAST_COLUMN = 'AN';

    private const FIRST_DATA_ROW = 3;

    /**
     * Contoh no 2–5 dari "yahata tools.xlsx": [A..L, forecast M..Q, AN].
     * No 2 memang tanpa Yes Name di file asli → ditandai di template (wajib diisi).
     */
    private const EXAMPLES = [
        [[2, 'PT. AISIN INDONESIA', 'BF-01 CBP-136', '1#', 'CUT OF DIE', null, 'COLLAR 19.9', 'BF-01 CBP-1361# CUT OF DIE983951-09011-CCOLLAR 19.9', 'D6', '983951-09011-C', 1, 1_000_000], [410_568, 393_279, 393_279, 393_279, 0], 30],
        [[3, 'PT. AISIN INDONESIA', 'BF-01 CBP-136', '1#', 'CHUCK FINGER', 'CUK', 'COLLAR 19.9', 'CBP136 1# CUK COLLAR 19.9', 'D6', '983951-09011-C', 1, 200_000], [410_568, 393_279, 393_279, 393_279, 0], 30],
        [[4, 'PT. AISIN INDONESIA', 'BF-01 CBP-136', '2#', 'CHUCK FINGER', 'CUK', 'COLLAR 19.9', 'CBP136 2# CUK COLLAR 19.9', 'D6', '983951-09011-C', 4, 200_000], [410_568, 393_279, 393_279, 393_279, 0], 30],
        [[5, 'PT. AISIN INDONESIA', 'BF-01 CBP-136', '3#', 'CHUCK FINGER', 'CUK', 'COLLAR 19.9', 'CBP136 3# CUK COLLAR 19.9', 'D6', '983951-09011-C', 2, 200_000], [410_568, 393_279, 393_279, 393_279, 0], 30],
    ];

    public function title(): string
    {
        return 'control stock';
    }

    public function array(): array
    {
        // Baris 2: 5 bulan forecast mulai bulan ini.
        $monthCells = array_map(
            fn (int $i): float => ExcelDate::PHPToExcel(now()->startOfMonth()->addMonthsNoOverflow($i)),
            range(0, ToolingCalculatorService::FORECAST_MONTHS - 1),
        );

        $rows = [ControlStockExport::MASTER_HEADER, ControlStockExport::masterMonthRow($monthCells)];

        foreach (self::EXAMPLES as $index => [$master, $forecasts, $leadTime]) {
            $rows[] = [...$master, ...$forecasts, ...self::formulas(self::FIRST_DATA_ROW + $index), $leadTime];
        }

        return $rows;
    }

    /**
     * Rumus kolom R..AM untuk baris $r (identik ToolingCalculatorService).
     *
     * @return list<string>
     */
    private static function formulas(int $r): array
    {
        $need = array_map(fn (string $col): string => "=ROUND({$col}{$r}/\$L{$r}, 2)", ['M', 'N', 'O', 'P', 'Q']);
        $balance = array_map(fn (string $col): string => "=ROUND(\$K{$r}-{$col}{$r}, 2)", ['S', 'T', 'U', 'V', 'W']);

        return [
            "=ROUND(AVERAGEIF(M{$r}:Q{$r},\">0\"), 0)",       // R  Forecast Average
            ...$need,                                          // S..W
            ...$balance,                                       // X..AB
            "=ROUND(R{$r}/L{$r}, 2)",                          // AC A. Tooling Need
            "=ROUNDUP((AC{$r}/20)*AN{$r},0)",                  // AD B
            "=AC{$r}*20%",                                     // AE C
            "=IF(L{$r}<=400000,1,0)",                          // AF D
            "=ROUNDUP(AC{$r}+AF{$r},0)",                       // AG E. Min Stock
            "=ROUNDUP(AD{$r}+AE{$r}+AF{$r},0)",                // AH Max Total StandBy
            "=IF(K{$r}>=AG{$r},\"SECURE\",\"ORDER\")",         // AI Info
            "=AG{$r}*L{$r}",                                   // AJ
            "=AH{$r}*L{$r}",                                   // AK
            "=IF(AJ{$r}>=R{$r},\"OK\",\"CEK FORMULA\")",       // AL
            "=IF(AK{$r}>=R{$r},\"OK\",\"CEK FORMULA\")",       // AM
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = self::FIRST_DATA_ROW + count(self::EXAMPLES) - 1;

                ControlStockExport::applyMasterLayout($sheet, self::LAST_COLUMN, $lastRow);

                // Kolom hitungan (rumus) diberi latar abu-abu: tidak perlu diisi manual.
                foreach (['R', 'S:AB', 'AC:AM'] as $range) {
                    [$from, $to] = array_pad(explode(':', $range), 2, $range);
                    $sheet->getStyle("{$from}".self::FIRST_DATA_ROW.":{$to}{$lastRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                }

                $sheet->getStyle('K'.self::FIRST_DATA_ROW.":Q{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Contoh no 2 di file asli tanpa Yes Name → tandai: baris seperti ini masuk "Excel Belum Lengkap".
                foreach (self::EXAMPLES as $index => [$master]) {
                    if ($master[5] === null) {
                        $cell = 'F'.(self::FIRST_DATA_ROW + $index);
                        $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');
                        $sheet->getComment($cell)->getText()->createText(
                            'Yes Name wajib diisi. Baris tanpa Yes Name tidak masuk dashboard dan tampil di menu "Excel Belum Lengkap".',
                        );
                    }
                }
            },
        ];
    }
}
