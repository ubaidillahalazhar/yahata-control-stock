<?php

namespace App\Exports;

use App\Exports\Sheets\StockOpnameGuideSheet;
use App\Exports\Sheets\StockOpnameSheet;
use App\Models\ToolsInventory;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template Stock Opname: sheet 1 = daftar tooling + kolom "Actual Physical Stock", sheet 2 = petunjuk.
 */
class StockOpnameTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $tools = ToolsInventory::query()->ordered()->get();

        return [
            new StockOpnameSheet($tools),
            new StockOpnameGuideSheet,
        ];
    }
}
