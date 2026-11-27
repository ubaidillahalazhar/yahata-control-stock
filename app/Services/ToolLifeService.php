<?php

namespace App\Services;

use App\Enums\UsageSource;
use App\Enums\UsageType;
use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pemakaian life time per tool (input harian dengan tanggal acak).
 *
 * - `tool_usage_logs` adalah sumber kebenaran (1 baris = 1 input, tidak pernah diedit).
 * - `tools_inventory.life_used` hanya cache: dihitung ulang dari log setiap kali ada
 *   input / pembatalan / reset, di dalam transaksi + row lock yang sama.
 * - Life terpakai = total USAGE aktif setelah RESET aktif terakhir (urut tanggal, lalu id).
 *   ADJUST (koreksi count siklus lama dari laporan kerusakan) tidak pernah menambah life,
 *   tetapi tetap masuk Actual bulanan.
 *
 * Batas warna & status bersifat tetap (bukan pengaturan client):
 *   hijau < 50% · kuning 50–79% · merah ≥ 80% (= Status Life ORDER).
 */
final class ToolLifeService
{
    public const YELLOW_FROM_PERCENT = 50;

    public const RED_FROM_PERCENT = 80;

    public const STATUS_ORDER = 'ORDER';

    public const STATUS_SECURE = 'SECURE';

    public const LEVEL_GREEN = 'green';

    public const LEVEL_YELLOW = 'yellow';

    public const LEVEL_RED = 'red';

    public function __construct(private readonly StockMutationService $stock) {}

    /**
     * Ringkasan life time untuk UI. Persentase dibulatkan ke bawah (1 desimal) agar
     * angka yang tampil selalu konsisten dengan warnanya (79,9% tetap kuning).
     *
     * @return array{life_used: int, life_remaining: int, life_over: int, life_usage_percent: float, life_level: string, life_status: string}
     */
    public static function summary(int $lifeTime, int $lifeUsed): array
    {
        $lifeUsed = max(0, $lifeUsed);

        if ($lifeTime <= 0) {
            return [
                'life_used' => $lifeUsed,
                'life_remaining' => 0,
                'life_over' => 0,
                'life_usage_percent' => 0.0,
                'life_level' => self::LEVEL_GREEN,
                'life_status' => self::STATUS_SECURE,
            ];
        }

        // Perbandingan integer murni (tanpa pembulatan float) untuk batas warna.
        $level = match (true) {
            $lifeUsed * 100 >= self::RED_FROM_PERCENT * $lifeTime => self::LEVEL_RED,
            $lifeUsed * 100 >= self::YELLOW_FROM_PERCENT * $lifeTime => self::LEVEL_YELLOW,
            default => self::LEVEL_GREEN,
        };

        return [
            'life_used' => $lifeUsed,
            'life_remaining' => max(0, $lifeTime - $lifeUsed),
            'life_over' => max(0, $lifeUsed - $lifeTime),
            'life_usage_percent' => (float) (intdiv($lifeUsed * 1000, $lifeTime) / 10),
            'life_level' => $level,
            'life_status' => $level === self::LEVEL_RED ? self::STATUS_ORDER : self::STATUS_SECURE,
        ];
    }

    public function recordUsage(
        int $toolId,
        string $usageDate,
        int $qty,
        string $picName,
        ?string $notes = null,
        ?string $batchNo = null,
        UsageSource $source = UsageSource::Manual,
    ): ToolUsageLog {
        return $this->write($toolId, UsageType::Usage, $usageDate, $qty, $picName, $notes, $batchNo, $source);
    }

    /**
     * Koreksi count untuk siklus tool yang sudah ditutup laporan kerusakan.
     * Masuk Actual bulanan, tetapi tidak menambah life tool yang sedang berjalan (recalculate() hanya USAGE).
     */
    public function adjust(int $toolId, string $usageDate, int $qty, string $picName, ?string $notes, int $damageReportId): ToolUsageLog
    {
        return $this->write($toolId, UsageType::Adjust, $usageDate, $qty, $picName, $notes, null, UsageSource::Damage, $damageReportId);
    }

