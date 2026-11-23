<?php

namespace App\Http\Controllers;

use App\Enums\ImportIssueStatus;
use App\Models\ToolImportIssue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Excel Belum Lengkap": baris master Excel yang ditahan pada import terakhir.
 */
class ImportIssueController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = array_filter($request->validate([
            'status' => ['nullable', Rule::enum(ImportIssueStatus::class)],
            'field' => ['nullable', 'string', 'max:30'],
        ]), fn ($value): bool => $value !== null && $value !== '');

        $issues = ToolImportIssue::query()->orderBy('excel_row')->get();

        $counts = ['total' => $issues->count()];

        foreach (ImportIssueStatus::cases() as $status) {
            $counts[$status->value] = $issues->where('status', $status)->count();
        }

        $fieldCounts = [];

        foreach ($issues as $issue) {
            foreach ($issue->problems as $problem) {
                $key = $problem['field'];
                $fieldCounts[$key] ??= ['field' => $key, 'column' => $problem['column'], 'label' => $problem['label'], 'count' => 0];
                $fieldCounts[$key]['count']++;
            }
        }

        $visible = $issues
            ->when($filters['status'] ?? null, fn ($rows, string $status) => $rows->where('status', ImportIssueStatus::from($status)))
            ->when($filters['field'] ?? null, fn ($rows, string $field) => $rows->filter(
                fn (ToolImportIssue $issue): bool => in_array($field, array_column($issue->problems, 'field'), true),
            ));

        return Inertia::render('ImportIssues/Index', [
            'issues' => $visible->values()->map(fn (ToolImportIssue $issue): array => [
                'id' => $issue->id,
                'excel_row' => $issue->excel_row,
                'tools_name_yes' => $issue->tools_name_yes,
                'tool_id' => $issue->tool_id,
                'status' => $issue->status->value,
                'status_label' => $issue->status->label(),
                'problems' => $issue->problems,
                'row_data' => $issue->row_data,
            ]),
            'counts' => $counts,
            'fieldCounts' => array_values($fieldCounts),
            'filters' => (object) $filters,
            'referenceNo' => $issues->first()?->reference_no,
            'importedAt' => $issues->first()?->created_at?->format('d/m/Y H:i'),
        ]);
    }
}
