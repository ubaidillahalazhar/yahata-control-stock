<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Imports\StockOpnameImport;
use App\Models\ToolsInventory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Workflow Stock Opname:
 *  1. preview(): baca file hasil opname → bandingkan Actual Physical Stock vs stok sistem (tanpa menulis DB).
 *  2. apply():   terapkan stok fisik dalam SATU transaksi + lockForUpdate, selisih dicatat sebagai OPNAME_ADJUST.
 */
final class StockOpnameService
{
    private const MAX_ERRORS = 200;

    public function __construct(private readonly StockMutationService $mutations) {}

    /**
     * @return array{file_name: string, rows: list<array<string, mixed>>, errors: list<string>, summary: array<string, int>}
     */
    public function preview(UploadedFile|string $file): array
    {
        $import = new StockOpnameImport;
        Excel::import($import, $file);

        /** @var Collection<int, Collection<string, mixed>> $rows */
        $rows = $import->sheet->rows;
        $first = $rows->first();

        if ($first === null || ! $first->has('actual_physical_stock') || (! $first->has('id') && ! $first->has('tools_name_yes'))) {
            throw ValidationException::withMessages([
                'opname_file' => 'Format tidak sesuai. Gunakan file dari tombol "Download Template Opname" '
                    .'(wajib ada kolom "ID" / "Tools Name YES" dan "Actual Physical Stock").',
            ]);
        }

        $tools = ToolsInventory::query()->get(['id', 'tools_name_yes', 'machine_name', 'rack_location', 'current_stock']);
        $byId = $tools->keyBy('id');
        $byName = $tools->keyBy(fn (ToolsInventory $tool): string => mb_strtolower($tool->tools_name_yes));

        $result = [];
        $errors = [];
        $seen = [];
        $skippedBlank = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // baris 1 = heading
            $name = trim((string) ($row->get('tools_name_yes') ?? ''));
            $rawId = $row->get('id');
            $rawActual = $row->get('actual_physical_stock');

            if (($rawId === null || $rawId === '') && $name === '') {
                continue; // baris kosong
            }

            if ($rawActual === null || trim((string) $rawActual) === '') {
                $skippedBlank++;

                continue;
            }

            $tool = (is_numeric($rawId) ? $byId->get((int) $rawId) : null)
                ?? ($name !== '' ? $byName->get(mb_strtolower($name)) : null);

            if ($tool === null) {
                $errors[] = "Baris {$rowNumber}: item \"".($name ?: "ID {$rawId}").'" tidak ditemukan di master.';

                continue;
            }

            $actual = $this->wholeNumber($rawActual);

            if ($actual === null || $actual < 0) {
                $errors[] = "Baris {$rowNumber} ({$tool->tools_name_yes}): Actual Physical Stock \"{$rawActual}\" harus bilangan bulat ≥ 0.";

                continue;
            }

            if (isset($seen[$tool->id])) {
                $errors[] = "Baris {$rowNumber} ({$tool->tools_name_yes}): duplikat dengan baris {$seen[$tool->id]} — diabaikan.";

                continue;
            }

            $seen[$tool->id] = $rowNumber;
            $templateStock = $this->wholeNumber($row->get('system_stock'));
            $current = (int) $tool->current_stock;

            $result[] = [
                'row' => $rowNumber,
                'tool_id' => $tool->id,
                'tools_name_yes' => $tool->tools_name_yes,
                'machine_name' => $tool->machine_name,
                'rack_location' => $tool->rack_location,
                'template_system_stock' => $templateStock,
                'current_stock' => $current,
                'actual_stock' => $actual,
                'difference' => $actual - $current,
                'stock_moved' => $templateStock !== null && $templateStock !== $current,
            ];
        }

        $differences = array_column($result, 'difference');

        return [
            'file_name' => $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file),
            'rows' => $result,
            'errors' => array_slice($errors, 0, self::MAX_ERRORS),
            'summary' => [
                'rows_with_input' => count($result),
                'matched' => count(array_filter($differences, fn (int $d): bool => $d === 0)),
                'with_difference' => count(array_filter($differences, fn (int $d): bool => $d !== 0)),
                'total_plus' => array_sum(array_filter($differences, fn (int $d): bool => $d > 0)),
                'total_minus' => abs(array_sum(array_filter($differences, fn (int $d): bool => $d < 0))),
                'stock_moved' => count(array_filter($result, fn (array $r): bool => $r['stock_moved'])),
                'skipped_blank' => $skippedBlank,
                'error_count' => count($errors),
                'not_counted' => max(0, $tools->count() - count($result)),
            ],
        ];
    }

    /**
     * @param  list<array{tool_id: int, actual_stock: int, template_system_stock?: ?int}>  $items
     * @return array<string, mixed>
     */
    public function apply(array $items, string $picName, ?string $notes = null, ?string $referenceNo = null): array
    {
        $referenceNo = filled($referenceNo) ? trim($referenceNo) : 'OPN-'.now()->format('Ymd-His');

        return DB::transaction(function () use ($items, $picName, $notes, $referenceNo): array {
            $itemsById = collect($items)->keyBy(fn (array $item): int => (int) $item['tool_id']);

            // Lock berurutan berdasarkan ID untuk menghindari deadlock antar sesi opname.
            $tools = ToolsInventory::query()
                ->whereIn('id', $itemsById->keys()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $adjusted = 0;
            $matched = 0;
            $plus = 0;
            $minus = 0;

            foreach ($tools as $tool) {
                $item = $itemsById[$tool->id];
                $actual = (int) $item['actual_stock'];
                $before = (int) $tool->current_stock;
                $delta = $actual - $before;

                if ($delta === 0) {
                    $matched++;

                    continue;
                }

                $noteParts = ["Stock opname: sistem {$before} → fisik {$actual}"];
                $templateStock = $item['template_system_stock'] ?? null;

                if ($templateStock !== null && (int) $templateStock !== $before) {
                    $noteParts[] = "stok di template {$templateStock} (ada mutasi sebelum upload)";
                }

                if (filled($notes)) {
                    $noteParts[] = trim($notes);
                }

                $this->mutations->applyToLockedTool($tool, TransactionType::OpnameAdjust, $delta, [
                    'reference_no' => $referenceNo,
                    'pic_name' => $picName,
                    'notes' => implode(' · ', $noteParts),
                ]);

                $adjusted++;
                $delta > 0 ? $plus += $delta : $minus += abs($delta);
            }

            return [
                'reference_no' => $referenceNo,
                'processed' => $tools->count(),
                'adjusted' => $adjusted,
                'matched' => $matched,
                'total_plus' => $plus,
                'total_minus' => $minus,
                'pic_name' => $picName,
                'applied_at' => now()->format('d/m/Y H:i'),
            ];
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }

    private function wholeNumber(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $clean = is_string($value) ? str_replace([' ', "\u{00A0}"], '', trim($value)) : $value;

        if (! is_numeric($clean) || floor((float) $clean) != (float) $clean) {
            return null;
        }

        return (int) $clean;
    }
}
