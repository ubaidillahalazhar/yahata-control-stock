import { Link, router } from '@inertiajs/react';
import { CircleCheck, FileSpreadsheet, FileWarning, Search, X } from 'lucide-react';
import { useDeferredValue, useMemo, useState } from 'react';
import { Button, Card, CardHeader, EmptyState, Input, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatInt } from '@/lib/format';
import { cleanParams } from '@/lib/urls';

const STATUS_TONES = {
    NEW_HELD: 'bg-red-50 text-red-700 ring-red-600/30',
    UPDATE_HELD: 'bg-amber-50 text-amber-800 ring-amber-500/40',
    DUPLICATE: 'bg-slate-100 text-slate-600 ring-slate-400/40',
};

const STATUS_CARDS = [
    {
        value: 'NEW_HELD',
        label: 'Tool baru belum masuk',
        hint: 'Tidak tampil di dashboard sampai datanya lengkap',
        accent: 'border-l-red-600',
    },
    {
        value: 'UPDATE_HELD',
        label: 'Update ditahan',
        hint: 'Dashboard tetap memakai data lama yang lengkap',
        accent: 'border-l-amber-400',
    },
    {
        value: 'DUPLICATE',
        label: 'Duplikat diabaikan',
        hint: 'Baris lain dengan nama sama sudah dipakai',
        accent: 'border-l-slate-500',
    },
];

// Urutan kolom sesuai Excel; field ini yang ditandai merah bila bermasalah.
const DATA_COLUMNS = [
    ['machine_name', 'C', 'Machine'],
    ['step', 'D', 'Step'],
    ['tools_name', 'E', 'Tools Name'],
    ['yes_name', 'F', 'Yes Name'],
    ['part_name', 'G', 'Part Name'],
    ['rack_location', 'I', 'Rak'],
    ['part_no', 'J', 'Part No'],
    ['current_stock', 'K', 'Stok'],
    ['life_time', 'L', 'Life Time'],
];

