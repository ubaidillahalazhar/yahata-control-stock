<?php

namespace App\Services;

use App\Enums\UsageType;
use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use Illuminate\Validation\ValidationException;

/**
 * Deteksi jumlah pemakaian yang "janggal" (kemungkinan salah ketik) sebelum disimpan.
 *
 * Janggal bila salah satu:
 *  - jumlah > LIFE_PERCENT% dari life time tool, atau
 *  - jumlah > DAILY_MULTIPLIER× rata-rata pemakaian harian tool tsb (BASELINE_DAYS hari terakhir,
 *    minimal MIN_BASELINE_DAYS hari berdata agar rata-ratanya bermakna).
 *
 * Angka janggal tidak ditolak: PIC/admin wajib mencentang konfirmasi. Aturan 10% juga dihitung
 * di form (resources/js/lib/usageAnomaly.js) agar peringatan langsung muncul saat mengetik.
 */
final class UsageAnomalyService
{
    public const LIFE_PERCENT = 10;

    public const DAILY_MULTIPLIER = 3;

    public const BASELINE_DAYS = 90;

    public const MIN_BASELINE_DAYS = 5;

    /**
     * Rata-rata pcs per hari pemakaian (hanya hari yang ada input) per tool.
     *
     * @param  list<int>  $toolIds
     * @return array<int, int> tool_id => rata-rata harian (tool tanpa cukup data tidak ada di hasil)
     */
    public static function dailyBaselines(array $toolIds, ?int $excludeLogId = null): array
    {
        if ($toolIds === []) {
            return [];
        }

        return ToolUsageLog::query()
            ->active()
            ->where('type', UsageType::Usage->value)
            ->whereIn('tool_id', $toolIds)
            ->where('usage_date', '>=', now()->subDays(self::BASELINE_DAYS)->toDateString())
            ->when($excludeLogId !== null, fn ($query) => $query->whereKeyNot($excludeLogId))
            ->groupBy('tool_id')
            ->selectRaw('tool_id, SUM(qty) AS total_qty, COUNT(DISTINCT usage_date) AS days')
            ->get()
            ->filter(fn ($row): bool => (int) $row->getAttribute('days') >= self::MIN_BASELINE_DAYS)
            ->mapWithKeys(fn ($row): array => [
                (int) $row->tool_id => intdiv((int) $row->getAttribute('total_qty'), (int) $row->getAttribute('days')),
            ])
            ->all();
    }

    /**
     * Alasan jumlah ini janggal, atau null bila wajar.
     */
    public static function reason(int $qty, int $lifeTime, ?int $dailyBaseline): ?string
    {
        if ($lifeTime > 0 && $qty * 100 > self::LIFE_PERCENT * $lifeTime) {
            return sprintf(
                'lebih dari %d%% life time tool (%s pcs)',
                self::LIFE_PERCENT,
                self::format(intdiv($lifeTime * self::LIFE_PERCENT, 100)),
            );
        }

        if ($dailyBaseline !== null && $dailyBaseline > 0 && $qty > self::DAILY_MULTIPLIER * $dailyBaseline) {
            return sprintf(
                'lebih dari %d× rata-rata harian tool ini (± %s pcs/hari)',
                self::DAILY_MULTIPLIER,
                self::format($dailyBaseline),
            );
        }

        return null;
    }

    /**
     * Untuk form input / koreksi: tolak bila janggal dan belum dikonfirmasi.
     *
     * @throws ValidationException
     */
    public static function assertConfirmed(int $toolId, int $qty, bool $confirmed, ?int $excludeLogId = null): void
    {
        if ($confirmed) {
            return;
        }

        $tool = ToolsInventory::query()->findOrFail($toolId, ['id', 'life_time']);
        $reason = self::reason($qty, (int) $tool->life_time, self::dailyBaselines([$toolId], $excludeLogId)[$toolId] ?? null);

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'confirm_unusual' => sprintf(
                    'Jumlah %s pcs janggal: %s. Periksa lagi angkanya; bila sudah benar, centang konfirmasi lalu simpan.',
                    self::format($qty),
                    $reason,
                ),
            ]);
        }
    }

    private static function format(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
