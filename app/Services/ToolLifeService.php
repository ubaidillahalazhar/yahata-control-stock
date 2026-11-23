<?php

namespace App\Services;

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

    public function recordUsage(int $toolId, string $usageDate, int $qty, string $picName, ?string $notes = null): ToolUsageLog
    {
        return $this->write($toolId, UsageType::Usage, $usageDate, $qty, $picName, $notes);
    }

    /**
     * Tool diganti baru: life terpakai kembali ke 0 mulai tanggal ini.
     * Stok tidak diubah di sini (pengambilan tool cadangan dicatat lewat Transaksi OUT).
     */
    public function reset(int $toolId, string $usageDate, string $picName, ?string $notes = null): ToolUsageLog
    {
        return $this->write($toolId, UsageType::Reset, $usageDate, 0, $picName, $notes);
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
     * Hitung ulang life terpakai dari log (dipakai juga untuk perbaikan data).
     */
    public function recalculate(ToolsInventory $tool): int
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
            ->where('type', UsageType::Usage->value);

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

    private function write(int $toolId, UsageType $type, string $usageDate, int $qty, string $picName, ?string $notes): ToolUsageLog
    {
        return DB::transaction(function () use ($toolId, $type, $usageDate, $qty, $picName, $notes): ToolUsageLog {
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
