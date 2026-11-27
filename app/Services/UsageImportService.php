<?php

namespace App\Services;

use App\Enums\UsageSource;
use App\Enums\UsageType;
use App\Http\Requests\StoreToolUsageRequest;
use App\Imports\MachineResultImport;
use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import Pemakaian Life Time (Day) dari export "Machine Result" — Upload → Review → Terapkan.
 *
 *  - Hanya baris End yang dihitung (baris Start selalu 0 pcs).
 *  - 1 baris End → SEMUA tool di master dengan Machine Name + Part No. sama bertambah Result Q'ty.
 *    Machine Name dicocokkan tanpa peduli urutan & tanda baca ("CBP-136 (BF-01)" = "BF-01 CBP-136").
 *  - Mesin / part yang tidak ada di master dilewati (ditampilkan saat review), bukan error.
 *  - PIC = Operator dari baris Start pasangannya (Machine Name + J/O No. + Lot No.); cadangan Input User.
 *  - Periode (bulan) dipilih saat upload; hanya baris End bertanggal di bulan itu yang diambil.
 *  - Upload ulang periode yang sama MENGGANTIKAN import sebelumnya (dibatalkan, bukan dihapus).
 *  - Baris salah untuk mesin yang ada di master → tidak ada yang dimasukkan (all-or-nothing).
 *  - Tool + tanggal ≤ counter laporan kerusakan Ganti Baru ditahan, kecuali dicentang saat review.
 */
final class UsageImportService
{
    /** Batas baris data file (Start + End). */
    public const MAX_ROWS = 20000;

    private const CACHE_PREFIX = 'usage-import:';

    private const CACHE_TTL_HOURS = 2;

    private const PIC_MAX = 100;

    /** Baris judul dicari di N baris pertama. */
    private const HEADER_SCAN_ROWS = 20;

    /** Kolom yang dibaca (judul dinormalisasi: huruf kecil, tanpa spasi / tanda baca). */
    private const COLUMNS = [
        'machine_group' => 'machinegroup',
        'machine_name' => 'machinename',
        'input_date' => 'inputdate',
        'start_end' => 'startend',
        'input_user' => 'inputuser',
        'operator' => 'operator',
        'jo_no' => 'jono',
        'lot_no' => 'lotno',
        'part_no' => 'partno',
        'shift' => 'shift',
        'qty' => 'resultqtypcs',
    ];

    private const REQUIRED = ['machine_name', 'input_date', 'start_end', 'operator', 'part_no', 'qty'];

    private const REQUIRED_LABELS = "Machine Name, Input Date, Start/End, Operator, Part No., Result Q'ty (Pcs)";

    public function __construct(private readonly ToolLifeService $life) {}

    /**
     * @param  string  $period  Y-m
     * @return array<string, mixed>
     */
    public function preview(UploadedFile|string $file, string $period): array
    {
        $import = new MachineResultImport;
        Excel::import($import, $file);

        [$headerIndex, $columns] = self::findHeader($import->rows);
        $periodStart = CarbonImmutable::createFromFormat('!Y-m', $period)->startOfMonth();
        $periodLabel = $periodStart->locale('id')->translatedFormat('F Y');
        $today = CarbonImmutable::today();

        $tools = ToolsInventory::query()->get(['id', 'tools_name_yes', 'tools_name', 'machine_name', 'part_no', 'life_time', 'life_used']);
        $toolsByKey = $tools->groupBy(fn (ToolsInventory $tool): string => self::machineKey($tool->machine_name).'|'.self::partKey($tool->part_no));
        $masterMachines = $tools->map(fn (ToolsInventory $tool): string => self::machineKey($tool->machine_name))->unique()->flip();

        // Pass 1: baris data + Operator dari baris Start (di export, Operator hanya terisi di baris Start).
        $records = [];
        $operators = [];

        foreach (array_slice($import->rows, $headerIndex + 1, null, true) as $index => $raw) {
            $cells = array_map(fn (int $col): mixed => $raw[$col] ?? null, $columns);

            if (array_filter($cells, fn (mixed $value): bool => self::text($value) !== null) === []) {
                continue; // baris kosong
            }

            if (count($records) >= self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'usage_file' => 'Maksimal '.number_format(self::MAX_ROWS, 0, ',', '.').' baris per file.',
                ]);
            }

            $record = ['row' => $index + 1, 'cells' => $cells, 'kind' => mb_strtolower((string) self::text($cells['start_end']))];
            $records[] = $record;
            $operator = self::text($cells['operator']);

