<?php

namespace App\Models;

use App\Enums\ImportIssueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris Excel master yang belum lengkap pada import terakhir.
 *
 * @property int $id
 * @property string $reference_no
 * @property int $excel_row
 * @property string|null $tools_name_yes
 * @property int|null $tool_id
 * @property ImportIssueStatus $status
 * @property list<array{field: string, column: string, label: string, reason: string}> $problems
 * @property array<string, mixed> $row_data
 */
class ToolImportIssue extends Model
{
    protected $fillable = [
        'reference_no',
        'excel_row',
        'tools_name_yes',
        'tool_id',
        'status',
        'problems',
        'row_data',
    ];

    protected function casts(): array
    {
        return [
            'excel_row' => 'integer',
            'status' => ImportIssueStatus::class,
            'problems' => 'array',
            'row_data' => 'array',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(ToolsInventory::class, 'tool_id');
    }
}
