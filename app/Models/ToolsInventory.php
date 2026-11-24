<?php

namespace App\Models;

use App\Services\ToolingCalculatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master tooling (1 baris sheet "control stock").
 *
 * @property int $id
 * @property int|null $no
 * @property string|null $customer
 * @property string|null $machine_name
 * @property string|null $step
 * @property string $tools_name
 * @property string|null $yes_name
 * @property string|null $part_name
 * @property string $tools_name_yes
 * @property string|null $rack_location
 * @property string|null $part_no
 * @property int $current_stock
 * @property int $life_time
 * @property array<int, float> $forecasts
 * @property array<int, string|null>|null $forecast_months
 * @property int $lead_time_days
 */
class ToolsInventory extends Model
{
    use HasFactory;

    protected $table = 'tools_inventory';

    protected $fillable = [
        'no',
        'customer',
        'machine_name',
        'step',
        'tools_name',
        'yes_name',
        'part_name',
        'tools_name_yes',
        'rack_location',
        'part_no',
        'current_stock',
        'life_time',
        'forecasts',
        'forecast_months',
        'lead_time_days',
    ];

    /** Cache hasil kalkulasi per instance (direset setiap kali model disimpan). */
    protected ?array $calculationCache = null;

    protected function casts(): array
    {
        return [
            'no' => 'integer',
            'current_stock' => 'integer',
            'life_time' => 'integer',
            'lead_time_days' => 'integer',
            'forecasts' => 'array',
            'forecast_months' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static function (self $tool): void {
            $tool->calculationCache = null;
        });
    }

    /**
     * Hasil 11 rumus Excel untuk item ini (lihat ToolingCalculatorService).
     */
    public function calculation(): array
    {
        return $this->calculationCache ??= app(ToolingCalculatorService::class)->forTool($this);
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'tool_id');
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class, 'tool_id');
    }

    /**
     * Urut sesuai kolom "no" pada Excel.
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('COALESCE(no, 2147483647)')->orderBy('id');
    }
}
