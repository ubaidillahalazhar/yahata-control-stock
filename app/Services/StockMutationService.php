<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\StockTransaction;
use App\Models\ToolsInventory;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya pintu perubahan `current_stock`.
 *
 * Setiap mutasi: DB::transaction() → SELECT ... FOR UPDATE → validasi → update stok → catat histori.
 * Dengan begitu dua request bersamaan pada item yang sama akan diproses berurutan (tanpa race condition).
 */
final class StockMutationService
{
    /** Retry otomatis bila terjadi deadlock / serialization failure. */
    public const TRANSACTION_ATTEMPTS = 3;

    /**
     * Mutasi stok atomik untuk satu item (membuka transaksi database sendiri).
     *
     * @param  array{reference_no?: ?string, pic_name?: ?string, notes?: ?string, damage_report_id?: ?int}  $attributes
     */
    public function record(int $toolId, TransactionType $type, int $qtyChange, array $attributes = []): StockTransaction
    {
        return DB::transaction(function () use ($toolId, $type, $qtyChange, $attributes): StockTransaction {
            $tool = $this->lockTool($toolId);

            return $this->applyToLockedTool($tool, $type, $qtyChange, $attributes);
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Ambil baris tool dengan row-level lock. Wajib dipanggil di dalam transaksi.
     */
    public function lockTool(int $toolId): ToolsInventory
    {
        return ToolsInventory::query()->whereKey($toolId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Terapkan perubahan stok pada tool yang SUDAH di-lockForUpdate() di dalam DB::transaction().
     *
     * @param  array{reference_no?: ?string, pic_name?: ?string, notes?: ?string, damage_report_id?: ?int}  $attributes
     *
     * @throws InsufficientStockException
     */
    public function applyToLockedTool(ToolsInventory $tool, TransactionType $type, int $qtyChange, array $attributes = []): StockTransaction
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('applyToLockedTool() harus dipanggil di dalam DB::transaction().');
        }

        $before = (int) $tool->current_stock;
        $after = $before + $qtyChange;

        if ($after < 0) {
            throw new InsufficientStockException($tool, $before, abs($qtyChange));
        }

        $tool->forceFill(['current_stock' => $after])->save();

        return $tool->stockTransactions()->create([
            'type' => $type,
            'qty_change' => $qtyChange,
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_no' => self::clean($attributes['reference_no'] ?? null),
            'pic_name' => self::clean($attributes['pic_name'] ?? null),
            'notes' => self::clean($attributes['notes'] ?? null),
            'damage_report_id' => $attributes['damage_report_id'] ?? null,
        ]);
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
