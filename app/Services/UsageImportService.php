<?php

namespace App\Services;

use App\Enums\UsageType;
use App\Http\Requests\StoreToolUsageRequest;
use App\Imports\UsageImport;
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
 * Import "Pemakaian Life Time (Day)" dari Excel — Upload → Review → Terapkan.
 *
 *  1. preview(): baca & validasi semua baris, deteksi duplikat, simulasi life (TANPA menulis DB).
 *     Hasil baris valid disimpan di cache dengan token; client hanya mengirim token saat apply.
 *  2. apply():   all-or-nothing — bila ada 1 baris salah, tidak ada yang masuk. Semua baris
 *     masuk dalam satu transaksi dengan nomor batch (PLT-...) agar bisa dibatalkan sekaligus.
 *
 * Tool dicocokkan HANYA dengan "Tools Name YES" (bukan Tools Name), tanpa membedakan huruf
 * besar/kecil & spasi berlebih. Duplikat = Tools Name YES + tanggal + jumlah + PIC sama.
 */
final class UsageImportService
{
    public const MAX_ROWS = 5000;

    private const CACHE_PREFIX = 'usage-import:';

    private const CACHE_TTL_HOURS = 2;

    private const PIC_MAX = 100;

    private const NOTES_MAX = 1000;

    /** Heading wajib (sudah di-slug oleh WithHeadingRow). */
    private const REQUIRED_HEADINGS = ['tools_name_yes', 'tanggal_pemakaian', 'jumlah_terpakai_pcs', 'nama_pic'];

