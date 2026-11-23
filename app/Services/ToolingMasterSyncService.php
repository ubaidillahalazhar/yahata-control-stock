<?php

namespace App\Services;

use App\Enums\ImportIssueStatus;
use App\Enums\TransactionType;
use App\Imports\ControlStockSheetImport;
use App\Imports\ToolingMasterImport;
use App\Models\ToolsInventory;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import master tooling dari file `yahata tools (1).xlsx` (sheet "control stock").
 *
 * - Baris 1 = nama kolom, baris 2 = tanggal bulan forecast (M..Q), baris 3 dst = data.
 * - Bulk upsert berdasarkan "Tools Name YES" (kolom H).
 * - Baris yang kolom wajibnya kosong / tidak valid TIDAK masuk tabel utama, melainkan dicatat di
 *   `tool_import_issues` ("Excel Belum Lengkap"). Tool yang sudah ada tetap memakai data lama
 *   (update ditahan). Isi tabel issue diganti setiap import, jadi Excel tidak pernah ditolak mentah.
 * - Current Stock item yang SUDAH ada tidak ditimpa, kecuali opsi "sync stock" aktif
 *   (selisihnya dicatat sebagai OPNAME_ADJUST). Item baru memakai stok dari Excel
 *   sebagai stok awal dan tercatat di audit log.
 */
final class ToolingMasterSyncService
{
    public const TARGET_SHEET = 'control stock';

    public const HEADER_ROW = 1;

    public const MONTH_ROW = 2;

    public const DATA_START_ROW = 3;

    public const DEFAULT_LEAD_TIME_DAYS = 30;

    private const MAX_MESSAGES = 100;

    // Indeks kolom (0-based) sesuai layout Excel.
    private const COL_NO = 0;            // A

    private const COL_CUSTOMER = 1;      // B

    private const COL_MACHINE = 2;       // C

    private const COL_STEP = 3;          // D

    private const COL_TOOLS_NAME = 4;    // E

    private const COL_YES_NAME = 5;      // F

    private const COL_PART_NAME = 6;     // G

    private const COL_TOOLS_NAME_YES = 7; // H

    private const COL_RACK = 8;          // I

    private const COL_PART_NO = 9;       // J

    private const COL_CURRENT_STOCK = 10; // K

    private const COL_LIFE_TIME = 11;    // L

    private const COL_FORECAST_FIRST = 12; // M (s.d. Q = 16)

    private const COL_LEAD_TIME = 39;    // AN

    /**
     * Kolom teks wajib: field => [indeks kolom, huruf kolom, label].
     *
     * @var array<string, array{0: int, 1: string, 2: string}>
     */
    private const REQUIRED_TEXT = [
        'machine_name' => [self::COL_MACHINE, 'C', 'Machine Name'],
        'step' => [self::COL_STEP, 'D', 'Step'],
        'tools_name' => [self::COL_TOOLS_NAME, 'E', 'Tools Name'],
        'yes_name' => [self::COL_YES_NAME, 'F', 'Yes Name'],
        'part_name' => [self::COL_PART_NAME, 'G', 'Part Name'],
        'tools_name_yes' => [self::COL_TOOLS_NAME_YES, 'H', 'Tools Name YES'],
        'rack_location' => [self::COL_RACK, 'I', 'Rack Location'],
        'part_no' => [self::COL_PART_NO, 'J', 'Part No'],
    ];

    /** @var list<array{row: int, tools_name_yes: ?string, problems: list<array<string, string>>, row_data: array<string, mixed>}> */
    private array $issues = [];

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(private readonly StockMutationService $mutations) {}

    /**
     * @return array{
     *     reference_no: string, total_rows: int, created: int, updated: int, skipped: int,
     *     stock_adjusted: int, initial_stock_logged: int, forecast_months: list<?string>,
     *     errors: list<string>, warnings: list<string>
     * }
     */
    public function import(UploadedFile|string $file, bool $syncStock = false, ?string $picName = null): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->issues = [];

