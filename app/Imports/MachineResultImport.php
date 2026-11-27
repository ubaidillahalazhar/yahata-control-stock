<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Membaca sheet pertama export "Machine Result" apa adanya (tanpa heading row):
 * baris judul dicari oleh UsageImportService karena posisinya bisa bergeser.
 */
class MachineResultImport implements ToArray, WithMultipleSheets
{
    /** @var list<list<mixed>> */
    public array $rows = [];

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function array(array $array): void
    {
        $this->rows = array_values($array);
    }
}
