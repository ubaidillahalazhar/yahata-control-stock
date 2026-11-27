<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyUsageImportRequest;
use App\Http\Requests\UploadUsageImportRequest;
use App\Services\UsageImportService;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

/**
 * Import Pemakaian Life Time (Day) dari export Machine Result: upload (+ periode) → review → terapkan.
 */
class UsageImportController extends Controller
{
    public function __construct(private readonly UsageImportService $import) {}

    /**
     * Baca & validasi file (belum menulis data pemakaian).
     */
    public function preview(UploadUsageImportRequest $request): RedirectResponse
    {
        ini_set('memory_limit', '512M');

        try {
            $preview = $this->import->preview($request->file('usage_file'), $request->validated('period'));
        } catch (SpreadsheetException|NoTypeDetectedException $exception) {
            return back()->with('error', 'File Excel tidak dapat dibaca: '.$exception->getMessage());
        }

        $summary = $preview['summary'];
        $message = $summary['error_rows'] > 0
            ? sprintf('%d dari %d baris End bermasalah. Tidak ada yang dimasukkan — perbaiki file lalu upload ulang.', $summary['error_rows'], $summary['end_rows'])
            : sprintf('Preview %s siap: %d baris End, %d tool. Periksa lalu klik "Terapkan".', $preview['period_label'], $summary['valid_rows'], $summary['tools']);

        return back()
            ->with($summary['error_rows'] > 0 ? 'error' : 'success', $message)
            ->with('usage_preview', $preview);
    }

    /**
     * Masukkan semua baris dalam satu transaksi (batch PLT-...), menggantikan import periode yang sama.
     */
    public function apply(ApplyUsageImportRequest $request): RedirectResponse
    {
        $result = $this->import->apply(
            token: $request->validated('token'),
            includeRows: $request->validated('include_rows') ?? [],
            appliedBy: (string) $request->user()?->name,
        );

        return back()
            ->with('success', sprintf(
                'Import Machine Result %s (%s) diterapkan: %d baris pemakaian, %d tool, total %s pcs%s.',
                $result['batch_no'],
                $result['period'],
                $result['inserted'],
                $result['tools'],
                number_format($result['total_qty'], 0, ',', '.'),
                $result['replaced'] > 0 ? sprintf(' · %d baris import lama periode ini dibatalkan', $result['replaced']) : '',
            ))
            ->with('usage_result', $result);
    }
}