        $path = $file instanceof UploadedFile ? (string) $file->getRealPath() : $file;
        $sheet = new ControlStockSheetImport;

        Excel::import(new ToolingMasterImport($sheet, $this->resolveSheetKey($path)), $file);

        $this->assertHeader($sheet->rows[self::HEADER_ROW] ?? []);
        $months = $this->parseForecastMonths($sheet->rows[self::MONTH_ROW] ?? []);
        $items = $this->parseItems($sheet->rows);

        if ($items === [] && $this->issues === []) {
            throw ValidationException::withMessages([
                'master_file' => 'Tidak ada baris data tooling yang valid mulai baris '.self::DATA_START_ROW.'.',
            ]);
        }

        return $this->persist($items, $months, $syncStock, $picName ?: 'IMPORT MASTER');
    }

    /**
     * Pilih sheet bernama "control stock" (case-insensitive). Jika tidak ada, gunakan sheet pertama.
     */
    private function resolveSheetKey(string $path): string|int
    {
        try {
            $reader = IOFactory::createReader(IOFactory::identify($path));

            foreach ($reader->listWorksheetNames($path) as $name) {
                if (mb_strtolower(trim($name)) === self::TARGET_SHEET) {
                    return $name;
                }
            }
        } catch (Throwable) {
            // Biarkan Maatwebsite yang melaporkan file rusak / format tidak dikenali.
        }

        return 0;
    }

    /**
     * @param  array<int, mixed>  $header
     */
    private function assertHeader(array $header): void
    {
        $normalize = static fn (mixed $value): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));

        $isValid = str_contains($normalize($header[self::COL_TOOLS_NAME_YES] ?? ''), 'tools name yes')
            && str_contains($normalize($header[self::COL_LIFE_TIME] ?? ''), 'life time')
            && str_contains($normalize($header[self::COL_CURRENT_STOCK] ?? ''), 'current stock');

        if (! $isValid) {
            throw ValidationException::withMessages([
                'master_file' => 'Format file tidak dikenali. Baris 1 harus berisi header seperti file "yahata tools.xlsx" '
                    .'(H = Tools Name YES, K = Current Stock, L = Life Time, M..Q = Forecast, AN = Lead time Order).',
            ]);
        }
    }

    /**
     * Ambil tanggal bulan forecast di baris 2 (M..Q). Jika tanggal tidak berurutan
     * (mis. Nov-2026, Des-2026, Jan-2026), tahunnya dinaikkan agar kronologis.
     *
     * @param  array<int, mixed>  $row
     * @return list<?string>
     */
    private function parseForecastMonths(array $row): array
    {
        $months = [];
        $corrections = [];
        $previous = null;

        for ($i = 0; $i < ToolingCalculatorService::FORECAST_MONTHS; $i++) {
            $date = $this->toDate($row[self::COL_FORECAST_FIRST + $i] ?? null);

            if ($date !== null && $previous !== null && $date->lessThanOrEqualTo($previous)) {
                $original = $date;

                while ($date->lessThanOrEqualTo($previous)) {
                    $date = $date->addYear();
                }

                $corrections[] = $original->format('d-M-Y').' → '.$date->format('d-M-Y');
            }

            $months[] = $date?->toDateString();
            $previous = $date ?? $previous;
        }

        if ($corrections !== []) {
            $this->warnings[] = 'Tanggal header forecast (baris 2) tidak berurutan, tahun disesuaikan: '.implode(', ', $corrections).'.';
        }

        return $months;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, array<string, mixed>> keyed by tools_name_yes
     */
    private function parseItems(array $rows): array
    {
        $items = [];
        ksort($rows);

        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber < self::DATA_START_ROW || $this->isBlankRow($cells)) {
                continue;
            }

            $text = [];
            $problems = [];

            foreach (self::REQUIRED_TEXT as $field => [$index, $column, $label]) {
                $text[$field] = $this->text($cells[$index] ?? null);

                if ($text[$field] === null) {
                    $problems[] = self::problem($field, $column, $label, 'kosong');
                }
            }

            $toolsNameYes = $text['tools_name_yes'];
            $toolsName = $text['tools_name'];

            // Kosong ≠ 0: stok 0 adalah data valid, sel kosong berarti belum diisi.
            [$currentStock, $stockProblem] = $this->wholeNumber($cells[self::COL_CURRENT_STOCK] ?? null);

            if ($stockProblem === null && $currentStock < 0) {
                $stockProblem = 'tidak boleh negatif';
            }

            if ($stockProblem !== null) {
                $problems[] = self::problem('current_stock', 'K', 'Current Stock', $stockProblem);
            }

            [$lifeTime, $lifeProblem] = $this->wholeNumber($cells[self::COL_LIFE_TIME] ?? null);

            if ($lifeProblem === null && $lifeTime <= 0) {
                $lifeProblem = 'harus lebih dari 0';
            }

            if ($lifeProblem !== null) {
                $problems[] = self::problem('life_time', 'L', 'Life Time', $lifeProblem);
            }

            $forecastCells = array_slice($cells, self::COL_FORECAST_FIRST, ToolingCalculatorService::FORECAST_MONTHS);

            // Forecast dianggap belum diisi bila kelima bulan (M..Q) kosong semua. Nilai 0 tetap valid.
            if (array_filter($forecastCells, fn (mixed $value): bool => $this->text($value) !== null) === []) {
                $problems[] = self::problem('forecasts', 'M–Q', 'Forecast', 'kosong semua (5 bulan)');
            }

            if ($problems !== []) {
                $this->issues[] = [
                    'row' => $rowNumber,
                    'tools_name_yes' => $toolsNameYes,
                    'problems' => $problems,
                    'row_data' => [
                        ...$text,
                        'current_stock' => $this->text($cells[self::COL_CURRENT_STOCK] ?? null),
                        'life_time' => $this->text($cells[self::COL_LIFE_TIME] ?? null),
                        'forecasts' => array_map(fn (mixed $value): ?string => $this->text($value), array_values($forecastCells)),
                    ],
                ];

                continue;
            }

            $leadTime = (int) round(ToolingCalculatorService::toNumber($cells[self::COL_LEAD_TIME] ?? null));

            if ($leadTime <= 0) {
                $leadTime = self::DEFAULT_LEAD_TIME_DAYS;
                $this->warnings[] = "Baris {$rowNumber} ({$toolsNameYes}): Lead time (AN) kosong, dipakai default {$leadTime} hari.";
            }

            $forecasts = ToolingCalculatorService::normalizeForecasts($forecastCells);

            if (isset($items[$toolsNameYes])) {
                $this->warnings[] = sprintf(
                    'Baris %d: "%s" duplikat dengan baris %d — data baris %d yang dipakai.',
                    $rowNumber,
                    $toolsNameYes,
                    $items[$toolsNameYes]['row'],
                    $rowNumber,
                );
            }

            $no = ToolingCalculatorService::toNumber($cells[self::COL_NO] ?? null);

            $items[$toolsNameYes] = [
                'row' => $rowNumber,
                'no' => $no > 0 ? (int) $no : null,
                'customer' => $this->text($cells[self::COL_CUSTOMER] ?? null),
                'machine_name' => $text['machine_name'],
                'step' => $text['step'],
                'tools_name' => $toolsName,
                'yes_name' => $text['yes_name'],
                'part_name' => $text['part_name'],
                'tools_name_yes' => $toolsNameYes,
                'rack_location' => $text['rack_location'],
                'part_no' => $text['part_no'],
                'current_stock' => $currentStock,
                'life_time' => $lifeTime,
                'forecasts' => $forecasts,
                'lead_time_days' => $leadTime,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  list<?string>  $months
     */
    private function persist(array $items, array $months, bool $syncStock, string $picName): array
    {
        $referenceNo = 'IMP-'.now()->format('Ymd-His');

        return DB::transaction(function () use ($items, $months, $syncStock, $picName, $referenceNo): array {
            $names = array_keys($items);

            /** @var \Illuminate\Support\Collection<string, ToolsInventory> $existing */
            $existing = collect();

            foreach (array_chunk($names, 1000) as $chunk) {
                $existing = $existing->union(
                    ToolsInventory::query()->whereIn('tools_name_yes', $chunk)->orderBy('id')->lockForUpdate()->get()->keyBy('tools_name_yes'),
                );
            }

            $now = now();
            $issueSummary = $this->storeIssues($referenceNo, $items, $now);
            $monthsJson = json_encode($months);
            $records = [];

            foreach ($items as $name => $item) {
                $records[] = [
                    'no' => $item['no'],
                    'customer' => $item['customer'],
                    'machine_name' => $item['machine_name'],
                    'step' => $item['step'],
                    'tools_name' => $item['tools_name'],
                    'yes_name' => $item['yes_name'],
                    'part_name' => $item['part_name'],
                    'tools_name_yes' => $name,
                    'rack_location' => $item['rack_location'],
                    'part_no' => $item['part_no'],
                    // Hanya dipakai saat INSERT (item baru); kolom ini tidak ikut di-update.
                    'current_stock' => $existing->has($name) ? (int) $existing[$name]->current_stock : $item['current_stock'],
                    'life_time' => $item['life_time'],
                    'forecasts' => json_encode($item['forecasts']),
                    'forecast_months' => $monthsJson,
                    'lead_time_days' => $item['lead_time_days'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $updateColumns = [
                'no', 'customer', 'machine_name', 'step', 'tools_name', 'yes_name', 'part_name',
                'rack_location', 'part_no', 'life_time', 'forecasts', 'forecast_months', 'lead_time_days', 'updated_at',
            ];

            foreach (array_chunk($records, 500) as $chunk) {
                DB::table('tools_inventory')->upsert($chunk, ['tools_name_yes'], $updateColumns);
            }

            // Stok awal item baru → audit log.
            $newNames = array_values(array_diff($names, $existing->keys()->all()));
            $initialLogged = 0;

            foreach (array_chunk($newNames, 1000) as $chunk) {
                $logs = ToolsInventory::query()
                    ->whereIn('tools_name_yes', $chunk)
                    ->where('current_stock', '>', 0)
                    ->get(['id', 'current_stock'])
                    ->map(fn (ToolsInventory $tool): array => [
                        'tool_id' => $tool->id,
                        'type' => TransactionType::OpnameAdjust->value,
                        'qty_change' => $tool->current_stock,
                        'stock_before' => 0,
                        'stock_after' => $tool->current_stock,
                        'reference_no' => $referenceNo,
                        'pic_name' => $picName,
                        'notes' => 'Stok awal dari import master Excel',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($logs !== []) {
                    DB::table('stock_transactions')->insert($logs);
                    $initialLogged += count($logs);
                }
            }

            // Opsional: samakan stok item lama dengan kolom K Excel.
            $stockAdjusted = 0;

            if ($syncStock) {
                foreach ($existing as $name => $tool) {
                    $target = (int) $items[$name]['current_stock'];
                    $delta = $target - (int) $tool->current_stock;

                    if ($delta === 0) {
                        continue;
                    }

                    $this->mutations->applyToLockedTool($tool, TransactionType::OpnameAdjust, $delta, [
                        'reference_no' => $referenceNo,
                        'pic_name' => $picName,
                        'notes' => sprintf('Sinkronisasi stok dari import master: sistem %d → Excel %d', $tool->current_stock, $target),
                    ]);

                    $stockAdjusted++;
                }
            }

            return [
                'reference_no' => $referenceNo,
                'total_rows' => count($items) + count($this->issues),
                'created' => count($newNames),
                'updated' => $existing->count(),
                'skipped' => count($this->issues),
                'held_new' => $issueSummary[ImportIssueStatus::NewHeld->value],
                'held_update' => $issueSummary[ImportIssueStatus::UpdateHeld->value],
                'held_duplicate' => $issueSummary[ImportIssueStatus::Duplicate->value],
                'stock_adjusted' => $stockAdjusted,
                'initial_stock_logged' => $initialLogged,
                'forecast_months' => $months,
                'errors' => array_slice($this->errors, 0, self::MAX_MESSAGES),
                'warnings' => array_slice($this->warnings, 0, self::MAX_MESSAGES),
            ];
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }

    /**
     * Ganti seluruh isi `tool_import_issues` dengan hasil import ini (dipanggil di dalam transaksi).
     *
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, int> jumlah per status
     */
    private function storeIssues(string $referenceNo, array $items, \DateTimeInterface $now): array
    {
        $summary = array_fill_keys(array_map(fn (ImportIssueStatus $status): string => $status->value, ImportIssueStatus::cases()), 0);
        $names = array_values(array_unique(array_filter(array_column($this->issues, 'tools_name_yes'))));
        $known = collect();

        foreach (array_chunk($names, 1000) as $chunk) {
            $known = $known->union(ToolsInventory::query()->whereIn('tools_name_yes', $chunk)->pluck('id', 'tools_name_yes'));
        }

        DB::table('tool_import_issues')->delete();
        $records = [];

        foreach ($this->issues as $issue) {
            $name = $issue['tools_name_yes'];
            $status = match (true) {
                $name !== null && isset($items[$name]) => ImportIssueStatus::Duplicate,
                $name !== null && $known->has($name) => ImportIssueStatus::UpdateHeld,
                default => ImportIssueStatus::NewHeld,
            };

            $summary[$status->value]++;
            $this->errors[] = sprintf(
                'Baris %d (%s): %s — %s.',
                $issue['row'],
                $name ?? 'tanpa Tools Name YES',
                implode(', ', array_map(fn (array $problem): string => "{$problem['column']} {$problem['label']} {$problem['reason']}", $issue['problems'])),
                mb_strtolower($status->label()),
            );

            $records[] = [
                'reference_no' => $referenceNo,
                'excel_row' => $issue['row'],
                'tools_name_yes' => $name,
                'tool_id' => $name !== null ? $known->get($name) : null,
                'status' => $status->value,
                'problems' => json_encode($issue['problems']),
                'row_data' => json_encode($issue['row_data']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('tool_import_issues')->insert($chunk);
        }

        return $summary;
    }

    /**
     * @return array{field: string, column: string, label: string, reason: string}
     */
    private static function problem(string $field, string $column, string $label, string $reason): array
    {
        return ['field' => $field, 'column' => $column, 'label' => $label, 'reason' => $reason];
    }

    /**
     * Angka bulat dari sel Excel. Sel kosong → [null, 'kosong']; teks bukan angka → [null, 'bukan angka'].
     *
     * @return array{0: int, 1: ?string}
     */
    private function wholeNumber(mixed $value): array
    {
        $text = $this->text($value);

        if ($text === null) {
            return [0, 'kosong'];
        }

        $clean = str_replace([' ', "\u{00A0}"], '', $text);

        if (! is_numeric($clean)) {
            return [0, 'bukan angka'];
        }

        return [(int) round((float) $clean), null];
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Trim teks (termasuk non-breaking space). Angka bulat dikonversi tanpa ".0".
     */
    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }

        $value = (string) $value;
        $text = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $value) ?? trim($value);

        return $text === '' ? null : $text;
    }

    private function toDate(mixed $value): ?CarbonImmutable
    {
        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value)->startOfDay();
            }

            if (is_numeric($value) && (float) $value > 0) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            }

            if (is_string($value) && trim($value) !== '') {
                return CarbonImmutable::parse($value)->startOfDay();
            }
        } catch (Throwable) {
            // Header bulan tidak valid → null (UI akan menampilkan "Bulan n").
        }

        return null;
    }
}
