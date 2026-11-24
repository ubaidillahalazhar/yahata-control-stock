<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Hanya membaca sheet pertama (sheet "Stock Opname"); sheet "Petunjuk" diabaikan.
 */
class StockOpnameImport implements WithMultipleSheets
{
    public readonly StockOpnameSheetImport $sheet;

    public function __construct()
    {
        $this->sheet = new StockOpnameSheetImport;
    }

    public function sheets(): array
    {
        return [0 => $this->sheet];
    }
}
