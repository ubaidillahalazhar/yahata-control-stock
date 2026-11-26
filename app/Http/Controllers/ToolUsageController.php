<?php

namespace App\Http\Controllers;

use App\Enums\UsageType;
use App\Http\Requests\CancelToolUsageRequest;
use App\Http\Requests\CorrectToolUsageRequest;
use App\Http\Requests\StoreToolUsageRequest;
use App\Http\Resources\ToolingResource;
use App\Http\Resources\ToolUsageLogResource;
use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use App\Services\ToolLifeService;
use App\Services\UsageAnomalyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pemakaian Life Time (Day) — input pemakaian harian (tanggal acak, 1 baris = 1 input).
 */
class ToolUsageController extends Controller
{
    public function __construct(private readonly ToolLifeService $life) {}

    public function index(Request $request): Response
    {
        $filters = array_filter($request->validate([
            'tool_id' => ['nullable', 'integer'],
            'batch' => ['nullable', 'string', 'max:40'],
            'month' => ['nullable', 'date_format:Y-m'],
            'type' => ['nullable', Rule::enum(UsageType::class)],
            'state' => ['nullable', Rule::in(['active', 'cancelled'])],
        ]), fn ($value): bool => $value !== null && $value !== '');

        $logs = ToolUsageLog::query()
            ->with(['tool:id,tools_name_yes,tools_name,machine_name,life_time', 'corrects:id,usage_date,qty'])
            ->filter($filters)
            ->orderByDesc('usage_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $monthTotal = ToolUsageLog::query()
            ->filter([...$filters, 'state' => 'active', 'type' => UsageType::Usage->value])
            ->selectRaw('COUNT(*) AS total_rows, COALESCE(SUM(qty), 0) AS total_qty')
            ->first();

        return Inertia::render('ToolUsages/Index', [
            'tools' => ToolingResource::collection(ToolsInventory::query()->ordered()->get())->resolve(),
            'logs' => ToolUsageLogResource::collection($logs)->response()->getData(true),
            'filters' => (object) $filters,
            'totals' => [
                'rows' => (int) $monthTotal?->getAttribute('total_rows'),
                'qty' => (int) $monthTotal?->getAttribute('total_qty'),
            ],
            'changeWindowHours' => ToolUsageLog::PIC_CHANGE_WINDOW_HOURS,
        ]);
    }

    public function store(StoreToolUsageRequest $request): RedirectResponse
    {
        UsageAnomalyService::assertConfirmed(
            (int) $request->validated('tool_id'),
            (int) $request->validated('qty'),
            $request->boolean('confirm_unusual'),
        );

        $log = $this->life->recordUsage(
            toolId: (int) $request->validated('tool_id'),
            usageDate: $request->validated('usage_date'),
            qty: (int) $request->validated('qty'),
            picName: $request->validated('pic_name'),
            notes: $request->validated('notes'),
        );

        return back()->with('success', $this->message($log, sprintf('Pemakaian %s pcs tercatat', number_format($log->qty, 0, ',', '.'))));
    }

    public function reset(StoreToolUsageRequest $request, ToolsInventory $tool): RedirectResponse
    {
        $log = $this->life->reset(
            toolId: $tool->id,
            usageDate: $request->validated('usage_date'),
            picName: $request->validated('pic_name'),
            notes: $request->validated('notes'),
        );

        return back()->with('success', $this->message($log, 'Tool diganti, life terpakai di-reset'));
    }

    public function cancel(CancelToolUsageRequest $request, ToolUsageLog $usage): RedirectResponse
    {
        $this->authorizeChange($request, $usage);

        $log = $this->life->cancel($usage, $request->validated('pic_name'), $request->validated('cancel_reason'));

        return back()->with('success', $this->message($log, 'Input dibatalkan'));
    }

    /**
     * Koreksi jumlah / tanggal: baris lama dibatalkan, baris baru menunjuk baris lama.
     */
    public function correct(CorrectToolUsageRequest $request, ToolUsageLog $usage): RedirectResponse
    {
        $this->authorizeChange($request, $usage);

        UsageAnomalyService::assertConfirmed(
            $usage->tool_id,
            (int) $request->validated('qty'),
            $request->boolean('confirm_unusual'),
            excludeLogId: $usage->id,
        );

        $log = $this->life->correct(
            log: $usage,
            usageDate: $request->validated('usage_date'),
            qty: (int) $request->validated('qty'),
            picName: $request->validated('pic_name'),
            reason: $request->validated('reason'),
        );

        return back()->with('success', $this->message($log, sprintf(
            'Input dikoreksi: %s pcs → %s pcs',
            number_format((int) $usage->qty, 0, ',', '.'),
            number_format($log->qty, 0, ',', '.'),
        )));
    }

    /**
     * Batalkan seluruh input dari satu batch import Excel (PLT-...).
     */
    public function cancelBatch(CancelToolUsageRequest $request, string $batch): RedirectResponse
    {
        $cancelled = $this->life->cancelBatch($batch, $request->validated('pic_name'), $request->validated('cancel_reason'));

        return back()->with('success', sprintf('Batch %s dibatalkan: %d input dicoret, life time tool terkait dihitung ulang.', $batch, $cancelled));
    }

    /**
     * PIC: hanya input Pemakaian miliknya sendiri, maks. 24 jam sejak input pertama. Admin: semua.
     *
     * @throws ValidationException
     */
    private function authorizeChange(Request $request, ToolUsageLog $usage): void
    {
        if ($usage->canBeChangedBy($request->user())) {
            return;
        }

        $message = match (true) {
            $usage->isCancelled() => 'Input ini sudah dibatalkan / dikoreksi sebelumnya.',
            $usage->user_id !== $request->user()?->id || $usage->batch_no !== null || ! $usage->isCorrectable()
                => 'Anda hanya bisa mengubah input Pemakaian yang Anda catat sendiri. Hubungi admin untuk input ini.',
            default => sprintf(
                'Batas %d jam untuk mengubah input ini sudah lewat (sampai %s). Hubungi admin untuk koreksi.',
                ToolUsageLog::PIC_CHANGE_WINDOW_HOURS,
                $usage->changeWindowEndsAt()?->format('d/m/Y H:i'),
            ),
        };

        throw ValidationException::withMessages(['usage' => $message]);
    }

    private function message(ToolUsageLog $log, string $prefix): string
    {
        $tool = ToolsInventory::query()->findOrFail($log->tool_id);
        $life = $tool->lifeSummary();

        return sprintf(
            '%s — %s. Life terpakai %s%% (sisa %s pcs) · Status Life %s.',
            $prefix,
            $tool->tools_name_yes,
            number_format($life['life_usage_percent'], 1, ',', '.'),
            number_format($life['life_remaining'], 0, ',', '.'),
            $life['life_status'],
        );
    }
}
