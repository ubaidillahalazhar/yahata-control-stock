<?php

namespace App\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Hanya memuat sel sampai kolom tertentu.
 *
 * File Excel dari user sering "terpakai" sampai kolom XFD (16.384 kolom) karena format / warna
 * diterapkan ke seluruh baris. Tanpa filter ini, setiap baris dibaca sampai XFD dan memori habis
 * walau datanya hanya ratusan baris.
 */
final class ColumnLimitReadFilter implements IReadFilter
{
    private readonly int $lastColumnIndex;

    public function __construct(string $lastColumn)
    {
        $this->lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
    }

    /**
     * @param  string  $columnAddress
     * @param  int  $row
     * @param  string  $worksheetName
     */
    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return Coordinate::columnIndexFromString((string) $columnAddress) <= $this->lastColumnIndex;
    }
}
