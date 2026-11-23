<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

/**
 * Membaca sheet "control stock" mentah (kolom A..AN), termasuk 2 baris header.
 *
 * Sel berformula tidak dihitung ulang: yang diambil adalah nilai cache terakhir dari Excel,
 * sehingga import cepat & tidak gagal karena rumus yang tidak didukung PhpSpreadsheet.
 * Interpretasi baris (header, data, validasi) dilakukan di ToolingMasterSyncService.
 */
class ControlStockSheetImport implements OnEachRow, WithColumnLimit, WithReadFilter
{
    public const LAST_COLUMN = 'AN';

    /** @var array<int, array<int, mixed>> nomor baris Excel (1-based) => [indeks kolom 0-based => nilai] */
    public array $rows = [];

    /** Batasi iterasi per baris sampai kolom AN (bukan sampai kolom terakhir sheet, bisa XFD). */
    public function endColumn(): string
    {
        return self::LAST_COLUMN;
    }

    /** Sel di kanan kolom AN tidak dimuat sama sekali. */
    public function readFilter(): IReadFilter
    {
        return new ColumnLimitReadFilter(self::LAST_COLUMN);
    }

    public function onRow(Row $row): void
    {
        $worksheet = $row->getDelegate()->getWorksheet();
        $rowIndex = $row->getRowIndex();
        $lastIndex = Coordinate::columnIndexFromString(self::LAST_COLUMN);
        $values = array_fill(0, $lastIndex, null);

        for ($column = 1; $column <= $lastIndex; $column++) {
            $coordinate = Coordinate::stringFromColumnIndex($column).$rowIndex;

            if (! $worksheet->cellExists($coordinate)) {
                continue;
            }

            $cell = $worksheet->getCell($coordinate);
            $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();

            if ($value instanceof RichText) {
                $value = $value->getPlainText();
            }

            $values[$column - 1] = $value;
        }

        $this->rows[$rowIndex] = $values;
    }
}
