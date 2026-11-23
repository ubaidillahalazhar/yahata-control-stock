<?php

namespace App\Exports\Sheets;

use App\Models\ToolsInventory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockOpnameSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    /** Kolom "Actual Physical Stock". */
    public const ACTUAL_COLUMN = 'I';

    /**
     * @param  Collection<int, ToolsInventory>  $tools
     */
    public function __construct(private readonly Collection $tools) {}

    public function title(): string
    {
        return 'Stock Opname';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tools Name YES',
            'Machine Name',
            'Step',
            'Tools Name',
            'Rack Location',
            'Part No.',
            'System Stock',
            'Actual Physical Stock',
            'Catatan',
        ];
    }

    public function array(): array
    {
        return $this->tools->map(fn (ToolsInventory $tool): array => [
            $tool->id,
            $tool->tools_name_yes,
            $tool->machine_name,
            $tool->step,
            $tool->tools_name,
            $tool->rack_location,
            $tool->part_no,
            (int) $tool->current_stock,
            null,
            null,
        ])->all();
    }

    public function columnWidths(): array
    {
        return ['A' => 8, 'B' => 44, 'C' => 18, 'D' => 10, 'E' => 24, 'F' => 13, 'G' => 18, 'H' => 13, 'I' => 21, 'J' => 32];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(2, $this->tools->count() + 1);
                $actual = self::ACTUAL_COLUMN;

                $sheet->freezePane('C2');
                $sheet->setAutoFilter("A1:J{$lastRow}");
                $sheet->getRowDimension(1)->setRowHeight(24);

                $sheet->getStyle("A2:A{$lastRow}")->getFont()->getColor()->setRGB('94A3B8');
                $sheet->getStyle("H2:H{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
                $sheet->getStyle("{$actual}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
                $sheet->getStyle("{$actual}2:{$actual}{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF9C3');
                $sheet->getStyle("{$actual}2:{$actual}{$lastRow}")->getFont()->setBold(true);
                $sheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_WHOLE);
                $validation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
                $validation->setFormula1('0');
                $validation->setAllowBlank(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Input tidak valid');
                $validation->setError('Isi dengan bilangan bulat ≥ 0 (jumlah fisik di rak).');

                for ($row = 2; $row <= $lastRow; $row++) {
                    $sheet->getCell("{$actual}{$row}")->setDataValidation(clone $validation);
                }
            },
        ];
    }
}
