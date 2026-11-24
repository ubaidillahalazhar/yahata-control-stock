<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsageGuideSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['PETUNJUK IMPORT PEMAKAIAN LIFE TIME (DAY) — PT. YAHATA FASTENER INDONESIA'],
            ['Template dibuat: '.now()->format('d/m/Y H:i')],
            [''],
            ['1. Isi sheet "Pemakaian" mulai baris 2. Satu baris = satu kali pemakaian (tanggal boleh acak).'],
            ['2. Kolom WAJIB: Tools Name YES, Tanggal Pemakaian, Jumlah Terpakai (pcs), Nama PIC. Kolom Catatan opsional.'],
            ['3. Tools Name YES harus sama dengan master — pilih dari dropdown / salin dari sheet "Daftar Tool".'],
            ['   (Yang dicocokkan adalah Tools Name YES, BUKAN Tools Name.)'],
            ['4. Tanggal format dd/mm/yyyy dan tidak boleh tanggal yang akan datang.'],
            ['5. Jumlah Terpakai = pcs yang dipukul / diproduksi tool tsb. (bilangan bulat > 0).'],
            ['6. Ganti Tool (reset life) TIDAK lewat Excel — input di aplikasi (halaman detail tool → Ganti Tool).'],
            ['7. Upload di menu Sinkronisasi Excel → 2. Import Pemakaian Life Time (Day) → review → Terapkan.'],
            ['   • Bila ada 1 baris salah, TIDAK ADA yang dimasukkan. Perbaiki file lalu upload ulang file yang sama.'],
            ['   • Baris yang sama persis (Tools Name YES + tanggal + jumlah + PIC) dengan input yang sudah ada'],
            ['     ditandai "kemungkinan duplikat" dan tidak dimasukkan kecuali dicentang saat review.'],
            ['   • Setiap import mendapat nomor batch (PLT-...) dan bisa dibatalkan sekaligus di menu Pemakaian Life Time (Day).'],
            [''],
            ['CONTOH PENGISIAN (jangan disalin ke sheet Pemakaian bila bukan data asli):'],
            ['Tools Name YES', 'Tanggal Pemakaian', 'Jumlah Terpakai (pcs)', 'Nama PIC', 'Catatan'],
            ['CBP136 1# CUK COLLAR 19.9', '12/11/2026', '120000', 'Budi', 'Shift 1'],
            ['CBP136 1# CUK COLLAR 19.9', '19/11/2026', '180000', 'Sari', ''],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 60, 'B' => 20, 'C' => 22, 'D' => 14, 'E' => 14];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            17 => ['font' => ['bold' => true]],
            18 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E293B']]],
        ];
    }
}
