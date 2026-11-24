<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Membaca sheet hasil Stock Opname (baris 1 = heading, baris 2 dst = data).
 * Heading di-slug otomatis: "Actual Physical Stock" → actual_physical_stock.
 */
class StockOpnameSheetImport implements ToCollection, WithCalculatedFormulas, WithHeadingRow
{
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function collection(Collection $collection): void
    {
        $this->rows = $collection;
    }
}
