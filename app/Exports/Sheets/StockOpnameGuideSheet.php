<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockOpnameGuideSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['PETUNJUK STOCK OPNAME — PT. YAHATA FASTENER INDONESIA'],
            ['Template dibuat: '.now()->format('d/m/Y H:i')],
            [''],
            ['1. Hitung stok fisik tiap item di rak, lalu isi kolom kuning "Actual Physical Stock" (bilangan bulat ≥ 0).'],
            ['2. Kosongkan "Actual Physical Stock" untuk item yang tidak dihitung — item tersebut tidak akan diubah.'],
            ['3. Jangan mengubah kolom "ID" dan "Tools Name YES" (dipakai untuk mencocokkan item).'],
            ['4. "System Stock" adalah stok sistem saat template diunduh (hanya sebagai pembanding).'],
            ['5. Simpan file (.xlsx) lalu upload di menu Sinkronisasi Excel → Upload Hasil Opname.'],
            ['6. Periksa preview selisih, isi nama PIC, lalu klik "Terapkan Hasil Opname".'],
            ['   Stok sistem akan diset sama dengan stok fisik; selisihnya tercatat sebagai OPNAME_ADJUST di Audit Log.'],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 115];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 13]]];
    }
}
