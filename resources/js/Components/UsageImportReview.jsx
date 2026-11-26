import { useForm } from '@inertiajs/react';
import { CircleCheck, Gauge, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Button, Card, CardHeader, Stat, cn } from '@/Components/ui';
import { formatInt } from '@/lib/format';
import { LIFE_TONES, lifeSummary } from '@/lib/life';

/**
 * Review hasil upload Excel Pemakaian Life Time (Day) sebelum diterapkan.
 * All-or-nothing: bila ada baris salah, tombol Terapkan nonaktif.
 * Baris "kemungkinan duplikat" tidak dimasukkan kecuali dicentang.
 */
export default function UsageImportReview({ preview, onClose }) {
    const [included, setIncluded] = useState(() => new Set());
    const [onlyProblems, setOnlyProblems] = useState(preview.summary.error_rows > 0);
    const form = useForm({ token: preview.token, include_duplicates: [], confirm_unusual: false });
    const hasErrors = preview.summary.error_rows > 0;

    const isIncluded = (row) => row.problems.length === 0 && (row.duplicate === null || included.has(row.row));

    const toggle = (rowNumber) =>
        setIncluded((current) => {
            const next = new Set(current);
            next.has(rowNumber) ? next.delete(rowNumber) : next.add(rowNumber);
            return next;
        });

    const selectedRows = preview.rows.filter(isIncluded);
    const totalQty = selectedRows.reduce((sum, row) => sum + row.qty, 0);
    const unusualSelected = selectedRows.filter((row) => row.unusual).length;

    const toolImpact = preview.tools.map((tool) => {
        const added = preview.rows
            .filter((row) => row.tool_id === tool.tool_id && isIncluded(row) && row.counts_toward_life)
            .reduce((sum, row) => sum + row.qty, 0);

        return {
            ...tool,
            before: lifeSummary(tool.life_time, tool.life_used_before),
            after: lifeSummary(tool.life_time, tool.life_used_before + added),
        };
    });

    const overLimit = toolImpact.filter((tool) => tool.after.percent > 100).length;
    const rows = onlyProblems
        ? preview.rows.filter((row) => row.problems.length > 0 || row.duplicate || row.unusual)
        : preview.rows;
    const applyErrors = Object.values(form.errors);

    const apply = () => {
        form.transform((data) => ({ ...data, include_duplicates: [...included] }));
        form.post('/sync/usage-apply', { preserveScroll: true, preserveState: true, onSuccess: onClose });
    };

    return (
        <Card className="mt-5">
            <CardHeader
                icon={Gauge}
                title="Review Import Pemakaian Life Time (Day)"
                subtitle={`File: ${preview.file_name} · belum ada data yang dimasukkan`}
                actions={
                    <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input
                            type="checkbox"
                            checked={onlyProblems}
                            onChange={(event) => setOnlyProblems(event.target.checked)}
                            className="size-4"
                        />
                        Hanya baris bermasalah / duplikat / janggal
                    </label>
                }
            />

            <div className="grid grid-cols-2 gap-2 border-b border-slate-200 p-4 sm:grid-cols-3 lg:grid-cols-6">
                <Stat label="Baris terisi" value={formatInt(preview.summary.total_rows)} />
                <Stat label="Valid" value={formatInt(preview.summary.valid_rows)} valueClassName="text-emerald-700" />
                <Stat
                    label="Bermasalah"
                    value={formatInt(preview.summary.error_rows)}
                    valueClassName={hasErrors ? 'text-red-600' : undefined}
                />
                <Stat
                    label="Kemungkinan duplikat"
                    value={formatInt(preview.summary.duplicate_rows)}
                    valueClassName={preview.summary.duplicate_rows ? 'text-amber-700' : undefined}
                />
                <Stat label="Akan dimasukkan" value={`${formatInt(selectedRows.length)} baris`} />
                <Stat label="Total pcs" value={formatInt(totalQty)} />
            </div>

            {hasErrors && (
                <p className="flex items-center gap-2 border-b border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-800">
                    <TriangleAlert className="size-4 shrink-0" />
                    {formatInt(preview.summary.error_rows)} baris bermasalah — TIDAK ADA yang dimasukkan. Perbaiki baris
                    merah di Excel, lalu upload ulang file yang sama.
                </p>
            )}
            {!hasErrors && preview.summary.duplicate_rows > 0 && (
                <p className="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-900">
                    <TriangleAlert className="size-4 shrink-0" />
                    Baris kuning sama persis (Tools Name YES + tanggal + jumlah + PIC) dengan input yang sudah ada / baris
                    lain di file, atau tanggalnya sudah tercakup count mesin di laporan kerusakan Ganti Baru. Tidak
                    dimasukkan, kecuali Anda centang "Tetap masukkan".
                </p>
            )}

            {!hasErrors && (preview.summary.unusual_rows ?? 0) > 0 && (
                <p className="flex items-center gap-2 border-b border-orange-200 bg-orange-50 px-4 py-2 text-xs text-orange-900">
                    <TriangleAlert className="size-4 shrink-0" />
                    {formatInt(preview.summary.unusual_rows)} baris berjumlah janggal (lebih dari 10% life time tool
                    atau lebih dari 3× rata-rata hariannya) — kemungkinan salah ketik. Periksa baris oranye sebelum
                    menerapkan.
                </p>
            )}

            <div className="max-h-[480px] overflow-auto">
                <table className="w-full min-w-[900px] text-sm">
                    <thead className="sticky top-0 bg-slate-800 text-[11px] font-bold tracking-wide text-white uppercase">
                        <tr>
                            <th className="px-3 py-2 text-left">Baris</th>
                            <th className="px-3 py-2 text-left">Tools Name YES</th>
                            <th className="px-3 py-2 text-left">Tanggal</th>
                            <th className="px-3 py-2 text-right">Jumlah (pcs)</th>
                            <th className="px-3 py-2 text-left">PIC / Catatan</th>
                            <th className="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-3 py-8 text-center text-slate-500">
                                    Tidak ada baris bermasalah atau duplikat.
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => {
                            const problem = (field) => row.problems.find((item) => item.field === field);
                            const cellError = (field) => problem(field) && 'bg-red-50 text-red-700';

                            return (
                                <tr
                                    key={row.row}
                                    className={cn(
                                        row.problems.length > 0 && 'bg-red-50/40',
                                        row.problems.length === 0 && row.duplicate && 'bg-amber-50/60',
                                        row.problems.length === 0 && !row.duplicate && row.unusual && 'bg-orange-50/60',
                                    )}
                                >
                                    <td className="px-3 py-2 text-slate-500 tabular-nums">{row.row}</td>
                                    <td className={cn('px-3 py-2', cellError('tools_name_yes'))}>
                                        <p className="font-mono text-[13px] font-bold">
                                            {row.tools_name_yes ?? row.input_name ?? '—'}
                                        </p>
                                        {row.tool_id && (
                                            <p className="text-xs text-slate-500">
                                                {row.tools_name} · {row.machine_name ?? '—'}
                                            </p>
                                        )}
                                    </td>
                                    <td className={cn('px-3 py-2 whitespace-nowrap', cellError('usage_date'))}>
                                        {row.usage_date_display ?? '—'}
                                    </td>
                                    <td
                                        className={cn(
                                            'px-3 py-2 text-right font-bold whitespace-nowrap tabular-nums',
                                            cellError('qty'),
                                        )}
                                    >
                                        {row.qty !== null ? formatInt(row.qty) : (row.qty_display ?? '—')}
                                    </td>
                                    <td className={cn('px-3 py-2', cellError('pic_name'))}>
                                        <p className="font-semibold">{row.pic_name ?? '—'}</p>
                                        {row.notes && <p className="text-xs text-slate-500">{row.notes}</p>}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {row.problems.length > 0 ? (
                                            <ul className="space-y-0.5 font-semibold text-red-700">
                                                {row.problems.map((item) => (
                                                    <li key={item.field}>
                                                        {item.label}: {item.reason}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : row.duplicate ? (
                                            <div className="space-y-1">
                                                <p className="font-semibold text-amber-800">
                                                    {row.duplicate.kind === 'counter'
                                                        ? 'Kemungkinan terhitung dobel'
                                                        : 'Kemungkinan duplikat'}{' '}
                                                    — {row.duplicate.message}
                                                </p>
                                                <label className="flex items-center gap-1.5 font-semibold text-slate-700">
                                                    <input
                                                        type="checkbox"
                                                        checked={included.has(row.row)}
                                                        onChange={() => toggle(row.row)}
                                                        disabled={hasErrors}
                                                        className="size-3.5"
                                                    />
                                                    Tetap masukkan
                                                </label>
                                            </div>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 font-semibold text-emerald-700">
                                                <CircleCheck className="size-3.5" /> OK
                                            </span>
                                        )}
                                        {row.problems.length === 0 && row.unusual && (
                                            <p className="mt-1 font-semibold text-orange-800">
                                                Angka janggal: {row.unusual}
                                            </p>
                                        )}
                                        {row.problems.length === 0 && !row.counts_toward_life && (
                                            <p className="mt-1 text-slate-500">
                                                Sebelum Ganti Tool terakhir — tidak menambah life tool sekarang (tetap
                                                masuk Actual).
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {toolImpact.length > 0 && (
                <div className="border-t border-slate-200 p-4">
                    <p className="mb-2 text-xs font-bold tracking-wide text-slate-500 uppercase">
                        Dampak ke Life Time ({formatInt(toolImpact.length)} tool)
                    </p>
                    {overLimit > 0 && (
                        <p className="mb-2 flex items-center gap-2 rounded-md bg-red-50 px-3 py-2 text-xs font-semibold text-red-800">
                            <TriangleAlert className="size-4 shrink-0" />
                            {overLimit} tool akan melewati 100% life time. Biasanya ada Ganti Tool yang belum diinput —
                            input dulu di aplikasi (detail tool → Ganti Tool) sebelum menerapkan.
                        </p>
                    )}
                    <div className="max-h-64 overflow-auto rounded-md border border-slate-200">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="sticky top-0 bg-slate-50 text-[11px] font-bold text-slate-600 uppercase">
                                <tr>
                                    <th className="px-3 py-2 text-left">Tools Name YES</th>
                                    <th className="px-3 py-2 text-right">Life Time</th>
                                    <th className="px-3 py-2 text-right">Sekarang</th>
                                    <th className="px-3 py-2 text-right">Setelah import</th>
                                    <th className="px-3 py-2 text-left">Status Life</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {toolImpact.map((tool) => (
                                    <tr key={tool.tool_id}>
                                        <td className="px-3 py-2">
                                            <p className="font-mono text-[13px] font-bold">{tool.tools_name_yes}</p>
                                            {tool.last_reset_date && (
                                                <p className="text-xs text-slate-500">
                                                    Ganti Tool terakhir {tool.last_reset_date.split('-').reverse().join('/')}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">{formatInt(tool.life_time)}</td>
                                        <td className={cn('px-3 py-2 text-right tabular-nums', LIFE_TONES[tool.before.level].text)}>
                                            {tool.before.percent}%
                                        </td>
                                        <td
                                            className={cn(
                                                'px-3 py-2 text-right font-bold tabular-nums',
                                                LIFE_TONES[tool.after.level].text,
                                            )}
                                        >
                                            {tool.after.percent}%
                                            <span className="block text-[11px] font-normal text-slate-500">
                                                {formatInt(tool.after.used)} pcs
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-xs font-bold">
                                            {tool.before.status !== tool.after.status ? (
                                                <span className="text-red-700">
                                                    {tool.before.status} → {tool.after.status}
                                                </span>
                                            ) : (
                                                <span className={LIFE_TONES[tool.after.level].text}>{tool.after.status}</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 p-4">
                {applyErrors.length > 0 && (
                    <ul className="mr-auto text-xs font-semibold text-red-600">
                        {applyErrors.slice(0, 3).map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                )}
                {!hasErrors && unusualSelected > 0 && (
                    <label className="flex items-center gap-2 rounded-md bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-900 ring-1 ring-orange-300">
                        <input
                            type="checkbox"
                            checked={form.data.confirm_unusual}
                            onChange={(event) => form.setData('confirm_unusual', event.target.checked)}
                            className="size-4"
                        />
                        Saya sudah cek {formatInt(unusualSelected)} baris berangka janggal
                    </label>
                )}
                <Button variant="ghost" onClick={onClose}>
                    Batal
                </Button>
                <Button
                    variant="success"
                    onClick={apply}
                    loading={form.processing}
                    disabled={
                        hasErrors ||
                        !preview.token ||
                        selectedRows.length === 0 ||
                        (unusualSelected > 0 && !form.data.confirm_unusual)
                    }
                >
                    <Gauge className="size-4" /> Terapkan {formatInt(selectedRows.length)} Baris Pemakaian
                </Button>
            </div>
        </Card>
    );
}
