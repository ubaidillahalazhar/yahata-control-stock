<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Membaca sheet hasil Stock Opname (baris 1 = heading, baris 2 dst = data).
 * Heading di-slug otomatis: "Actual Physical Stock" → actual_physical_stock.
 */
class StockOpnameSheetImport implements ToCollection, WithCalculatedFormulas, WithColumnLimit, WithHeadingRow, WithReadFilter
{
    /** Template opname hanya 10 kolom (A..J); sisakan margin bila user menambah kolom catatan. */
    public const LAST_COLUMN = 'Z';

    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function endColumn(): string
    {
        return self::LAST_COLUMN;
    }

    public function readFilter(): IReadFilter
    {
        return new ColumnLimitReadFilter(self::LAST_COLUMN);
    }

    public function collection(Collection $collection): void
    {
        $this->rows = $collection;
    }
}
