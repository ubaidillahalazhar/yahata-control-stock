<?php

namespace App\Services;

use App\Enums\DamageAction;
use App\Enums\DamageReason;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\DamageReport;
use Illuminate\Support\Facades\DB;

final class DamageReportService
{
    public function __construct(
        private readonly StockMutationService $mutations,
        private readonly ShotCountService $shotCount,
    ) {}

    /**
     * Simpan laporan kerusakan — semuanya dalam satu transaksi database:
     * - REPLACE_NEW: stok dipotong (transaksi DAMAGE_REPLACEMENT), lalu siklus life ditutup
     *   (snapshot + koreksi count + reset life). Count boleh "belum dibawa" (diisi belakangan).
     * - REGRIND_REPAIR: stok & life tidak berubah (aturan regrind masih ditunda).
     *
     * @param  array{tool_id: int, user_id?: ?int, operator_name: string, shift: int, qty_replaced: int, actual_shot_count?: ?int, count_pending?: bool, damage_reason: string, action: string, notes?: ?string}  $data
     *
     * @throws InsufficientStockException
     */
    public function submit(array $data): DamageReport
    {
        return DB::transaction(function () use ($data): DamageReport {
            $tool = $this->mutations->lockTool((int) $data['tool_id']);
            $action = DamageAction::from($data['action']);
            $reason = DamageReason::from($data['damage_reason']);
            $qty = (int) $data['qty_replaced'];
            $stockBefore = (int) $tool->current_stock;
            $countPending = $action === DamageAction::ReplaceNew && (bool) ($data['count_pending'] ?? false);
            $shotCount = $countPending || ! isset($data['actual_shot_count']) ? null : (int) $data['actual_shot_count'];

            if ($action->cutsStock() && $stockBefore < $qty) {
                throw new InsufficientStockException($tool, $stockBefore, $qty);
            }

            $report = $tool->damageReports()->create([
                'user_id' => $data['user_id'] ?? null,
                'operator_name' => trim($data['operator_name']),
                'shift' => (int) $data['shift'],
                'qty_replaced' => $qty,
                'actual_shot_count' => $shotCount,
                'count_pending' => $countPending,
                'damage_reason' => $reason,
                'action' => $action,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'reported_at' => now(),
            ]);

            // Nomor dokumen berbasis ID → unik tanpa race condition.
            $report->report_no = sprintf('DR-%s-%05d', $report->reported_at->format('Ymd'), $report->id);

            if ($action->cutsStock()) {
                $transaction = $this->mutations->applyToLockedTool($tool, TransactionType::DamageReplacement, -$qty, [
                    'reference_no' => $report->report_no,
                    'pic_name' => $report->operator_name,
                    'notes' => sprintf(
                        'Shift %d · %s · Shot count %s',
                        $report->shift,
                        $reason->label(),
                        $shotCount === null ? 'menunggu' : number_format($shotCount, 0, ',', '.'),
                    ),
                    'damage_report_id' => $report->id,
                ]);

                $report->stock_after = $transaction->stock_after;
            }

            if ($action === DamageAction::ReplaceNew) {
                $this->shotCount->closeCycle($report, $tool);
            }

            $report->save();

            return $report;
        }, StockMutationService::TRANSACTION_ATTEMPTS);
    }
}
