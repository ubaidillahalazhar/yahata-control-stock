<?php

namespace App\Http\Controllers;

use App\Exports\ControlStockExport;
use App\Http\Requests\ImportMasterRequest;
use App\Models\StockTransaction;
use App\Models\ToolsInventory;
use App\Services\ToolingMasterSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelSyncController extends Controller
{
    public function __construct(private readonly ToolingMasterSyncService $sync) {}

    /**
     * Module 4: Excel Master Sync & Stock Opname.
     */
    public function index(): Response
    {
        $lastOpname = StockTransaction::query()
            ->where('reference_no', 'like', 'OPN-%')
            ->latest('id')
            ->first(['reference_no', 'pic_name', 'created_at']);

        return Inertia::render('Sync/Index', [
            'stats' => [
                'total_tools' => ToolsInventory::query()->count(),
                'last_master_update' => ($lastUpdate = ToolsInventory::query()->max('updated_at'))
                    ? Carbon::parse($lastUpdate)->format('d/m/Y H:i')
                    : null,
                'last_opname' => $lastOpname ? [
                    'reference_no' => $lastOpname->reference_no,
                    'pic_name' => $lastOpname->pic_name,
                    'created_at' => $lastOpname->created_at?->format('d/m/Y H:i'),
                ] : null,
            ],
        ]);
    }

    public function importMaster(ImportMasterRequest $request): RedirectResponse
    {
        try {
            $result = $this->sync->import(
                file: $request->file('master_file'),
                syncStock: $request->boolean('sync_stock'),
                picName: $request->validated('pic_name'),
            );
        } catch (SpreadsheetException|NoTypeDetectedException $exception) {
            return back()->with('error', 'File Excel tidak dapat dibaca: '.$exception->getMessage());
        }

        return back()
            ->with('success', sprintf(
                'Import master selesai: %d item baru, %d item diperbarui, %d baris belum lengkap (lihat menu Excel Belum Lengkap).',
                $result['created'],
                $result['updated'],
                $result['skipped'],
            ))
            ->with('import_result', $result);
    }

    public function exportControlStock(): BinaryFileResponse
    {
        return Excel::download(new ControlStockExport, 'yahata_tools_control_stock_'.now()->format('Ymd_His').'.xlsx');
    }
}
