<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Wrapper agar hanya sheet "control stock" (atau sheet pertama) yang dibaca.
 */
class ToolingMasterImport implements WithMultipleSheets
{
    public function __construct(
        public readonly ControlStockSheetImport $sheet,
        private readonly string|int $sheetKey = 0,
    ) {}

    public function sheets(): array
    {
        return [$this->sheetKey => $this->sheet];
    }
}
