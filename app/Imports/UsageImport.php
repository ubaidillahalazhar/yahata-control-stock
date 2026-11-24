<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Membaca sheet pertama ("Pemakaian") template Pemakaian Life Time (Day).
 * Baris 1 = heading (di-slug: "Tools Name YES" → tools_name_yes), baris 2 dst = data.
 * Sheet "Daftar Tool" & "Petunjuk" diabaikan.
 */
class UsageImport implements ToCollection, WithCalculatedFormulas, WithColumnLimit, WithHeadingRow, WithMultipleSheets, WithReadFilter
{
    /** Template 5 kolom (A..E); sisakan margin bila user menambah kolom. */
    public const LAST_COLUMN = 'J';

    /** @var Collection<int, Collection<string, mixed>> */
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function sheets(): array
    {
        return [0 => $this];
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