            if ($record['kind'] === 'start' && $operator !== null) {
                $operators[self::jobKey($cells)] = $operator;
            }
        }

        // Pass 2: baris End.
        $parsed = [];
        $skipped = [];
        $seenJobs = [];
        $outsidePeriod = 0;
        $zeroRows = 0;

        foreach ($records as $record) {
            if ($record['kind'] !== 'end') {
                continue;
            }

            $cells = $record['cells'];
            $machine = self::text($cells['machine_name']);
            $partNo = self::text($cells['part_no']);
            $matched = $toolsByKey->get(self::machineKey($machine).'|'.self::partKey($partNo));
            [$at, $dateReason] = self::dateTime($cells['input_date']);

            if ($matched === null) {
                // Di luar master: dilewati. Hitung hanya baris di periode ini (atau tanggal tak terbaca).
                if ($at === null || $at->format('Y-m') === $period) {
                    $key = mb_strtoupper(($machine ?? '(kosong)').' | '.($partNo ?? '(kosong)'));
                    $skipped[$key] ??= [
                        'machine_name' => $machine ?? '(kosong)',
                        'part_no' => $partNo ?? '(kosong)',
                        'reason' => $masterMachines->has(self::machineKey($machine))
                            ? 'Part No. tidak ada di master untuk mesin ini'
                            : 'Mesin tidak ada di master',
                        'end_rows' => 0,
                        'qty' => 0,
                    ];
                    $skipped[$key]['end_rows']++;
                    $skipped[$key]['qty'] += self::quantity($cells['qty'])[0] ?? 0;
                }

                continue;
            }

            if ($at !== null && $at->format('Y-m') !== $period) {
                $outsidePeriod++;

                continue;
            }

            [$qty, $qtyReason] = self::quantity($cells['qty']);

            if ($qtyReason === null && $qty === 0) {
                $zeroRows++;

                continue;
            }

            $pic = self::text($cells['operator']) ?? $operators[self::jobKey($cells)] ?? self::text($cells['input_user']);
            $problems = [];

            if ($dateReason !== null) {
                $problems[] = self::problem('input_date', 'Input Date', $dateReason);
            } elseif ($at->startOfDay()->greaterThan($today)) {
                $problems[] = self::problem('input_date', 'Input Date', 'tidak boleh tanggal yang akan datang');
            }

            if ($qtyReason !== null) {
                $problems[] = self::problem('qty', "Result Q'ty (Pcs)", $qtyReason);
            }

            if ($pic === null) {
                $problems[] = self::problem('pic_name', 'Operator', 'kosong (baris Start pasangannya & Input User juga kosong)');
            } elseif (mb_strlen($pic) > self::PIC_MAX) {
                $problems[] = self::problem('pic_name', 'Operator', 'maksimal '.self::PIC_MAX.' karakter');
            }

            $row = [
                'row' => $record['row'],
                'machine_name' => $machine,
                'part_no' => $partNo,
                'jo_no' => self::text($cells['jo_no']),
                'lot_no' => self::text($cells['lot_no']),
                'shift' => self::text($cells['shift']),
                'usage_date' => $at?->toDateString(),
                'usage_date_display' => $at?->format('d/m/Y H:i') ?? self::text($cells['input_date']),
                'qty' => $qty,
                'qty_display' => $qty === null ? self::text($cells['qty']) : null,
                'pic_name' => $pic,
                'tool_ids' => $matched->pluck('id')->all(),
                'problems' => $problems,
                'duplicate' => null,
                'held_tools' => [],
            ];

            if ($problems === [] && $row['jo_no'] !== null) {
                $jobKey = self::jobKey($cells);

                if (isset($seenJobs[$jobKey])) {
                    $row['duplicate'] = "J/O No. + Lot No. sama dengan baris End {$seenJobs[$jobKey]} di file ini";
                } else {
                    $seenJobs[$jobKey] = $row['row'];
                }
            }

            $parsed[] = $row;
        }

        if ($parsed === []) {
            throw ValidationException::withMessages([
                'usage_file' => $records === []
                    ? 'Tidak ada baris data pada file.'
                    : "Tidak ada baris End untuk mesin di master pada periode {$periodLabel}. Cek periode yang dipilih.",
            ]);
        }

        $parsed = self::markCounterOverlaps($parsed);
        $valid = array_values(array_filter($parsed, fn (array $row): bool => $row['problems'] === []));
        $errorRows = count($parsed) - count($valid);

        // Simulasi life per tool: semua baris valid kecuali yang ditahan (default preview).
        $byTool = [];

        foreach ($valid as $row) {
            foreach ($row['tool_ids'] as $toolId) {
                $byTool[$toolId] ??= [];

                if ($row['duplicate'] === null && ! isset($row['held_tools'][$toolId])) {
                    $byTool[$toolId][] = ['usage_date' => $row['usage_date'], 'qty' => $row['qty']];
                }
            }
        }

        $projection = $byTool === [] ? [] : $this->life->projectPeriod($byTool, $period);
        $toolsById = $tools->keyBy('id');

        foreach ($parsed as &$row) {
            $row['tools_count'] = count($row['tool_ids']);
            $row['counts_toward_life'] = $row['usage_date'] !== null && array_reduce(
                $row['tool_ids'],
                fn (bool $carry, int $toolId): bool => $carry || ($projection[$toolId]['last_reset_date'] ?? null) === null
                    || $row['usage_date'] >= $projection[$toolId]['last_reset_date'],
                false,
            );
        }
        unset($row);

        $previous = self::previousImport($period);
        $token = (string) Str::uuid();
        $fileName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);

        if ($errorRows === 0) {
            Cache::put(self::CACHE_PREFIX.$token, [
                'file_name' => $fileName,
                'period' => $period,
                'rows' => $valid,
            ], now()->addHours(self::CACHE_TTL_HOURS));
        }

        return [
            'token' => $errorRows === 0 ? $token : null,
            'file_name' => $fileName,
            'period' => $period,
            'period_label' => $periodLabel,
            'rows' => $parsed,
            'skipped' => array_values($skipped),
            'previous' => $previous,
            'other_inputs' => self::otherInputs($period, array_keys($byTool)),
            'tools' => array_values(array_map(fn (int $toolId): array => [
                'tool_id' => $toolId,
                'tools_name_yes' => $toolsById[$toolId]->tools_name_yes,
                'tools_name' => $toolsById[$toolId]->tools_name,
                'machine_name' => $toolsById[$toolId]->machine_name,
                'life_time' => (int) $toolsById[$toolId]->life_time,
                'life_used_before' => $projection[$toolId]['life_used_before'],
                'life_used_after' => $projection[$toolId]['life_used_after'],
                'last_reset_date' => $projection[$toolId]['last_reset_date'],
            ], array_keys($byTool))),
            'summary' => [
                'end_rows' => count($parsed),
                'valid_rows' => count($valid),
                'error_rows' => $errorRows,
                'held_rows' => count(array_filter($valid, fn (array $row): bool => $row['duplicate'] !== null || $row['held_tools'] !== [])),
                'skipped_rows' => array_sum(array_column($skipped, 'end_rows')),
                'outside_period_rows' => $outsidePeriod,
                'zero_rows' => $zeroRows,
                'tools' => count($byTool),
            ],
        ];
    }

    /**
     * @param  list<int>  $includeRows  nomor baris Excel yang ditahan tetapi tetap dimasukkan
     * @return array{batch_no: string, period: string, inserted: int, tools: int, total_qty: int, replaced: int, replaced_batches: list<string>, skipped_held: int, file_name: string}
     */
    public function apply(string $token, array $includeRows, string $appliedBy): array
    {
        $lock = Cache::lock(self::CACHE_PREFIX.'lock:'.$token, 120);

        if (! $lock->get()) {
            throw ValidationException::withMessages(['usage_import' => 'Import ini sedang diproses. Tunggu sebentar.']);
        }

        try {
            $cached = Cache::get(self::CACHE_PREFIX.$token);

            if ($cached === null) {
                throw ValidationException::withMessages([
                    'usage_import' => 'Preview sudah diterapkan atau kedaluwarsa ('.self::CACHE_TTL_HOURS.' jam). Upload ulang file.',
                ]);
            }

            $include = array_flip(array_map('intval', $includeRows));

            // Cek ulang laporan Ganti Baru terbaru (mungkin ada laporan baru sejak preview dibuat).
            $flaggedAtPreview = array_map(fn (array $row): array => array_keys($row['held_tools']), $cached['rows']);
            $rows = self::markCounterOverlaps(array_map(fn (array $row): array => [...$row, 'held_tools' => []], $cached['rows']));

            foreach ($rows as $i => $row) {
                if (array_diff(array_keys($row['held_tools']), $flaggedAtPreview[$i]) !== [] && ! isset($include[$row['row']])) {
                    throw ValidationException::withMessages([
                        'usage_import' => 'Sejak preview dibuat, ada laporan kerusakan Ganti Baru baru untuk tool di file ini. Upload ulang file untuk review ulang.',
                    ]);
                }
            }

            $entries = [];
            $skippedHeld = 0;

            foreach ($rows as $row) {
                $included = isset($include[$row['row']]);

                if ($row['duplicate'] !== null && ! $included) {
                    $skippedHeld += count($row['tool_ids']);

                    continue;
                }

                foreach ($row['tool_ids'] as $toolId) {
                    if (isset($row['held_tools'][$toolId]) && ! $included) {
                        $skippedHeld++;

                        continue;
                    }

                    $entries[] = [
                        'tool_id' => $toolId,
                        'usage_date' => $row['usage_date'],
                        'qty' => $row['qty'],
                        'pic_name' => $row['pic_name'],
                        'notes' => self::notes($row),
                    ];
                }
            }

            if ($entries === []) {
                throw ValidationException::withMessages(['usage_import' => 'Tidak ada baris yang dipilih untuk dimasukkan.']);
            }

            $result = $this->life->importPeriod($entries, $this->batchNo(), $cached['period'], $appliedBy);
            Cache::forget(self::CACHE_PREFIX.$token);

            return [
                ...$result,
                'period' => $cached['period'],
                'skipped_held' => $skippedHeld,
                'file_name' => $cached['file_name'],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Tahan tool + tanggal ≤ pengecekan counter terakhir (laporan kerusakan Ganti Baru):
     * pemakaian sampai tanggal itu sudah tercakup angka counter → kemungkinan terhitung dobel.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function markCounterOverlaps(array $rows): array
    {
        $toolIds = [];

        foreach ($rows as $row) {
            if ($row['problems'] === []) {
                array_push($toolIds, ...$row['tool_ids']);
            }
        }

        $checkpoints = ShotCountService::latestCheckpoints(array_values(array_unique($toolIds)));

        if ($checkpoints === []) {
            return $rows;
        }

        foreach ($rows as &$row) {
            if ($row['problems'] !== []) {
                continue;
            }

            foreach ($row['tool_ids'] as $toolId) {
                $checkpoint = $checkpoints[$toolId] ?? null;

                if ($checkpoint !== null && $row['usage_date'] <= $checkpoint['date']) {
                    $row['held_tools'][$toolId] = CarbonImmutable::parse($checkpoint['date'])->format('d/m/Y');
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Import Machine Result aktif untuk periode ini (akan digantikan bila diterapkan).
     *
     * @return array{rows: int, total_qty: int, batches: list<string>}|null
     */
    private static function previousImport(string $period): ?array
    {
        $query = ToolUsageLog::query()->active()
            ->where('source', UsageSource::MachineResult->value)
            ->where('import_period', $period);

        $rows = (clone $query)->count();

        if ($rows === 0) {
            return null;
        }

        return [
            'rows' => $rows,
            'total_qty' => (int) (clone $query)->sum('qty'),
            'batches' => (clone $query)->distinct()->orderBy('batch_no')->pluck('batch_no')->filter()->values()->all(),
        ];
    }

    /**
     * Input pemakaian lain (manual / template lama) di periode ini untuk tool di file — tidak ikut diganti.
     *
     * @param  list<int>  $toolIds
     * @return array{rows: int, tools: int, total_qty: int}|null
     */
    private static function otherInputs(string $period, array $toolIds): ?array
    {
        if ($toolIds === []) {
            return null;
        }

        $from = CarbonImmutable::createFromFormat('!Y-m', $period)->startOfMonth();
        $query = ToolUsageLog::query()->active()
            ->where('type', UsageType::Usage->value)
            ->where('source', '<>', UsageSource::MachineResult->value)
            ->whereIn('tool_id', $toolIds)
            // Batas atas eksklusif: kolom date bisa tersimpan sebagai "Y-m-d 00:00:00".
            ->where('usage_date', '>=', $from->toDateString())
            ->where('usage_date', '<', $from->addMonth()->toDateString());

        $rows = (clone $query)->count();

        return $rows === 0 ? null : [
            'rows' => $rows,
            'tools' => (clone $query)->distinct()->count('tool_id'),
            'total_qty' => (int) (clone $query)->sum('qty'),
        ];
    }

    /**
     * Cari baris judul (posisinya bisa bergeser) & indeks kolom yang dibaca.
     *
     * @param  list<list<mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    private static function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, self::HEADER_SCAN_ROWS, true) as $index => $row) {
            $headings = [];

            foreach ($row as $col => $value) {
                $key = self::headingKey($value);

                if ($key !== '' && ! isset($headings[$key])) {
                    $headings[$key] = $col;
                }
            }

            if (! isset($headings['machinename'], $headings['resultqtypcs'])) {
                continue;
            }

            $columns = [];

            foreach (self::COLUMNS as $field => $heading) {
                if (isset($headings[$heading])) {
                    $columns[$field] = $headings[$heading];
                }
            }

            $missing = array_diff(self::REQUIRED, array_keys($columns));

            if ($missing !== []) {
                break;
            }

            // Kolom opsional yang tidak ada → selalu null.
            foreach (array_keys(self::COLUMNS) as $field) {
                $columns[$field] ??= PHP_INT_MAX;
            }

            return [$index, $columns];
        }

        throw ValidationException::withMessages([
            'usage_file' => 'Format tidak sesuai. Upload export "Machine Result" apa adanya (kolom wajib: '.self::REQUIRED_LABELS.').',
        ]);
    }

    private static function headingKey(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $value));
    }

    /**
     * Machine Name tanpa peduli urutan & tanda baca: "CBP-136 (BF-01)" = "BF-01 CBP-136".
     */
    public static function machineKey(?string $name): string
    {
        $tokens = preg_split('/[^A-Z0-9]+/', mb_strtoupper((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        sort($tokens);

        return implode(' ', $tokens);
    }

    private static function partKey(?string $partNo): string
    {
        return preg_replace('/\s+/u', '', mb_strtoupper(trim((string) $partNo)));
    }

    /** Pasangan Start ↔ End: Machine Name + J/O No. + Lot No. */
    private static function jobKey(array $cells): string
    {
        return implode('|', [
            self::machineKey(self::text($cells['machine_name'])),
            mb_strtoupper((string) self::text($cells['jo_no'])),
            mb_strtoupper((string) self::text($cells['lot_no'])),
        ]);
    }

    private static function notes(array $row): string
    {
        return implode(' · ', array_filter([
            'Machine Result',
            $row['machine_name'],
            $row['usage_date_display'],
            $row['jo_no'] ? 'J/O '.$row['jo_no'] : null,
            $row['lot_no'] ? 'Lot '.$row['lot_no'] : null,
            $row['shift'],
        ]));
    }

    private function batchNo(): string
    {
        $base = 'PLT-'.now()->format('Ymd-His');
        $batchNo = $base;
        $suffix = 2;

        while (ToolUsageLog::query()->where('batch_no', $batchNo)->exists()) {
            $batchNo = $base.'-'.$suffix++;
        }

        return $batchNo;
    }

    /**
     * Input Date: "02/11/2026 01:00" (teks dd/mm/yyyy) atau sel tanggal Excel (angka serial).
     *
     * @return array{0: ?CarbonImmutable, 1: ?string}
     */
    private static function dateTime(mixed $value): array
    {
        if ($value instanceof \DateTimeInterface) {
            return [CarbonImmutable::instance($value), null];
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return [null, 'kosong'];
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $serial = (float) $value;

            if ($serial < 1 || $serial > 2958465) {
                return [null, 'bukan tanggal'];
            }

            try {
                return [CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($serial)), null];
            } catch (Throwable) {
                return [null, 'bukan tanggal'];
            }
        }

        $text = preg_replace('/\s+/', ' ', trim((string) $value));

        foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y', 'j/n/Y G:i', 'j/n/Y H:i', 'j/n/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd-m-Y H:i', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text);
            } catch (Throwable) {
                continue;
            }

            // Bandingkan balik agar tanggal tidak valid (31/02) tidak "meluber" ke bulan berikutnya.
            if ($date instanceof CarbonImmutable && $date->format($format) === $text) {
                return [$date, null];
            }
        }

        return [null, 'format tidak dikenali (gunakan dd/mm/yyyy jam:menit)'];
    }

    /**
     * Bilangan bulat ≥ 0. Teks "22.400" / "22,400" dibaca sebagai 22400 (pemisah ribuan).
     *
     * @return array{0: ?int, 1: ?string}
     */
    private static function quantity(mixed $value): array
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return [null, 'kosong'];
        }

        if (is_string($value)) {
            $clean = str_replace([' ', "\u{00A0}"], '', trim($value));

            if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $clean) === 1) {
                $clean = str_replace(['.', ','], '', $clean);
            }

            $value = $clean;
        }

        if (! is_numeric($value)) {
            return [null, 'bukan angka'];
        }

        $number = (float) $value;

        if (floor($number) != $number) {
            return [null, 'harus bilangan bulat'];
        }

        if ($number < 0) {
            return [null, 'tidak boleh minus'];
        }

        if ($number > StoreToolUsageRequest::MAX_QTY) {
            return [null, 'terlalu besar (maks. '.number_format(StoreToolUsageRequest::MAX_QTY, 0, ',', '.').')'];
        }

        return [(int) $number, null];
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @return array{field: string, label: string, reason: string}
     */
    private static function problem(string $field, string $label, string $reason): array
    {
        return ['field' => $field, 'label' => $label, 'reason' => $reason];
    }
}
