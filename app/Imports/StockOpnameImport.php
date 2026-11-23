<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Hanya membaca sheet pertama (sheet "Stock Opname"); sheet "Petunjuk" diabaikan.
 */
class StockOpnameImport implements WithMultipleSheets, WithReadFilter
{
    public readonly StockOpnameSheetImport $sheet;

    public function __construct()
    {
        $this->sheet = new StockOpnameSheetImport;
    }

    public function readFilter(): IReadFilter
    {
        return $this->sheet->readFilter();
    }

    public function sheets(): array
    {
        return [0 => $this->sheet];
    }
}
