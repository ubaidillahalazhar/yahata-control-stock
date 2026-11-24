<?php

namespace App\Exports;

use App\Models\StockTransaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditLogExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private readonly array $filters) {}

    public function query(): Builder
    {
        return StockTransaction::query()
            ->with('tool:id,tools_name_yes,tools_name,machine_name,rack_location')
            ->filter($this->filters)
            ->orderByDesc('id');
    }

    public function title(): string
    {
        return 'Audit Log';
    }

    public function headings(): array
    {
        return ['Waktu', 'Jenis', 'Tools Name YES', 'Tools Name', 'Machine Name', 'Rack', 'Qty', 'Stok Sebelum', 'Stok Sesudah', 'No. Referensi', 'PIC / Operator', 'Catatan'];
    }

    /**
     * @param  StockTransaction  $row
     */
    public function map($row): array
    {
        return [
            $row->created_at?->format('d/m/Y H:i:s'),
            $row->type->value,
            $row->tool?->tools_name_yes,
            $row->tool?->tools_name,
            $row->tool?->machine_name,
            $row->tool?->rack_location,
            $row->qty_change,
            $row->stock_before,
            $row->stock_after,
            $row->reference_no,
            $row->pic_name,
            $row->notes,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