    /**
     * Import Machine Result satu periode (bulan) — satu transaksi, semua atau tidak sama sekali.
     *
     * Import Machine Result sebelumnya untuk periode yang sama DIBATALKAN dulu (bukan dihapus),
     * lalu baris baru dimasukkan, sehingga upload ulang tidak pernah terhitung dobel.
     * Tool dikunci berurutan berdasarkan ID agar tidak deadlock dengan input lain.
     *
     * @param  list<array{tool_id: int, usage_date: string, qty: int, pic_name: string, notes: ?string}>  $entries
     * @return array{batch_no: string, inserted: int, tools: int, total_qty: int, replaced: int, replaced_batches: list<string>}
     */
    public function importPeriod(array $entries, string $batchNo, string $period, string $cancelledBy): array
    {
        return DB::transaction(function () use ($entries, $batchNo, $period, $cancelledBy): array {
            $previous = fn () => ToolUsageLog::query()->active()
                ->where('source', UsageSource::MachineResult->value)
                ->where('import_period', $period);

            $newToolIds = array_values(array_unique(array_map(fn (array $row): int => (int) $row['tool_id'], $entries)));
            $toolIds = array_values(array_unique([...$newToolIds, ...$previous()->distinct()->pluck('tool_id')->all()]));
            sort($toolIds);

            $tools = ToolsInventory::query()->whereIn('id', $toolIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if (array_diff($newToolIds, $tools->keys()->all()) !== []) {
                throw ValidationException::withMessages([
                    'usage_import' => 'Sebagian tool sudah tidak ada di master sejak preview dibuat. Upload ulang file.',
                ]);
            }

            $replacedBatches = $previous()->distinct()->orderBy('batch_no')->pluck('batch_no')->filter()->values()->all();
            $replaced = $previous()->update([
                'cancelled_at' => now(),
                'cancelled_by' => self::clean($cancelledBy),
                'cancel_reason' => "Diganti import {$batchNo} (periode {$period})",
                'updated_at' => now(),
            ]);

            $byTool = [];

            foreach ($entries as $entry) {
                $byTool[(int) $entry['tool_id']][] = $entry;
            }

            $now = now();
            $userId = auth()->id();
            $insert = [];
            // Format tanggal sama persis dengan simpanan Eloquent (recalculate() membandingkan nilai mentah).
            $model = new ToolUsageLog;

            foreach ($byTool as $toolId => $rows) {
                // life_used_before/after per baris = simulasi berurutan tanggal (snapshot, bukan sumber kebenaran).
                usort($rows, fn (array $a, array $b): int => strcmp($a['usage_date'], $b['usage_date']));
                $running = $this->recalculate($tools[$toolId]);
                $resetDate = $this->lastResetDate($toolId);

                foreach ($rows as $row) {
                    $before = $running;

                    if ($resetDate === null || $row['usage_date'] >= $resetDate) {
                        $running += (int) $row['qty'];
                    }

                    $insert[] = [
                        'tool_id' => $toolId,
                        'type' => UsageType::Usage->value,
                        'source' => UsageSource::MachineResult->value,
                        'usage_date' => $model->fromDateTime(Carbon::parse($row['usage_date'])->startOfDay()),
                        'qty' => (int) $row['qty'],
                        'life_used_before' => $before,
                        'life_used_after' => $running,
                        'pic_name' => self::clean($row['pic_name']),
                        'notes' => self::clean($row['notes'] ?? null),
                        'batch_no' => $batchNo,
                        'import_period' => $period,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($insert, 500) as $chunk) {
                ToolUsageLog::query()->insert($chunk);
            }

            foreach ($tools as $tool) {
                $tool->forceFill(['life_used' => $this->recalculate($tool)])->save();
            }

            return [
                'batch_no' => $batchNo,
                'inserted' => count($insert),
                'tools' => count($byTool),
                'total_qty' => array_sum(array_column($insert, 'qty')),
                'replaced' => $replaced,
                'replaced_batches' => $replacedBatches,
            ];
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }

    /**
     * Simulasi import satu periode Machine Result (preview, tidak menulis DB).
     * Life "sebelum" = kondisi sekarang; "setelah" = tanpa import periode ini yang lama + baris baru.
     *
     * @param  array<int, list<array{usage_date: string, qty: int}>>  $newRowsByTool  keyed by tool_id
     * @return array<int, array{life_used_before: int, life_used_after: int, last_reset_date: ?string}>
     */
    public function projectPeriod(array $newRowsByTool, string $period): array
    {
        $result = [];

        foreach (ToolsInventory::query()->whereIn('id', array_keys($newRowsByTool))->get() as $tool) {
            $resetDate = $this->lastResetDate($tool->id);
            $counted = array_sum(array_map(
                fn (array $row): int => $resetDate === null || $row['usage_date'] >= $resetDate ? (int) $row['qty'] : 0,
                $newRowsByTool[$tool->id],
            ));

            $result[$tool->id] = [
                'life_used_before' => (int) $tool->life_used,
                'life_used_after' => $this->recalculate($tool, excludeImportPeriod: $period) + $counted,
                'last_reset_date' => $resetDate,
            ];
        }

        return $result;
    }

    private function lastResetDate(int $toolId): ?string
    {
        return ToolUsageLog::query()->active()
            ->where('tool_id', $toolId)
            ->where('type', UsageType::Reset->value)
            ->orderByDesc('usage_date')->orderByDesc('id')
            ->first(['usage_date'])?->usage_date?->toDateString();
    }

    /**
     * Batalkan seluruh input aktif dari satu batch import Excel. Baris tetap tercatat (dicoret).
     *
     * @return int jumlah baris yang dibatalkan
     */
    public function cancelBatch(string $batchNo, string $picName, ?string $reason = null): int
    {
        return DB::transaction(function () use ($batchNo, $picName, $reason): int {
            $toolIds = ToolUsageLog::query()->active()->where('batch_no', $batchNo)
                ->distinct()->orderBy('tool_id')->pluck('tool_id')->all();

            if ($toolIds === []) {
                throw ValidationException::withMessages(['usage' => "Batch {$batchNo} tidak punya input aktif (sudah dibatalkan / tidak ada)."]);
            }

            $tools = ToolsInventory::query()->whereIn('id', $toolIds)->orderBy('id')->lockForUpdate()->get();

            $cancelled = ToolUsageLog::query()->active()->where('batch_no', $batchNo)->update([
                'cancelled_at' => now(),
                'cancelled_by' => self::clean($picName),
                'cancel_reason' => self::clean($reason) ?? "Batal batch {$batchNo}",
                'updated_at' => now(),
            ]);

            foreach ($tools as $tool) {
                $tool->forceFill(['life_used' => $this->recalculate($tool)])->save();
            }

            return $cancelled;
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }

    /**
     * Simulasi life terpakai setelah pemakaian baru ditambahkan (untuk preview, tidak menulis DB).
     * Sama dengan recalculate(): hanya pemakaian SETELAH Ganti Tool terakhir yang dihitung.
     * Pemakaian baru bertanggal sama dengan Ganti Tool dianggap setelahnya (input lebih baru).
     *
     * @param  array<int, list<array{usage_date: string, qty: int}>>  $newRowsByTool  keyed by tool_id
     * @return array<int, array{life_used_before: int, life_used_after: int, last_reset_date: ?string, counted_qty: int}>
     */
    public static function projectLife(array $newRowsByTool): array
    {
        if ($newRowsByTool === []) {
            return [];
        }

        $tools = ToolsInventory::query()->whereIn('id', array_keys($newRowsByTool))->get(['id', 'life_used'])->keyBy('id');

        $lastResets = ToolUsageLog::query()->active()
            ->whereIn('tool_id', array_keys($newRowsByTool))
            ->where('type', UsageType::Reset->value)
            ->orderBy('usage_date')->orderBy('id')
            ->get(['tool_id', 'usage_date'])
            ->keyBy('tool_id'); // urut naik → entri terakhir per tool tersisa

        $result = [];

        foreach ($newRowsByTool as $toolId => $rows) {
            $resetDate = $lastResets->get($toolId)?->usage_date?->toDateString();
            $counted = array_sum(array_map(
                fn (array $row): int => $resetDate === null || $row['usage_date'] >= $resetDate ? (int) $row['qty'] : 0,
                $rows,
            ));
            $before = (int) ($tools->get($toolId)?->life_used ?? 0);

            $result[$toolId] = [
                'life_used_before' => $before,
                'life_used_after' => $before + $counted,
                'last_reset_date' => $resetDate,
                'counted_qty' => $counted,
            ];
        }

        return $result;
    }

    /**
     * Tool diganti baru: life terpakai kembali ke 0 mulai tanggal ini.
     * Stok tidak diubah di sini (pengambilan tool cadangan dicatat lewat Transaksi OUT).
     */
    public function reset(int $toolId, string $usageDate, string $picName, ?string $notes = null, ?int $damageReportId = null): ToolUsageLog
    {
        $source = $damageReportId !== null ? UsageSource::Damage : UsageSource::Manual;

        return $this->write($toolId, UsageType::Reset, $usageDate, 0, $picName, $notes, null, $source, $damageReportId);
    }

    public function cancel(ToolUsageLog $log, string $picName, ?string $reason = null): ToolUsageLog
    {
        return DB::transaction(function () use ($log, $picName, $reason): ToolUsageLog {
            $tool = $this->stock->lockTool($log->tool_id);
            $log = ToolUsageLog::query()->whereKey($log->id)->lockForUpdate()->firstOrFail();

            if ($log->isCancelled()) {
                throw ValidationException::withMessages(['usage' => 'Input ini sudah dibatalkan sebelumnya.']);
            }

            $log->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => self::clean($picName),
                'cancel_reason' => self::clean($reason),
            ])->save();

            $tool->forceFill(['life_used' => $this->recalculate($tool)])->save();

            return $log;
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }

    /**
     * Koreksi satu input Pemakaian (salah jumlah / tanggal): baris lama dibatalkan dengan alasan,
     * baris baru dibuat & menunjuk baris lama (`corrects_id`). Batch, sumber, dan catatan ikut
     * baris lama, sehingga "Batalkan seluruh batch" tetap mencakup hasil koreksinya.
     */
    public function correct(ToolUsageLog $log, string $usageDate, int $qty, string $picName, string $reason): ToolUsageLog
    {
        return DB::transaction(function () use ($log, $usageDate, $qty, $picName, $reason): ToolUsageLog {
            $tool = $this->stock->lockTool($log->tool_id);
            $log = ToolUsageLog::query()->whereKey($log->id)->lockForUpdate()->firstOrFail();

            if ($log->isCancelled()) {
                throw ValidationException::withMessages(['usage' => 'Input ini sudah dibatalkan / dikoreksi sebelumnya.']);
            }

            if (! $log->isCorrectable()) {
                throw ValidationException::withMessages(['usage' => 'Hanya input Pemakaian yang bisa dikoreksi.']);
            }

            if ($log->usage_date->toDateString() === $usageDate && (int) $log->qty === $qty) {
                throw ValidationException::withMessages(['qty' => 'Tidak ada perubahan: tanggal & jumlah sama dengan input lama.']);
            }

            $reason = self::clean($reason);

            $log->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => self::clean($picName),
                'cancel_reason' => 'Dikoreksi: '.$reason,
            ])->save();

            $tool->forceFill(['life_used' => $this->recalculate($tool)])->save();

            $notes = trim(sprintf(
                'Koreksi dari %s · %s pcs (%s). %s',
                $log->usage_date->format('d/m/Y'),
                number_format((int) $log->qty, 0, ',', '.'),
                $reason,
                $log->notes ?? '',
            ));

            $corrected = $this->write(
                toolId: $tool->id,
                type: UsageType::Usage,
                usageDate: $usageDate,
                qty: $qty,
                picName: $picName,
                notes: $notes,
                batchNo: $log->batch_no,
                source: $log->source,
                corrects: $log,
            );

            // Hasil koreksi baris Machine Result ikut periodenya → ikut diganti saat periode diupload ulang.
            if ($log->import_period !== null) {
                $corrected->forceFill(['import_period' => $log->import_period])->save();
            }

            return $corrected;
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }

    /**
     * Hitung ulang life terpakai dari log (dipakai juga untuk perbaikan data).
     */
    public function recalculate(ToolsInventory $tool, ?string $excludeImportPeriod = null): int
    {
        $lastReset = ToolUsageLog::query()
            ->active()
            ->where('tool_id', $tool->id)
            ->where('type', UsageType::Reset->value)
            ->orderByDesc('usage_date')
            ->orderByDesc('id')
            ->first();

        $usage = ToolUsageLog::query()
            ->active()
            ->where('tool_id', $tool->id)
            ->where('type', UsageType::Usage->value)
            // Preview import Machine Result: abaikan import lama periode itu (akan diganti).
            ->when($excludeImportPeriod !== null, fn ($query) => $query->where(
                fn ($q) => $q->whereNull('import_period')->orWhere('import_period', '<>', $excludeImportPeriod),
            ));

        if ($lastReset !== null) {
            // Bandingkan dengan nilai mentah kolom agar format tanggal sama persis dengan yang tersimpan.
            $resetDate = $lastReset->getRawOriginal('usage_date');

            $usage->where(function ($query) use ($resetDate, $lastReset): void {
                $query->where('usage_date', '>', $resetDate)
                    ->orWhere(fn ($same) => $same->where('usage_date', $resetDate)->where('id', '>', $lastReset->id));
            });
        }

        return (int) $usage->sum('qty');
    }

    /**
     * Actual per bulan dari log aktif (yang dibatalkan tidak dihitung).
     *
     * - actual_qty     = total pcs input Pemakaian di bulan itu (dibandingkan dengan Forecast).
     * - tools_replaced = jumlah "Ganti Tool" di bulan itu (= tool yang habis / diganti).
     *
     * @param  list<?string>  $months  Format Y-m; null / duplikat diabaikan
     * @return array<string, array{actual_qty: int, usage_inputs: int, tools_replaced: int}>
     */
    public static function monthlyActuals(int $toolId, array $months): array
    {
        $empty = array_fill_keys(self::normalizeMonths($months), self::EMPTY_ACTUAL);

        return self::monthlyActualsByTool($months, [$toolId])[$toolId] ?? $empty;
    }

    /**
     * Sama seperti monthlyActuals(), untuk banyak tool sekaligus (1 query — dipakai export).
     * Tool tanpa log tidak ada di hasil; pakai EMPTY_ACTUAL sebagai default.
     *
     * @param  list<?string>  $months  Format Y-m
     * @param  list<int>|null  $toolIds  null = semua tool
     * @return array<int, array<string, array{actual_qty: int, usage_inputs: int, tools_replaced: int}>>
     */
    public static function monthlyActualsByTool(array $months, ?array $toolIds = null): array
    {
        $months = self::normalizeMonths($months);

        if ($months === [] || $toolIds === []) {
            return [];
        }

        $template = array_fill_keys($months, self::EMPTY_ACTUAL);
        $from = Carbon::createFromFormat('!Y-m', $months[0])->startOfMonth();
        $until = Carbon::createFromFormat('!Y-m', end($months))->startOfMonth()->addMonth();
        $result = [];

        ToolUsageLog::query()
            ->active()
            ->when($toolIds !== null, fn ($query) => $query->whereIn('tool_id', $toolIds))
            // Batas atas eksklusif: kolom date bisa tersimpan sebagai "Y-m-d 00:00:00".
            ->where('usage_date', '>=', $from->toDateString())
            ->where('usage_date', '<', $until->toDateString())
            ->select(['id', 'tool_id', 'type', 'usage_date', 'qty'])
            ->lazyById(1000)
            ->each(function (ToolUsageLog $log) use (&$result, $template): void {
                $key = $log->usage_date->format('Y-m');

                if (! isset($template[$key])) {
                    return;
                }

                $result[$log->tool_id] ??= $template;

                if ($log->type === UsageType::Usage) {
                    $result[$log->tool_id][$key]['actual_qty'] += $log->qty;
                    $result[$log->tool_id][$key]['usage_inputs']++;
                } elseif ($log->type === UsageType::Adjust) {
                    // Koreksi dari counter mesin: menambah Actual, bukan jumlah input / ganti tool.
                    $result[$log->tool_id][$key]['actual_qty'] += $log->qty;
                } elseif ($log->type === UsageType::Reset) {
                    $result[$log->tool_id][$key]['tools_replaced']++;
                }
            });

        return $result;
    }

    public const EMPTY_ACTUAL = ['actual_qty' => 0, 'usage_inputs' => 0, 'tools_replaced' => 0];

    /**
     * @param  list<?string>  $months
     * @return list<string>
     */
    private static function normalizeMonths(array $months): array
    {
        $months = array_values(array_unique(array_filter($months)));
        sort($months);

        return $months;
    }

    private function write(
        int $toolId,
        UsageType $type,
        string $usageDate,
        int $qty,
        string $picName,
        ?string $notes,
        ?string $batchNo = null,
        UsageSource $source = UsageSource::Manual,
        ?int $damageReportId = null,
        ?ToolUsageLog $corrects = null,
    ): ToolUsageLog {
        return DB::transaction(function () use ($toolId, $type, $usageDate, $qty, $picName, $notes, $batchNo, $source, $damageReportId, $corrects): ToolUsageLog {
            $tool = $this->stock->lockTool($toolId);
            $before = (int) $tool->life_used;

            $log = ToolUsageLog::query()->create([
                'tool_id' => $tool->id,
                'type' => $type,
                'usage_date' => Carbon::parse($usageDate)->startOfDay(),
                'qty' => $type === UsageType::Reset ? $before : $qty,
                'life_used_before' => $before,
                'life_used_after' => $before,
                'pic_name' => self::clean($picName),
                'notes' => self::clean($notes),
                'batch_no' => $batchNo,
                'source' => $source,
                'damage_report_id' => $damageReportId,
                // Akun penginput (dasar aturan 24 jam PIC). Null bila dari console / job.
                'user_id' => auth()->id(),
                'corrects_id' => $corrects?->id,
                'first_input_at' => $corrects !== null ? ($corrects->first_input_at ?? $corrects->created_at) : null,
            ]);

            $after = $this->recalculate($tool);

            $log->forceFill(['life_used_after' => $after])->save();
            $tool->forceFill(['life_used' => $after])->save();

            return $log;
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
