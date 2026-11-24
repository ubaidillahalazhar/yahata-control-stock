<?php

namespace App\Exports;

use App\Exports\Sheets\UsageEntrySheet;
use App\Exports\Sheets\UsageGuideSheet;
use App\Exports\Sheets\UsageToolListSheet;
use App\Models\ToolsInventory;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template import Pemakaian Life Time (Day):
 *  1. "Pemakaian"   — diisi user (sheet pertama, satu-satunya yang dibaca saat upload).
 *  2. "Daftar Tool" — semua Tools Name YES terdaftar (sumber dropdown kolom A).
 *  3. "Petunjuk"    — aturan pengisian + contoh.
 */
class UsageTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $tools = ToolsInventory::query()->ordered()->get();

        return [
            new UsageEntrySheet($tools->count()),
            new UsageToolListSheet($tools),
            new UsageGuideSheet,
        ];
    }
}
