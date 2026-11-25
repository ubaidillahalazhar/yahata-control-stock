<?php

namespace App\Models;

use App\Enums\DamageAction;
use App\Enums\DamageReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Laporan kerusakan / penggantian part dari operator (form QR).
 *
 * @property int $id
 * @property string|null $report_no
 * @property int $tool_id
 * @property int|null $user_id
 * @property string $operator_name
 * @property int $shift
 * @property int $qty_replaced
 * @property int $actual_shot_count
 * @property DamageReason $damage_reason
 * @property DamageAction $action
 * @property int $stock_before
 * @property int $stock_after
 * @property string|null $notes
 * @property Carbon $reported_at
 */
class DamageReport extends Model
{
    protected $fillable = [
        'report_no',
        'tool_id',
        'user_id',
        'operator_name',
        'shift',
        'qty_replaced',
        'actual_shot_count',
        'damage_reason',
        'action',
        'stock_before',
        'stock_after',
        'notes',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'shift' => 'integer',
            'qty_replaced' => 'integer',
            'actual_shot_count' => 'integer',
            'damage_reason' => DamageReason::class,
            'action' => DamageAction::class,
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'reported_at' => 'datetime',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(ToolsInventory::class, 'tool_id');
    }

    /** Akun pelapor (null untuk laporan lama sebelum ada login). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** PIC hanya melihat laporannya sendiri; admin melihat semua. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when(! $user->isAdmin(), fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public function stockTransaction(): HasOne
    {
        return $this->hasOne(StockTransaction::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $like = static fn (string $value): string => '%'.mb_strtolower(trim($value)).'%';

        $query
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('reported_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('reported_at', '<=', $date))
            ->when($filters['shift'] ?? null, fn (Builder $q, $shift) => $q->where('shift', (int) $shift))
            ->when($filters['damage_reason'] ?? null, fn (Builder $q, string $reason) => $q->where('damage_reason', $reason))
            ->when($filters['action'] ?? null, fn (Builder $q, string $action) => $q->where('action', $action))
            ->when($filters['operator'] ?? null, fn (Builder $q, string $name) => $q->whereRaw('LOWER(operator_name) LIKE ?', [$like($name)]))
            ->when($filters['search'] ?? null, function (Builder $q, string $search) use ($like): void {
                $q->where(function (Builder $inner) use ($search, $like): void {
                    $inner->whereRaw('LOWER(report_no) LIKE ?', [$like($search)])
                        ->orWhereHas('tool', function (Builder $tool) use ($search, $like): void {
                            $tool->whereRaw('LOWER(tools_name_yes) LIKE ?', [$like($search)])
                                ->orWhereRaw('LOWER(machine_name) LIKE ?', [$like($search)]);
                        });
                });
            });
    }
}
