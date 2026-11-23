<?php

namespace App\Http\Controllers;

use App\Exports\StockOpnameTemplateExport;
use App\Http\Requests\ApplyOpnameRequest;
use App\Http\Requests\UploadOpnameRequest;
use App\Services\StockOpnameService;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockOpnameController extends Controller
{
    public function __construct(private readonly StockOpnameService $opname) {}

    public function template(): BinaryFileResponse
    {
        return Excel::download(new StockOpnameTemplateExport, 'Template_Stock_Opname_'.now()->format('Ymd_His').'.xlsx');
    }

    /**
     * Baca file hasil opname dan tampilkan preview selisih (belum mengubah stok).
     */
    public function preview(UploadOpnameRequest $request): RedirectResponse
    {
        try {
            $preview = $this->opname->preview($request->file('opname_file'));
        } catch (SpreadsheetException|NoTypeDetectedException $exception) {
            return back()->with('error', 'File Excel tidak dapat dibaca: '.$exception->getMessage());
        }

        $message = $preview['summary']['rows_with_input'] > 0
            ? sprintf('Preview opname siap: %d item terisi, %d item berselisih.', $preview['summary']['rows_with_input'], $preview['summary']['with_difference'])
            : 'Tidak ada baris "Actual Physical Stock" yang terisi pada file.';

        return back()
            ->with($preview['summary']['rows_with_input'] > 0 ? 'success' : 'error', $message)
            ->with('opname_preview', $preview);
    }

    /**
     * Terapkan hasil opname (satu transaksi DB, lockForUpdate, selisih → OPNAME_ADJUST).
     */
    public function apply(ApplyOpnameRequest $request): RedirectResponse
    {
        $result = $this->opname->apply(
            items: $request->validated('items'),
            picName: $request->validated('pic_name'),
            notes: $request->validated('notes'),
            referenceNo: $request->validated('reference_no'),
        );

        return back()
            ->with('success', sprintf(
                'Stock opname %s diterapkan: %d item disesuaikan (+%d / -%d pcs), %d item sesuai.',
                $result['reference_no'],
                $result['adjusted'],
                $result['total_plus'],
                $result['total_minus'],
                $result['matched'],
            ))
            ->with('opname_result', $result);
    }
}
