<?php

namespace App\Models;

use App\Enums\UsageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris = satu kali input pemakaian life time (tanggal bebas / acak).
 * Baris tidak pernah diedit atau dihapus; koreksi dilakukan dengan membatalkan (cancelled_at).
 *
 * @property int $id
 * @property int $tool_id
 * @property UsageType $type
 * @property Carbon $usage_date
 * @property int $qty
 * @property int $life_used_before
 * @property int $life_used_after
 * @property string $pic_name
 * @property string|null $notes
 * @property string|null $batch_no
 * @property Carbon|null $cancelled_at
 * @property string|null $cancelled_by
 * @property string|null $cancel_reason
 */
class ToolUsageLog extends Model
{
    protected $fillable = [
        'tool_id',
        'type',
        'usage_date',
        'qty',
        'life_used_before',
        'life_used_after',
        'pic_name',
        'notes',
        'batch_no',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => UsageType::class,
            'usage_date' => 'date',
            'qty' => 'integer',
            'life_used_before' => 'integer',
            'life_used_after' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(ToolsInventory::class, 'tool_id');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    /**
     * Filter riwayat: tool_id, batch (nomor batch import Excel), month (YYYY-MM), type, state (active | cancelled).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['tool_id'] ?? null, fn (Builder $q, $toolId) => $q->where('tool_id', (int) $toolId))
            ->when($filters['batch'] ?? null, fn (Builder $q, string $batch) => $q->where('batch_no', $batch))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['month'] ?? null, function (Builder $q, string $month): void {
                $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();

                $q->whereDate('usage_date', '>=', $start->toDateString())
                    ->whereDate('usage_date', '<=', $start->copy()->endOfMonth()->toDateString());
            })
            ->when(($filters['state'] ?? null) === 'active', fn (Builder $q) => $q->whereNull('cancelled_at'))
            ->when(($filters['state'] ?? null) === 'cancelled', fn (Builder $q) => $q->whereNotNull('cancelled_at'));
    }
}
