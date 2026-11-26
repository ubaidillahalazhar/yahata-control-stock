<?php

namespace App\Models;

use App\Enums\DamageAction;
use App\Enums\DamageReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int|null $actual_shot_count  angka counter mesin; null = count belum dibawa
 * @property int|null $system_shot_count  snapshot life_used sistem saat laporan dibuat
 * @property int|null $life_time_snapshot
 * @property int|null $shot_variance  actual - system (negatif = Excel kelebihan input)
 * @property bool $count_pending
 * @property Carbon|null $count_filled_at
 * @property Carbon|null $overdue_notified_at
 * @property bool $needs_review
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property DamageReason $damage_reason
 * @property DamageAction $action
 * @property int $stock_before
 * @property int $stock_after
 * @property string|null $notes
 * @property Carbon $reported_at
 */
class DamageReport extends Model
{
    /** Laporan "menunggu count" lebih lama dari ini → peringatan merah + notifikasi admin. */
    public const COUNT_OVERDUE_HOURS = 24;

    protected $fillable = [
        'report_no',
        'tool_id',
        'user_id',
        'operator_name',
        'shift',
        'qty_replaced',
        'actual_shot_count',
        'system_shot_count',
        'life_time_snapshot',
        'shot_variance',
        'count_pending',
        'count_filled_at',
        'needs_review',
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
            'system_shot_count' => 'integer',
            'life_time_snapshot' => 'integer',
            'shot_variance' => 'integer',
            'count_pending' => 'boolean',
            'count_filled_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Koreksi count (ADJUST) & reset life yang dibuat otomatis oleh laporan ini. */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(ToolUsageLog::class);
    }

    /** Count belum diisi lebih dari batas waktu → peringatan merah + notifikasi admin. */
    public function isCountOverdue(): bool
    {
        return $this->count_pending && $this->reported_at->lte(now()->subHours(self::COUNT_OVERDUE_HOURS));
    }

    public function isAwaitingReview(): bool
    {
        return $this->needs_review && $this->reviewed_at === null;
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
            ->when(($filters['count'] ?? null) === 'pending', fn (Builder $q) => $q->where('count_pending', true))
            ->when(($filters['count'] ?? null) === 'overdue', fn (Builder $q) => $q->where('count_pending', true)
                ->where('reported_at', '<=', now()->subHours(self::COUNT_OVERDUE_HOURS)))
            ->when(($filters['review'] ?? null) === 'needed', fn (Builder $q) => $q->where('needs_review', true)->whereNull('reviewed_at'))
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
