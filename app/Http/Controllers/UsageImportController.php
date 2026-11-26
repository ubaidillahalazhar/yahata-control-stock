<?php

namespace App\Http\Controllers;

use App\Exports\UsageTemplateExport;
use App\Http\Requests\ApplyUsageImportRequest;
use App\Http\Requests\UploadUsageImportRequest;
use App\Services\UsageImportService;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Import Pemakaian Life Time (Day) dari Excel: template → upload & review → terapkan.
 */
class UsageImportController extends Controller
{
    public function __construct(private readonly UsageImportService $import) {}

    public function template(): BinaryFileResponse
    {
        return Excel::download(new UsageTemplateExport, 'template_pemakaian_life_time.xlsx');
    }

    /**
     * Baca & validasi file (belum menulis data pemakaian).
     */
    public function preview(UploadUsageImportRequest $request): RedirectResponse
    {
        try {
            $preview = $this->import->preview($request->file('usage_file'));
        } catch (SpreadsheetException|NoTypeDetectedException $exception) {
            return back()->with('error', 'File Excel tidak dapat dibaca: '.$exception->getMessage());
        }

        $summary = $preview['summary'];
        $message = $summary['error_rows'] > 0
            ? sprintf('%d dari %d baris bermasalah. Tidak ada yang dimasukkan — perbaiki file lalu upload ulang.', $summary['error_rows'], $summary['total_rows'])
            : sprintf('Preview siap: %d baris, %d tool. Periksa lalu klik "Terapkan".', $summary['valid_rows'], $summary['tools']);

        return back()
            ->with($summary['error_rows'] > 0 ? 'error' : 'success', $message)
            ->with('usage_preview', $preview);
    }

    /**
     * Masukkan semua baris valid dalam satu transaksi (nomor batch PLT-...).
     */
    public function apply(ApplyUsageImportRequest $request): RedirectResponse
    {
        $result = $this->import->apply(
            token: $request->validated('token'),
            includeDuplicateRows: $request->validated('include_duplicates') ?? [],
            confirmUnusual: $request->boolean('confirm_unusual'),
        );

        return back()
            ->with('success', sprintf(
                'Import pemakaian %s diterapkan: %d baris, %d tool, total %s pcs%s.',
                $result['batch_no'],
                $result['inserted'],
                $result['tools'],
                number_format($result['total_qty'], 0, ',', '.'),
                $result['skipped_duplicates'] > 0 ? sprintf(' (%d baris duplikat tidak dimasukkan)', $result['skipped_duplicates']) : '',
            ))
            ->with('usage_result', $result);
    }
}
