<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Wrapper agar hanya sheet "control stock" (atau sheet pertama) yang dibaca.
 */
class ToolingMasterImport implements WithMultipleSheets, WithReadFilter
{
    public function __construct(
        public readonly ControlStockSheetImport $sheet,
        private readonly string|int $sheetKey = 0,
    ) {}

    /** Filter dipasang di level file (dibaca ReaderFactory), berlaku untuk semua sheet. */
    public function readFilter(): IReadFilter
    {
        return $this->sheet->readFilter();
    }

    public function sheets(): array
    {
        return [$this->sheetKey => $this->sheet];
    }
}
