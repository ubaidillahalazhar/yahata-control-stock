<?php

namespace App\Http\Controllers;

use App\Enums\DamageAction;
use App\Enums\DamageReason;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\ListFilterRequest;
use App\Http\Requests\StoreDamageReportRequest;
use App\Http\Resources\DamageReportResource;
use App\Http\Resources\ToolingResource;
use App\Models\DamageReport;
use App\Models\ToolsInventory;
use App\Notifications\AdminAlert;
use App\Notifications\DamageReportNeedsReview;
use App\Notifications\DamageReportSubmitted;
use App\Services\DamageReportService;
use App\Services\ShotCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DamageReportController extends Controller
{
    private const FILTER_KEYS = ['date_from', 'date_to', 'shift', 'damage_reason', 'action', 'operator', 'search', 'count', 'review'];

    public function __construct(
        private readonly DamageReportService $reports,
        private readonly ShotCountService $shotCount,
    ) {}

    /**
     * Riwayat seluruh laporan kerusakan.
     */
    public function index(ListFilterRequest $request): Response
    {
        $filters = array_intersect_key($request->filters(), array_flip(self::FILTER_KEYS));
        $user = $request->user();

        $reports = DamageReport::query()
            ->with('tool')
            ->visibleTo($user)
            ->filter($filters)
            ->latest('reported_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $summary = DamageReport::query()
            ->visibleTo($user)
            ->filter($filters)
            ->selectRaw('damage_reason, COUNT(*) AS total_reports, COALESCE(SUM(qty_replaced), 0) AS total_qty')
            ->groupBy('damage_reason')
            ->get()
            ->mapWithKeys(fn (DamageReport $row): array => [
                $row->damage_reason->value => [
                    'reports' => (int) $row->getAttribute('total_reports'),
                    'qty' => (int) $row->getAttribute('total_qty'),
                ],
            ]);

        // Jumlah yang butuh perhatian (tanpa filter) → tombol filter cepat di atas tabel.
        $attention = [
            'count_pending' => DamageReport::query()->visibleTo($user)->filter(['count' => 'pending'])->count(),
            'count_overdue' => DamageReport::query()->visibleTo($user)->filter(['count' => 'overdue'])->count(),
            'needs_review' => DamageReport::query()->visibleTo($user)->filter(['review' => 'needed'])->count(),
        ];

        return Inertia::render('DamageReports/Index', [
            'reports' => DamageReportResource::collection($reports)->response()->getData(true),
            'summary' => $summary,
            'attention' => $attention,
            'filters' => (object) $filters,
            'reasons' => DamageReason::options(),
            'actions' => DamageAction::options(),
        ]);
    }

    /**
     * Module 3: Form mobile yang dibuka via scan QR (?tool_id=… atau ?machine=…&rack=…).
     */
    public function create(Request $request): Response
    {
        $tools = ToolsInventory::query()->ordered()->get();
        $toolId = $request->integer('tool_id');

        return Inertia::render('DamageReports/Create', [
            'tools' => ToolingResource::collection($tools)->resolve(),
            'selectedToolId' => $toolId && $tools->contains('id', $toolId) ? $toolId : null,
            'scope' => [
                'machine' => $request->string('machine')->trim()->toString() ?: null,
                'rack' => $request->string('rack')->trim()->toString() ?: null,
            ],
            'reasons' => DamageReason::options(),
            'actions' => DamageAction::options(),
        ]);
    }

    public function store(StoreDamageReportRequest $request): RedirectResponse
    {
        try {
            $report = $this->reports->submit([...$request->validated(), 'user_id' => $request->user()->id]);
        } catch (InsufficientStockException $exception) {
            throw $exception->toValidationException('qty_replaced');
        }

        $report->loadMissing('tool');

        // Admin pelapor tidak perlu diberi tahu tentang laporannya sendiri.
        // Selisih negatif → satu notifikasi "perlu ditinjau" (sudah memuat info laporannya).
        rescue(fn () => AdminAlert::toAdmins(
            $report->needs_review ? new DamageReportNeedsReview($report) : new DamageReportSubmitted($report),
            except: $request->user(),
        ));

        $message = $report->action->cutsStock()
            ? sprintf('Laporan %s tersimpan. Stok dipotong %d pcs (%d → %d). Life tool di-reset.', $report->report_no, $report->qty_replaced, $report->stock_before, $report->stock_after)
            : sprintf('Laporan %s tersimpan (Regrind / Repair — stok & life tidak berubah).', $report->report_no);

        if ($report->count_pending) {
            $message .= ' Shot count belum diisi — lengkapi dari halaman ini setelah angka counter dibawa.';
        }

        return to_route('damage-reports.show', $report)->with('success', $message);
    }

    /**
     * Lengkapi shot count laporan yang dibuat dengan "count belum dibawa" (pelapor atau admin).
     */
    public function fillCount(Request $request, DamageReport $damageReport): RedirectResponse
    {
        $this->authorizeView($request, $damageReport);

        $validated = $request->validate([
            'actual_shot_count' => ['required', 'integer', 'min:0', 'max:'.StoreDamageReportRequest::MAX_SHOT_COUNT],
        ], [], ['actual_shot_count' => 'Aktual shot count']);

        $report = $this->shotCount->fillPendingCount($damageReport, (int) $validated['actual_shot_count'])->load('tool');

        if ($report->needs_review) {
            rescue(fn () => AdminAlert::toAdmins(new DamageReportNeedsReview($report), except: $request->user()));
        }

        return back()->with('success', sprintf(
            'Shot count %s disimpan (%s pcs, selisih %s dari sistem).',
            $report->report_no,
            number_format((int) $report->actual_shot_count, 0, ',', '.'),
            ($report->shot_variance >= 0 ? '+' : '').number_format((int) $report->shot_variance, 0, ',', '.'),
        ));
    }

    /**
     * Admin menandai laporan "perlu ditinjau" sudah diperiksa (data tidak diubah otomatis).
     */
    public function review(Request $request, DamageReport $damageReport): RedirectResponse
    {
        $validated = $request->validate(['review_note' => ['nullable', 'string', 'max:255']]);

        if (! $damageReport->isAwaitingReview()) {
            return back()->with('error', 'Laporan ini tidak sedang menunggu tinjauan.');
        }

        $damageReport->forceFill([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => filled($validated['review_note'] ?? null) ? trim($validated['review_note']) : null,
        ])->save();

        return back()->with('success', sprintf('Laporan %s ditandai sudah ditinjau.', $damageReport->report_no));
    }

    private function authorizeView(Request $request, DamageReport $report): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $report->user_id === $user->id, 403, 'Laporan ini bukan milik akun Anda.');
    }

    /**
     * Slip / formulir resmi siap cetak.
     */
    public function show(Request $request, DamageReport $damageReport): Response
    {
        $this->authorizeView($request, $damageReport);

        $damageReport->load(['tool', 'stockTransaction', 'reviewer:id,name']);

        return Inertia::render('DamageReports/Show', [
            'report' => (new DamageReportResource($damageReport))->resolve(),
        ]);
    }
}
