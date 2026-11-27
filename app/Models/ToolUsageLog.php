<?php

namespace App\Models;

use App\Enums\UsageSource;
use App\Enums\UsageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Satu baris = satu kali input pemakaian life time (tanggal bebas / acak).
 * Baris tidak pernah diedit atau dihapus. Koreksi = baris lama dibatalkan (cancelled_at) dan
 * baris baru dibuat dengan `corrects_id` menunjuk baris lama, sehingga riwayat tetap utuh.
 *
 * PIC hanya boleh mengoreksi / membatalkan input Pemakaian miliknya sendiri dalam
 * PIC_CHANGE_WINDOW_HOURS jam sejak input pertama; setelah itu hanya admin.
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
 * @property UsageSource $source
 * @property int|null $damage_report_id
 * @property int|null $user_id
 * @property int|null $corrects_id
 * @property Carbon|null $first_input_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancelled_by
 * @property string|null $cancel_reason
 */
class ToolUsageLog extends Model
{
    public const PIC_CHANGE_WINDOW_HOURS = 24;

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
        'import_period',
        'source',
        'damage_report_id',
        'user_id',
        'corrects_id',
        'first_input_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => UsageType::class,
            'source' => UsageSource::class,
            'usage_date' => 'date',
            'qty' => 'integer',
            'life_used_before' => 'integer',
            'life_used_after' => 'integer',
            'cancelled_at' => 'datetime',
            'first_input_at' => 'datetime',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(ToolsInventory::class, 'tool_id');
    }

    public function damageReport(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Baris lama yang dikoreksi oleh baris ini. */
    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_id');
    }

    /** Baris pengganti bila baris ini sudah dikoreksi. */
    public function correctedBy(): HasOne
    {
        return $this->hasOne(self::class, 'corrects_id');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Dasar batas waktu PIC: input pertama dalam rantai koreksi (koreksi tidak memperpanjang batas). */
    public function changeWindowEndsAt(): ?Carbon
    {
        $start = $this->first_input_at ?? $this->created_at;

        return $start?->copy()->addHours(self::PIC_CHANGE_WINDOW_HOURS);
    }

    /**
     * Boleh dikoreksi / dibatalkan oleh user ini?
     * Admin: semua baris aktif. PIC: hanya Pemakaian (bukan Ganti Tool / koreksi count / import Excel)
     * yang ia input sendiri, dalam 24 jam sejak input pertama.
     */
    public function canBeChangedBy(?User $user): bool
    {
        if ($user === null || $this->isCancelled()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->type === UsageType::Usage
            && $this->batch_no === null
            && $this->user_id === $user->id
            && $this->changeWindowEndsAt()?->isFuture() === true;
    }

    public function isCorrectable(): bool
    {
        return $this->type === UsageType::Usage && ! $this->isCancelled();
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
