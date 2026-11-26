<?php

namespace App\Services;

use App\Enums\DamageAction;
use App\Models\DamageReport;
use App\Models\ToolsInventory;
use App\Notifications\AdminAlert;
use App\Notifications\DamageCountOverdue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menghubungkan angka counter mesin (shot count) dengan life time di sistem.
 *
 * Asumsi saat ini: counter mesin di-nol-kan setiap ganti tool, sehingga angka counter =
 * jumlah shot sejak tool dipasang = arti `tools_inventory.life_used`. Bila nanti counter
 * menjadi kumulatif, cukup ubah shotsSinceInstall() (mis. kurangi counter saat pasang).
 *
 * - Laporan kerusakan REPLACE_NEW menutup siklus: snapshot life sistem, selisih counter vs
 *   sistem (positif → ADJUST, negatif → ditandai untuk ditinjau admin), lalu reset life.
 */
final class ShotCountService
{
    /** Selisih counter vs sistem di atas persentase ini → operator wajib konfirmasi di form. */
    public const CONFIRM_VARIANCE_PERCENT = 10;

    public function __construct(
        private readonly ToolLifeService $life,
        private readonly StockMutationService $stock,
    ) {}

    public static function shotsSinceInstall(int $counterReading): int
    {
        return max(0, $counterReading);
    }

    public static function variance(int $counterReading, int $systemLifeUsed): int
    {
        return self::shotsSinceInstall($counterReading) - $systemLifeUsed;
    }

    /** Sama dengan aturan konfirmasi di form (resources/js/lib/shotCount.js). */
    public static function needsConfirmation(int $variance, int $systemLifeUsed): bool
    {
        return abs($variance) * 100 > self::CONFIRM_VARIANCE_PERCENT * max($systemLifeUsed, 1);
    }

    /**
     * Tutup siklus life tool untuk laporan REPLACE_NEW. Dipanggil di dalam transaksi
     * DamageReportService setelah laporan dibuat; `$tool` sudah di-lock.
     */
    public function closeCycle(DamageReport $report, ToolsInventory $tool): void
    {
        $report->system_shot_count = (int) $tool->life_used;
        $report->life_time_snapshot = (int) $tool->life_time;

        if ($report->actual_shot_count !== null) {
            $this->applyVariance($report);
        }

        $this->life->reset(
            toolId: $tool->id,
            usageDate: $report->reported_at->toDateString(),
            picName: $report->operator_name,
            notes: 'Ganti baru — '.$report->report_no,
            damageReportId: $report->id,
        );
    }

    /**
     * Lengkapi laporan "count belum dibawa". Selisih dihitung terhadap snapshot saat laporan dibuat,
     * jadi tidak tercampur dengan pemakaian tool baru yang sudah berjalan sejak itu.
     */
    public function fillPendingCount(DamageReport $report, int $counterReading): DamageReport
    {
        return DB::transaction(function () use ($report, $counterReading): DamageReport {
            $this->stock->lockTool($report->tool_id);
            $report = DamageReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if (! $report->count_pending) {
                throw ValidationException::withMessages(['actual_shot_count' => 'Count laporan ini sudah diisi sebelumnya.']);
            }

            $report->forceFill([
                'actual_shot_count' => $counterReading,
                'count_pending' => false,
                'count_filled_at' => now(),
            ]);
            $this->applyVariance($report);
            $report->save();

            return $report;
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }

    /**
     * Tanggal pengecekan counter terakhir per tool (laporan kerusakan Ganti Baru).
     * Baris import Excel bertanggal ≤ tanggal ini kemungkinan terhitung dobel → ditahan di preview.
     *
     * @param  list<int>  $toolIds
     * @return array<int, array{date: string, source: string}>
     */
    public static function latestCheckpoints(array $toolIds): array
    {
        if ($toolIds === []) {
            return [];
        }

        $checkpoints = [];
        $merge = function (int $toolId, ?string $at, string $source) use (&$checkpoints): void {
            if ($at === null) {
                return;
            }
            $date = substr($at, 0, 10);

            if (! isset($checkpoints[$toolId]) || $date > $checkpoints[$toolId]['date']) {
                $checkpoints[$toolId] = ['date' => $date, 'source' => $source];
            }
        };

        // Hanya laporan dengan snapshot (sejak fitur ini ada); laporan lama tidak menahan import.
        DamageReport::query()
            ->whereIn('tool_id', $toolIds)
            ->where('action', DamageAction::ReplaceNew->value)
            ->whereNotNull('system_shot_count')
            ->groupBy('tool_id')
            ->selectRaw('tool_id, MAX(reported_at) AS last_at')
            ->get()
            ->each(fn ($row) => $merge((int) $row->tool_id, $row->getAttribute('last_at'), 'laporan kerusakan'));

        return $checkpoints;
    }

    /**
     * Kirim notifikasi sekali untuk laporan yang menunggu count lebih dari batas waktu.
     * Dipanggil dari polling notifikasi admin → tidak butuh scheduler / cron di server.
     *
     * @return int jumlah laporan yang baru diberi tahu
     */
    public static function notifyOverdueCounts(): int
    {
        $notified = 0;

        DamageReport::query()
            ->with('tool')
            ->where('count_pending', true)
            ->whereNull('overdue_notified_at')
            ->where('reported_at', '<=', now()->subHours(DamageReport::COUNT_OVERDUE_HOURS))
            ->limit(50)
            ->get()
            ->each(function (DamageReport $report) use (&$notified): void {
                // Update bersyarat: dua admin polling bersamaan tidak menghasilkan notifikasi ganda.
                $claimed = DamageReport::query()->whereKey($report->id)->whereNull('overdue_notified_at')
                    ->update(['overdue_notified_at' => now()]);

                if ($claimed === 1) {
                    AdminAlert::toAdmins(new DamageCountOverdue($report));
                    $notified++;
                }
            });

        return $notified;
    }

    private function applyVariance(DamageReport $report): void
    {
        $variance = self::variance((int) $report->actual_shot_count, (int) $report->system_shot_count);

        $report->shot_variance = $variance;
        $report->needs_review = $variance < 0;

        if ($variance > 0) {
            $this->life->adjust(
                toolId: $report->tool_id,
                usageDate: $report->reported_at->toDateString(),
                qty: $variance,
                picName: $report->operator_name,
                notes: sprintf(
                    'Koreksi %s: counter %s vs sistem %s',
                    $report->report_no,
                    self::format((int) $report->actual_shot_count),
                    self::format((int) $report->system_shot_count),
                ),
                damageReportId: $report->id,
            );
        }
    }

    private static function format(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
