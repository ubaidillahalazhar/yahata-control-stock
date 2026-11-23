<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histori mutasi stok (audit log). Baris tidak pernah diubah setelah dibuat.
 *
 * @property int $id
 * @property int $tool_id
 * @property TransactionType $type
 * @property int $qty_change
 * @property int $stock_before
 * @property int $stock_after
 * @property string|null $reference_no
 * @property string|null $pic_name
 * @property string|null $notes
 * @property int|null $damage_report_id
 */
class StockTransaction extends Model
{
    protected $fillable = [
        'tool_id',
        'type',
        'qty_change',
        'stock_before',
        'stock_after',
        'reference_no',
        'pic_name',
        'notes',
        'damage_report_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'qty_change' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
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

    /**
     * Filter audit log: date_from, date_to, type, pic, search, tool_id.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $like = static fn (string $value): string => '%'.mb_strtolower(trim($value)).'%';

        $query
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['tool_id'] ?? null, fn (Builder $q, $toolId) => $q->where('tool_id', (int) $toolId))
            ->when($filters['pic'] ?? null, fn (Builder $q, string $pic) => $q->whereRaw('LOWER(pic_name) LIKE ?', [$like($pic)]))
            ->when($filters['search'] ?? null, function (Builder $q, string $search) use ($like): void {
                $q->where(function (Builder $inner) use ($search, $like): void {
                    $inner->whereRaw('LOWER(reference_no) LIKE ?', [$like($search)])
                        ->orWhereHas('tool', function (Builder $tool) use ($search, $like): void {
                            $tool->whereRaw('LOWER(tools_name_yes) LIKE ?', [$like($search)])
                                ->orWhereRaw('LOWER(tools_name) LIKE ?', [$like($search)])
                                ->orWhereRaw('LOWER(machine_name) LIKE ?', [$like($search)]);
                        });
                });
            });
    }
}
