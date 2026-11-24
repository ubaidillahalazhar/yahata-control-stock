<?php

namespace App\Exports;

use App\Models\ToolsInventory;
use App\Services\ToolingCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export dengan layout identik sheet "control stock" (A..AN, 2 baris header).
 * Kolom kalkulasi berisi nilai hasil ToolingCalculatorService. File ini dapat
 * di-import kembali sebagai master (round-trip).
 */
class ControlStockExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private const LAST_COLUMN = 'AN';

    /** Kolom header yang di-merge vertikal (baris 1-2). */
    private const VERTICAL_MERGES = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'R',
        'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN',
    ];

    /** Grup 5 bulan yang di-merge horizontal pada baris 1. */
    private const MONTH_GROUPS = [['M', 'Q'], ['S', 'W'], ['X', 'AB']];

    /** @var Collection<int, ToolsInventory> */
    private Collection $tools;

    public function __construct()
    {
        $this->tools = ToolsInventory::query()->ordered()->get();
    }

    public function title(): string
    {
        return 'control stock';
    }

    public function array(): array
    {
        $months = $this->tools->first(fn (ToolsInventory $tool): bool => ! empty($tool->forecast_months))?->forecast_months ?? [];
        $monthCells = [];

        for ($i = 0; $i < ToolingCalculatorService::FORECAST_MONTHS; $i++) {
            $monthCells[] = isset($months[$i]) ? ExcelDate::PHPToExcel(CarbonImmutable::parse($months[$i])) : 'Bulan '.($i + 1);
        }

        $header = [
            'no', 'Customer', 'Machine Name', 'Step', 'Tools Name', 'Yes Name', 'Part Name', 'Tools Name YES',
            'Rack Location', 'Part No.', 'Current Stock', 'Life Time',
            'Forecast', null, null, null, null,
            'Forecast Average',
            'Tooling Need', null, null, null, null,
            'Tooling Balanced Stock', null, null, null, null,
            'A. Tooling Need', "B. Safety Stock \nLead Time tooling", "C. Safety stock \nFluktuasi (20%)",
            "D. Safety stock + 1 untuk \nlife time < 500.000 Pcs", 'E. Min Stock', "Max\nTotal StandBy", 'Info',
            "Est Prod Qty \n(Min Stock)", "Est Prod Qty \n(Stand By Stock)", "KOREKSI \nMIN STOCK", "KOREKSI \nSTAND BY STOCK",
            "Lead time Order \n(Day)",
        ];

        $monthRow = array_merge(
            array_fill(0, 12, null),
            $monthCells,
            [null],
            $monthCells,
            $monthCells,
            array_fill(0, 12, null),
        );

        $rows = [$header, $monthRow];

        foreach ($this->tools as $tool) {
            $calc = $tool->calculation();

            $rows[] = array_merge(
                [
                    $tool->no, $tool->customer, $tool->machine_name, $tool->step, $tool->tools_name, $tool->yes_name,
                    $tool->part_name, $tool->tools_name_yes, $tool->rack_location, $tool->part_no,
                    (int) $tool->current_stock, (int) $tool->life_time,
                ],
                $calc['forecasts'],
                [$calc['forecast_avg']],
                $calc['monthly_need'],
                $calc['monthly_balance'],
                [
                    $calc['tooling_need_a'],
                    $calc['safety_stock_lead_time_b'],
                    $calc['safety_stock_fluct_c'],
                    $calc['safety_stock_add_d'],
                    $calc['min_stock_e'],
                    $calc['max_total_stand_by'],
                    $calc['status_info'],
                    $calc['est_prod_qty_min'],
                    $calc['est_prod_qty_stand_by'],
                    $calc['koreksi_min_stock'],
                    $calc['koreksi_stand_by'],
                    (int) $tool->lead_time_days,
                ],
            );
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COLUMN;
                $lastRow = max(3, $this->tools->count() + 2);

                foreach (self::VERTICAL_MERGES as $column) {
                    $sheet->mergeCells("{$column}1:{$column}2");
                }

                foreach (self::MONTH_GROUPS as [$from, $to]) {
                    $sheet->mergeCells("{$from}1:{$to}1");
                    $sheet->getStyle("{$from}2:{$to}2")->getNumberFormat()->setFormatCode('mmm-yy');
                }

                $sheet->getStyle("A1:{$last}2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(42);
                $sheet->getStyle("A1:{$last}{$lastRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
                $sheet->freezePane('I3');

                for ($index = 1; $index <= Coordinate::columnIndexFromString($last); $index++) {
                    $column = Coordinate::stringFromColumnIndex($index);
                    $sheet->getColumnDimension($column)->setWidth(match ($column) {
                        'B' => 22, 'C' => 16, 'E' => 22, 'G' => 14, 'H' => 40, 'J' => 17,
                        default => 11,
                    });
                }

                // Warna status (kolom AI) & hasil koreksi (AL, AM).
                for ($row = 3; $row <= $lastRow; $row++) {
                    foreach (['AI', 'AL', 'AM'] as $column) {
                        $value = (string) $sheet->getCell("{$column}{$row}")->getValue();
                        $color = in_array($value, ['ORDER', 'CEK FORMULA'], true) ? 'DC2626' : '059669';
                        $sheet->getStyle("{$column}{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
                    }
                }
            },
        ];
    }
}
