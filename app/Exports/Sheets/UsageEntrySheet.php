<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet input pemakaian: hanya heading, tanpa contoh data (contoh ada di sheet Petunjuk)
 * supaya baris contoh tidak ikut ter-import dan menambah life time.
 */
class UsageEntrySheet implements FromArray, WithEvents, WithTitle
{
    /** Heading baris 1 — dipakai juga oleh UsageImportService (di-slug). */
    public const HEADINGS = ['Tools Name YES', 'Tanggal Pemakaian', 'Jumlah Terpakai (pcs)', 'Nama PIC', 'Catatan'];

    /** Jumlah baris yang diberi dropdown / validasi. */
    public const INPUT_ROWS = 2000;

    public function __construct(private readonly int $toolCount) {}

    public function title(): string
    {
        return 'Pemakaian';
    }

    public function array(): array
    {
        return [self::HEADINGS];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = self::INPUT_ROWS + 1;

                $sheet->getStyle('A1:E1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                // Kolom opsional (Catatan) dibedakan warnanya.
                $sheet->getStyle('E1')->getFill()->getStartColor()->setRGB('64748B');
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->freezePane('A2');

                foreach (['A' => 45, 'B' => 20, 'C' => 22, 'D' => 22, 'E' => 40] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $sheet->getStyle("B2:B{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                $sheet->getStyle("C2:C{$last}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("A1:E{$last}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

                if ($this->toolCount > 0) {
                    $list = $this->validation(DataValidation::TYPE_LIST, 'Tools Name YES', 'Pilih dari daftar (sheet "Daftar Tool").');
                    $list->setShowDropDown(true); // true = TAMPILKAN panah dropdown (penamaan PhpSpreadsheet terbalik)
                    $list->setFormula1("'Daftar Tool'!\$A\$2:\$A\$".($this->toolCount + 1));
                    $sheet->setDataValidation("A2:A{$last}", $list);
                }

                $date = $this->validation(DataValidation::TYPE_DATE, 'Tanggal Pemakaian', 'Format dd/mm/yyyy, tidak boleh tanggal yang akan datang.');
                $date->setOperator(DataValidation::OPERATOR_LESSTHANOREQUAL);
                $date->setFormula1('TODAY()');
                $sheet->setDataValidation("B2:B{$last}", $date);

                $qty = $this->validation(DataValidation::TYPE_WHOLE, 'Jumlah Terpakai', 'Bilangan bulat lebih dari 0 (pcs).');
                $qty->setOperator(DataValidation::OPERATOR_GREATERTHAN);
                $qty->setFormula1('0');
                $sheet->setDataValidation("C2:C{$last}", $qty);
            },
        ];
    }

    private function validation(string $type, string $title, string $message): DataValidation
    {
        $validation = new DataValidation;
        $validation->setType($type);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowInputMessage(true);
        $validation->setErrorTitle($title);
        $validation->setError($message);
        $validation->setPromptTitle($title);
        $validation->setPrompt($message);

        return $validation;
    }
}
