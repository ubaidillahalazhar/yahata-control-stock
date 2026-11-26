import { useForm } from '@inertiajs/react';
import { Clock, Gauge, SearchCheck, TriangleAlert } from 'lucide-react';
import { Button, Field, Input, cn } from '@/Components/ui';
import { formatInt, formatSigned } from '@/lib/format';
import { compareCounter, confirmMessage } from '@/lib/shotCount';

/**
 * Ringkasan shot count di slip laporan (ikut tercetak).
 */
export function ShotCountSummary({ report }) {
    const closesCycle = report.system_shot_count !== null && report.system_shot_count !== undefined;

    return (
        <div className="mt-4 grid grid-cols-2 gap-3 rounded-lg border border-slate-200 p-3 text-sm sm:grid-cols-4 print:grid-cols-4">
            <Cell label="Shot count (counter)">
                {report.count_pending ? (
                    <span className={cn('font-bold', report.count_overdue ? 'text-red-700' : 'text-amber-700')}>
                        Menunggu count
                    </span>
                ) : (
                    formatInt(report.actual_shot_count)
                )}
            </Cell>
            <Cell label="Life time standar">{formatInt(report.life_time_snapshot ?? report.tool?.life_time)}</Cell>
            <Cell label="Pencapaian life">
                {report.life_time_usage_pct !== null ? `${String(report.life_time_usage_pct).replace('.', ',')}%` : '—'}
            </Cell>
            <Cell label="Selisih vs sistem">
                {closesCycle && report.shot_variance !== null ? (
                    <span className={cn(report.shot_variance < 0 && 'text-amber-700')}>
                        {formatSigned(report.shot_variance)}
                        <span className="block text-[11px] font-normal text-slate-500">
                            sistem {formatInt(report.system_shot_count)}
                        </span>
                    </span>
                ) : (
                    '—'
                )}
            </Cell>
        </div>
    );
}

function Cell({ label, children }) {
    return (
        <div>
            <p className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">{label}</p>
            <p className="mt-0.5 font-mono font-extrabold text-slate-900 tabular-nums">{children}</p>
        </div>
    );
}

/**
 * Aksi di layar (tidak tercetak): isi count tertunda (pelapor / admin), tandai ditinjau (admin).
 */
export function ShotCountActions({ report, isAdmin }) {
    if (report.count_pending) {
        return <FillCountForm report={report} />;
    }

    if (report.awaiting_review) {
        return <ReviewPanel report={report} isAdmin={isAdmin} />;
    }

    if (report.needs_review && report.reviewed_at_display) {
        return (
            <Panel tone="slate" icon={SearchCheck}>
                Selisih negatif sudah ditinjau {report.reviewed_by ? `oleh ${report.reviewed_by}` : ''} pada{' '}
                {report.reviewed_at_display}.{report.review_note && <> Catatan: {report.review_note}</>}
            </Panel>
        );
    }

    return null;
}

function FillCountForm({ report }) {
    const { data, setData, post, processing, errors } = useForm({ actual_shot_count: '' });
    const comparison = compareCounter(data.actual_shot_count, report.system_shot_count ?? 0);

    const submit = (event) => {
        event.preventDefault();
        if (comparison?.confirm && !window.confirm(confirmMessage(comparison))) {
            return;
        }
        post(`/damage-reports/${report.id}/count`, { preserveScroll: true });
    };

    return (
        <Panel tone={report.count_overdue ? 'red' : 'amber'} icon={Clock}>
            <p className="font-bold">
                Shot count belum diisi{report.count_overdue && ' — sudah lebih dari 24 jam'}.
            </p>
            <p className="text-xs">
                Isi angka counter mesin saat tool ini rusak. Sistem mencatat{' '}
                <b>{formatInt(report.system_shot_count)}</b> pcs saat laporan dibuat.
            </p>
            <form onSubmit={submit} className="mt-3 flex flex-wrap items-end gap-2">
                <Field label="Aktual shot count" error={errors.actual_shot_count} className="w-48">
                    <Input
                        type="number"
                        inputMode="numeric"
                        min={0}
                        value={data.actual_shot_count}
                        onChange={(event) => setData('actual_shot_count', event.target.value)}
                        invalid={Boolean(errors.actual_shot_count)}
                        placeholder="angka counter"
                        className="bg-white tabular-nums"
                        autoFocus
                    />
                </Field>
                <Button type="submit" loading={processing} disabled={data.actual_shot_count === ''}>
                    <Gauge className="size-4" /> Simpan Count
                </Button>
            </form>
            {comparison && comparison.variance !== 0 && (
                <p className="mt-2 text-xs font-semibold">
                    Selisih {formatSigned(comparison.variance)} pcs dari sistem
                    {comparison.variance < 0 && ' — akan ditandai untuk ditinjau admin'}.
                </p>
            )}
        </Panel>
    );
}

function ReviewPanel({ report, isAdmin }) {
    const { data, setData, post, processing } = useForm({ review_note: '' });

    return (
        <Panel tone="amber" icon={TriangleAlert}>
            <p className="font-bold">Perlu ditinjau admin</p>
            <p className="text-xs">
                Counter mesin ({formatInt(report.actual_shot_count)}) lebih kecil dari sistem (
                {formatInt(report.system_shot_count)}), selisih {formatSigned(report.shot_variance)} pcs. Kemungkinan
                input Excel kelebihan atau counter salah baca. Data tidak diubah otomatis.
            </p>
            {isAdmin && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(`/damage-reports/${report.id}/review`, { preserveScroll: true });
                    }}
                    className="mt-3 flex flex-wrap items-end gap-2"
                >
                    <Field label="Catatan tinjauan (opsional)" className="min-w-64 flex-1">
                        <Input
                            value={data.review_note}
                            onChange={(event) => setData('review_note', event.target.value)}
                            placeholder="mis. Excel tgl 20 dobel, sudah dibatalkan"
                            className="bg-white"
                        />
                    </Field>
                    <Button type="submit" loading={processing}>
                        <SearchCheck className="size-4" /> Tandai Sudah Ditinjau
                    </Button>
                </form>
            )}
        </Panel>
    );
}

const PANEL_TONES = {
    amber: 'border-amber-300 bg-amber-50 text-amber-900',
    red: 'border-red-300 bg-red-50 text-red-900',
    slate: 'border-slate-200 bg-slate-50 text-slate-700',
};

function Panel({ tone, icon: Icon, children }) {
    return (
        <div
            className={cn(
                'mx-auto mb-5 flex max-w-3xl gap-3 rounded-xl border px-4 py-3 text-sm print:hidden',
                PANEL_TONES[tone],
            )}
        >
            <Icon className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}
