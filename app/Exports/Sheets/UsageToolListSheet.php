<?php

namespace App\Exports\Sheets;

use App\Models\ToolsInventory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Referensi Tools Name YES yang terdaftar (kolom A = sumber dropdown sheet Pemakaian).
 */
class UsageToolListSheet implements FromArray, WithEvents, WithTitle
{
    /**
     * @param  Collection<int, ToolsInventory>  $tools
     */
    public function __construct(private readonly Collection $tools) {}

    public function title(): string
    {
        return 'Daftar Tool';
    }

    public function array(): array
    {
        $rows = [['Tools Name YES', 'Tools Name', 'Machine Name', 'Rack Location', 'Life Time (pcs)', 'Life Terpakai Saat Ini (pcs)', 'Life Terpakai (%)']];

        foreach ($this->tools as $tool) {
            $life = $tool->lifeSummary();
            $rows[] = [
                $tool->tools_name_yes,
                $tool->tools_name,
                $tool->machine_name,
                $tool->rack_location,
                (int) $tool->life_time,
                $life['life_used'],
                $life['life_usage_percent'] / 100,
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = max(2, $this->tools->count() + 1);

                $sheet->getStyle('A1:G1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
                ]);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:G{$last}");
                $sheet->getStyle("E2:F{$last}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("G2:G{$last}")->getNumberFormat()->setFormatCode('0.0%');

                foreach (['A' => 45, 'B' => 22, 'C' => 18, 'D' => 14, 'E' => 16, 'F' => 24, 'G' => 16] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }
}