    public function __construct(private readonly ToolLifeService $life) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(UploadedFile|string $file): array
    {
        $import = new UsageImport;
        Excel::import($import, $file);

        $rows = $import->rows;
        $headings = array_keys(($rows->first() ?? collect())->toArray());

        // Toleransi heading "Jumlah Terpakai" tanpa "(pcs)".
        if (in_array('jumlah_terpakai', $headings, true)) {
            $headings[] = 'jumlah_terpakai_pcs';
        }

        $missing = array_diff(self::REQUIRED_HEADINGS, $headings);

        if ($rows->isEmpty() || $missing !== []) {
            throw ValidationException::withMessages([
                'usage_file' => 'Format tidak sesuai. Gunakan file dari tombol "Download Template Pemakaian" '
                    .'(kolom wajib: Tools Name YES, Tanggal Pemakaian, Jumlah Terpakai (pcs), Nama PIC).',
            ]);
        }

        $tools = ToolsInventory::query()->get(['id', 'tools_name_yes', 'tools_name', 'machine_name', 'rack_location', 'life_time', 'life_used']);
        $byName = $tools->keyBy(fn (ToolsInventory $tool): string => self::nameKey($tool->tools_name_yes));
        $today = CarbonImmutable::today();

        $parsed = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $cells = [
                'tools_name_yes' => self::text($row->get('tools_name_yes')),
                'usage_date' => $row->get('tanggal_pemakaian'),
                'qty' => $row->get('jumlah_terpakai_pcs') ?? $row->get('jumlah_terpakai'),
                'pic_name' => self::text($row->get('nama_pic')),
                'notes' => self::text($row->get('catatan')),
            ];

            if ($cells['tools_name_yes'] === null && self::text($cells['usage_date']) === null
                && self::text($cells['qty']) === null && $cells['pic_name'] === null && $cells['notes'] === null) {
                continue; // baris kosong
            }

            if (count($parsed) >= self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'usage_file' => 'Maksimal '.number_format(self::MAX_ROWS, 0, ',', '.').' baris per file. Pecah file menjadi beberapa bagian.',
                ]);
            }

            $problems = [];
            $tool = null;

            if ($cells['tools_name_yes'] === null) {
                $problems[] = self::problem('tools_name_yes', 'Tools Name YES', 'kosong');
            } elseif (($tool = $byName->get(self::nameKey($cells['tools_name_yes']))) === null) {
                $problems[] = self::problem('tools_name_yes', 'Tools Name YES', 'tidak terdaftar di master (cek sheet "Daftar Tool")');
            }

            [$date, $dateReason] = self::date($cells['usage_date']);

            if ($dateReason !== null) {
                $problems[] = self::problem('usage_date', 'Tanggal Pemakaian', $dateReason);
            } elseif ($date->greaterThan($today)) {
                $problems[] = self::problem('usage_date', 'Tanggal Pemakaian', 'tidak boleh tanggal yang akan datang');
            }

            [$qty, $qtyReason] = self::quantity($cells['qty']);

            if ($qtyReason !== null) {
                $problems[] = self::problem('qty', 'Jumlah Terpakai', $qtyReason);
            }

            if ($cells['pic_name'] === null) {
                $problems[] = self::problem('pic_name', 'Nama PIC', 'kosong');
            } elseif (mb_strlen($cells['pic_name']) > self::PIC_MAX) {
                $problems[] = self::problem('pic_name', 'Nama PIC', 'maksimal '.self::PIC_MAX.' karakter');
            }

            if ($cells['notes'] !== null && mb_strlen($cells['notes']) > self::NOTES_MAX) {
                $problems[] = self::problem('notes', 'Catatan', 'maksimal '.self::NOTES_MAX.' karakter');
            }

            $record = [
                'row' => $index + 2, // baris 1 = heading
                'input_name' => $cells['tools_name_yes'],
                'tool_id' => $tool?->id,
                'tools_name_yes' => $tool?->tools_name_yes,
                'tools_name' => $tool?->tools_name,
                'machine_name' => $tool?->machine_name,
                'usage_date' => $date?->toDateString(),
                'usage_date_display' => $date?->format('d/m/Y') ?? self::text($cells['usage_date']),
                'qty' => $qty,
                'qty_display' => $qty === null ? self::text($cells['qty']) : null,
                'pic_name' => $cells['pic_name'],
                'notes' => $cells['notes'],
                'problems' => $problems,
                'duplicate' => null,
                'unusual' => null,
                'counts_toward_life' => true,
            ];

            if ($problems === []) {
                $key = self::duplicateKey($tool->id, $record['usage_date'], $qty, $cells['pic_name']);

                if (isset($seen[$key])) {
                    $record['duplicate'] = ['kind' => 'file', 'message' => "Sama persis dengan baris {$seen[$key]} di file ini"];
                } else {
                    $seen[$key] = $record['row'];
                }
            }

            $parsed[] = $record;
        }

        if ($parsed === []) {
            throw ValidationException::withMessages(['usage_file' => 'Tidak ada baris pemakaian yang terisi pada file.']);
        }

        $parsed = $this->markUnusual($this->markCounterOverlaps($this->markExistingDuplicates($parsed)), $tools);
        $valid = array_values(array_filter($parsed, fn (array $row): bool => $row['problems'] === []));

        // Simulasi life per tool (pemakaian sebelum Ganti Tool terakhir tidak menambah life tool sekarang).
        $byTool = [];

        foreach ($valid as $row) {
            $byTool[$row['tool_id']][] = ['usage_date' => $row['usage_date'], 'qty' => $row['qty']];
        }

        $projection = ToolLifeService::projectLife($byTool);

        foreach ($parsed as &$row) {
            $resetDate = $row['tool_id'] !== null ? ($projection[$row['tool_id']]['last_reset_date'] ?? null) : null;
            $row['counts_toward_life'] = $resetDate === null || ($row['usage_date'] !== null && $row['usage_date'] >= $resetDate);
        }
        unset($row);

        $errorRows = count($parsed) - count($valid);
        $token = (string) Str::uuid();
        $fileName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);

        if ($errorRows === 0) {
            Cache::put(self::CACHE_PREFIX.$token, ['file_name' => $fileName, 'rows' => $valid], now()->addHours(self::CACHE_TTL_HOURS));
        }

        $toolsById = $tools->keyBy('id');

        return [
            'token' => $errorRows === 0 ? $token : null,
            'file_name' => $fileName,
            'rows' => $parsed,
            'tools' => array_values(array_map(fn (int $toolId): array => [
                'tool_id' => $toolId,
                'tools_name_yes' => $toolsById[$toolId]->tools_name_yes,
                'tools_name' => $toolsById[$toolId]->tools_name,
                'machine_name' => $toolsById[$toolId]->machine_name,
                'life_time' => (int) $toolsById[$toolId]->life_time,
                'life_used_before' => $projection[$toolId]['life_used_before'],
                'last_reset_date' => $projection[$toolId]['last_reset_date'],
            ], array_keys($byTool))),
            'summary' => [
                'total_rows' => count($parsed),
                'valid_rows' => count($valid),
                'error_rows' => $errorRows,
                'duplicate_rows' => count(array_filter($valid, fn (array $row): bool => $row['duplicate'] !== null)),
                'unusual_rows' => count(array_filter($valid, fn (array $row): bool => $row['unusual'] !== null)),
                'tools' => count($byTool),
            ],
        ];
    }

    /**
     * @param  list<int>  $includeDuplicateRows  nomor baris Excel duplikat yang tetap dimasukkan
     * @return array{batch_no: string, inserted: int, tools: int, total_qty: int, skipped_duplicates: int, file_name: string}
     */
    public function apply(string $token, array $includeDuplicateRows = [], bool $confirmUnusual = false): array
    {
        $lock = Cache::lock(self::CACHE_PREFIX.'lock:'.$token, 60);

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

            $include = array_flip(array_map('intval', $includeDuplicateRows));

            // Cek ulang duplikat terhadap data terbaru (mungkin ada input lain sejak preview dibuat).
            $flaggedAtPreview = array_flip(array_column(
                array_filter($cached['rows'], fn (array $row): bool => $row['duplicate'] !== null),
                'row',
            ));
            $rows = $this->markCounterOverlaps($this->markExistingDuplicates($cached['rows']));
            $newlyDuplicated = array_filter(
                $rows,
                fn (array $row): bool => $row['duplicate'] !== null && ! isset($flaggedAtPreview[$row['row']]),
            );
            $selected = array_values(array_filter(
                $rows,
                fn (array $row): bool => $row['duplicate'] === null || isset($include[$row['row']]),
            ));

            if ($newlyDuplicated !== []) {
                throw ValidationException::withMessages([
                    'usage_import' => 'Sejak preview dibuat, ada input baru yang sama dengan '.count($newlyDuplicated).' baris file ini. Upload ulang file untuk review ulang.',
                ]);
            }

            if ($selected === []) {
                throw ValidationException::withMessages(['usage_import' => 'Tidak ada baris yang dipilih untuk dimasukkan.']);
            }

            $unusual = count(array_filter($selected, fn (array $row): bool => ($row['unusual'] ?? null) !== null));

            if ($unusual > 0 && ! $confirmUnusual) {
                throw ValidationException::withMessages([
                    'confirm_unusual' => "{$unusual} baris berjumlah janggal. Periksa baris bertanda oranye, lalu centang konfirmasi sebelum menerapkan.",
                ]);
            }

            $result = $this->life->importUsages($selected, $this->batchNo());
            Cache::forget(self::CACHE_PREFIX.$token);

            return [
                ...$result,
                'skipped_duplicates' => count($rows) - count($selected),
                'file_name' => $cached['file_name'],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Tandai baris yang sama persis dengan input aktif yang sudah ada di database.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function markExistingDuplicates(array $rows): array
    {
        $valid = array_filter($rows, fn (array $row): bool => $row['problems'] === [] && $row['duplicate'] === null);

        if ($valid === []) {
            return $rows;
        }

        $dates = array_column($valid, 'usage_date');
        $existing = [];

        ToolUsageLog::query()->active()
            ->where('type', UsageType::Usage->value)
            ->whereIn('tool_id', array_unique(array_column($valid, 'tool_id')))
            // Batas atas eksklusif: kolom date bisa tersimpan sebagai "Y-m-d 00:00:00".
            ->where('usage_date', '>=', min($dates))
            ->where('usage_date', '<', CarbonImmutable::parse(max($dates))->addDay()->toDateString())
            ->select(['id', 'tool_id', 'usage_date', 'qty', 'pic_name', 'batch_no', 'created_at'])
            ->lazyById(1000)
            ->each(function (ToolUsageLog $log) use (&$existing): void {
                $existing[self::duplicateKey($log->tool_id, $log->usage_date->toDateString(), $log->qty, $log->pic_name)] ??= $log;
            });

        foreach ($rows as &$row) {
            if ($row['problems'] !== [] || $row['duplicate'] !== null) {
                continue;
            }

            $log = $existing[self::duplicateKey($row['tool_id'], $row['usage_date'], $row['qty'], $row['pic_name'])] ?? null;

            if ($log !== null) {
                $source = $log->batch_no ? "import {$log->batch_no}" : 'input aplikasi';
                $row['duplicate'] = [
                    'kind' => 'existing',
                    'message' => 'Sudah pernah diinput ('.$source.', '.$log->created_at?->format('d/m/Y H:i').')',
                ];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Tahan baris yang tanggalnya ≤ pengecekan counter mesin terakhir tool tersebut
     * (laporan kerusakan Ganti Baru): pemakaian sampai tanggal itu sudah
     * tercakup angka counter, jadi kemungkinan terhitung dobel. Sama seperti duplikat:
     * tidak dimasukkan kecuali admin mencentang "Tetap masukkan".
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function markCounterOverlaps(array $rows): array
    {
        $candidates = array_filter($rows, fn (array $row): bool => $row['problems'] === [] && $row['duplicate'] === null);
        $checkpoints = ShotCountService::latestCheckpoints(array_values(array_unique(array_column($candidates, 'tool_id'))));

        if ($checkpoints === []) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $checkpoint = $row['problems'] === [] && $row['duplicate'] === null ? ($checkpoints[$row['tool_id']] ?? null) : null;

            if ($checkpoint !== null && $row['usage_date'] <= $checkpoint['date']) {
                $row['duplicate'] = [
                    'kind' => 'counter',
                    'message' => sprintf(
                        'Counter mesin tool ini sudah dicatat %s (%s) — pemakaian sampai tanggal itu sudah tercakup',
                        CarbonImmutable::parse($checkpoint['date'])->format('d/m/Y'),
                        $checkpoint['source'],
                    ),
                ];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Tandai baris valid yang jumlahnya janggal (> 10% life time / > 3× rata-rata harian).
     * Hanya peringatan: baris tetap masuk, tetapi admin wajib konfirmasi sekali sebelum Terapkan.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<int, ToolsInventory>  $tools
     * @return list<array<string, mixed>>
     */
    private function markUnusual(array $rows, $tools): array
    {
        $lifeTimes = $tools->pluck('life_time', 'id');
        $toolIds = array_values(array_unique(array_filter(array_column($rows, 'tool_id'))));
        $baselines = UsageAnomalyService::dailyBaselines($toolIds);

        foreach ($rows as &$row) {
            if ($row['problems'] === []) {
                $row['unusual'] = UsageAnomalyService::reason(
                    (int) $row['qty'],
                    (int) $lifeTimes->get($row['tool_id']),
                    $baselines[$row['tool_id']] ?? null,
                );
            }
        }
        unset($row);

        return $rows;
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

    /** Kunci duplikat: tool (dari Tools Name YES) + tanggal + jumlah + PIC (tanpa beda huruf besar/kecil). */
    private static function duplicateKey(int $toolId, string $date, int $qty, string $picName): string
    {
        return implode('|', [$toolId, $date, $qty, mb_strtolower(preg_replace('/\s+/u', ' ', trim($picName)))]);
    }

    private static function nameKey(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?string}
     */
    private static function date(mixed $value): array
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return [null, 'kosong'];
        }

        // Sel tanggal Excel → angka serial.
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $serial = (float) $value;

            if ($serial < 1 || $serial > 2958465) {
                return [null, 'bukan tanggal'];
            }

            try {
                return [CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($serial))->startOfDay(), null];
            } catch (Throwable) {
                return [null, 'bukan tanggal'];
            }
        }

        $text = trim((string) $value);

        // Format teks yang diterima. "j/n/Y" = tanpa nol di depan (mis. 5/1/2026).
        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'j.n.Y', 'Y-m-d', 'd/m/y'] as $format) {
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

        return [null, 'format tidak dikenali (gunakan dd/mm/yyyy)'];
    }

    /**
     * Bilangan bulat > 0. Teks "120.000" / "120,000" dibaca sebagai 120000 (pemisah ribuan).
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

        if ($number <= 0) {
            return [null, 'harus lebih dari 0'];
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