export default function Index({ issues, counts, fieldCounts, filters, referenceNo, importedAt }) {
    const [query, setQuery] = useState('');
    const deferredQuery = useDeferredValue(query);

    const apply = (next) =>
        router.get('/import-issues', cleanParams({ ...filters, ...next }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const visible = useMemo(() => {
        const tokens = deferredQuery.trim().toLowerCase().split(/\s+/).filter(Boolean);

        if (tokens.length === 0) return issues;

        return issues.filter((issue) => {
            const haystack = [issue.tools_name_yes, ...Object.values(issue.row_data ?? {}).flat()]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return tokens.every((token) => haystack.includes(token));
        });
    }, [issues, deferredQuery]);

    const hasFilter = Boolean(filters.status || filters.field);

    return (
        <AppLayout
            title="Excel Belum Lengkap"
            subtitle={
                referenceNo
                    ? `Hasil import terakhir ${referenceNo} · ${importedAt} — perbaiki di Excel lalu upload ulang`
                    : 'Baris Excel master yang kolom wajibnya kosong akan tampil di sini setelah import'
            }
            actions={
                <Button as={Link} href="/sync" size="sm">
                    <FileSpreadsheet className="size-4" /> Upload Ulang Excel
                </Button>
            }
        >
            {counts.total === 0 ? (
                <Card>
                    <EmptyState
                        icon={CircleCheck}
                        title="Semua data Excel lengkap"
                        description="Tidak ada baris yang ditahan pada import terakhir."
                    />
                </Card>
            ) : (
                <>
                    <section className="grid gap-3 sm:grid-cols-3">
                        {STATUS_CARDS.map((card) => {
                            const active = filters.status === card.value;

                            return (
                                <button
                                    key={card.value}
                                    type="button"
                                    onClick={() => apply({ status: active ? '' : card.value })}
                                    aria-pressed={active}
                                    className={cn(
                                        'rounded-lg border border-l-4 border-slate-200 bg-white p-4 text-left shadow-sm transition hover:shadow-md',
                                        card.accent,
                                        active && 'ring-2 ring-blue-600',
                                    )}
                                >
                                    <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">
                                        {card.label}
                                    </p>
                                    <p className="mt-1 text-3xl font-extrabold tabular-nums">
                                        {formatInt(counts[card.value])}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">{card.hint}</p>
                                </button>
                            );
                        })}
                    </section>

                    <Card className="mt-5">
                        <CardHeader
                            icon={FileWarning}
                            title={`${formatInt(counts.total)} baris belum lengkap`}
                            subtitle="Sel merah = kolom wajib yang kosong / tidak valid"
                        />

                        <div className="space-y-3 border-b border-slate-200 p-4">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    type="search"
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Cari Tools Name YES, mesin, part no…"
                                    className="pl-9"
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-bold text-slate-500 uppercase">Kolom kosong:</span>
                                {fieldCounts.map((item) => {
                                    const active = filters.field === item.field;

                                    return (
                                        <button
                                            key={item.field}
                                            type="button"
                                            onClick={() => apply({ field: active ? '' : item.field })}
                                            aria-pressed={active}
                                            className={cn(
                                                'rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset transition',
                                                active
                                                    ? 'bg-red-600 text-white ring-red-700'
                                                    : 'bg-white text-red-700 ring-red-300 hover:bg-red-50',
                                            )}
                                        >
                                            {item.column} {item.label} · {formatInt(item.count)}
                                        </button>
                                    );
                                })}
                                {hasFilter && (
                                    <Button variant="ghost" size="xs" onClick={() => apply({ status: '', field: '' })}>
                                        <X className="size-3.5" /> Reset filter
                                    </Button>
                                )}
                            </div>
                        </div>

                        {visible.length === 0 ? (
                            <EmptyState icon={Search} title="Tidak ada baris yang cocok" />
                        ) : (
                            <div className="max-h-[68vh] overflow-auto">
                                <table className="w-full min-w-[1200px] border-collapse text-sm">
                                    <thead className="sticky top-0 z-10 bg-slate-800 text-[11px] font-bold tracking-wide text-white uppercase">
                                        <tr>
                                            <th className="px-3 py-2.5 text-left">Baris</th>
                                            <th className="px-3 py-2.5 text-left">Tools Name YES (H)</th>
                                            <th className="px-3 py-2.5 text-left">Status</th>
                                            {DATA_COLUMNS.map(([field, column, label]) => (
                                                <th key={field} className="px-3 py-2.5 text-left whitespace-nowrap">
                                                    {label}
                                                    <span className="ml-1 font-mono text-[9px] font-normal text-slate-400 normal-case">
                                                        {column}
                                                    </span>
                                                </th>
                                            ))}
                                            <th className="px-3 py-2.5 text-left">
                                                Forecast
                                                <span className="ml-1 font-mono text-[9px] font-normal text-slate-400 normal-case">
                                                    M–Q
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visible.map((issue) => (
                                            <IssueRow key={issue.id} issue={issue} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </>
            )}
        </AppLayout>
    );
}

function IssueRow({ issue }) {
    const problems = Object.fromEntries(issue.problems.map((problem) => [problem.field, problem]));
    const data = issue.row_data ?? {};
    const cell = (field) => cn('px-3 py-2 align-top whitespace-nowrap', problems[field] && 'bg-red-50');

    return (
        <tr className="border-b border-slate-100 hover:bg-blue-50/50">
            <td className="px-3 py-2 align-top font-mono text-xs font-bold text-slate-500">{issue.excel_row}</td>
            <td className={cell('tools_name_yes')}>
                <Value value={issue.tools_name_yes} problem={problems.tools_name_yes} mono />
                {issue.tool_id && (
                    <Link href={`/tools/${issue.tool_id}`} className="block text-xs text-blue-700 hover:underline">
                        Lihat data lama →
                    </Link>
                )}
            </td>
            <td className="px-3 py-2 align-top">
                <span
                    className={cn(
                        'inline-block rounded px-1.5 py-0.5 text-[11px] font-bold whitespace-nowrap ring-1 ring-inset',
                        STATUS_TONES[issue.status],
                    )}
                >
                    {issue.status_label}
                </span>
            </td>
            {DATA_COLUMNS.map(([field]) => (
                <td key={field} className={cell(field)}>
                    <Value value={data[field]} problem={problems[field]} />
                </td>
            ))}
            <td className={cell('forecasts')}>
                {problems.forecasts ? (
                    <Value problem={problems.forecasts} />
                ) : (
                    <span className="font-mono text-xs text-slate-600">
                        {(data.forecasts ?? []).map((value) => value ?? '·').join(' / ')}
                    </span>
                )}
            </td>
        </tr>
    );
}

function Value({ value, problem, mono = false }) {
    if (problem) {
        return (
            <span className="text-xs font-bold text-red-700">
                {value ? <span className="mr-1 font-normal text-slate-600">{value}</span> : null}
                {problem.reason}
            </span>
        );
    }

    return <span className={cn('text-slate-800', mono && 'font-mono text-[13px] font-bold')}>{value ?? '—'}</span>;
}
