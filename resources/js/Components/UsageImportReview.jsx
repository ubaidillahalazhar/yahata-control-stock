import { useForm } from '@inertiajs/react';
import { CircleCheck, Gauge, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Button, Card, CardHeader, Stat, cn } from '@/Components/ui';
import { formatInt } from '@/lib/format';
import { LIFE_TONES, lifeSummary } from '@/lib/life';

const heldCount = (row) => Object.keys(row.held_tools ?? {}).length;

/** Baris End ini punya tool yang ditahan (duplikat J/O + Lot, atau sudah tercakup laporan Ganti Baru). */
const isHeld = (row) => row.duplicate !== null || heldCount(row) > 0;

/**
 * Review hasil upload Machine Result sebelum diterapkan.
 * All-or-nothing: bila ada baris End salah (untuk mesin di master), tombol Terapkan nonaktif.
 * Baris yang ditahan tidak dimasukkan kecuali dicentang "Tetap masukkan".
 */
export default function UsageImportReview({ preview, onClose }) {
    const [included, setIncluded] = useState(() => new Set());
    const [onlyProblems, setOnlyProblems] = useState(preview.summary.error_rows > 0);
    const form = useForm({ token: preview.token, include_rows: [] });
    const hasErrors = preview.summary.error_rows > 0;

    const toggle = (rowNumber) =>
        setIncluded((current) => {
            const next = new Set(current);
            next.has(rowNumber) ? next.delete(rowNumber) : next.add(rowNumber);
            return next;
        });

    /** Tool yang benar-benar bertambah dari satu baris End. */
    const addedTools = (row) => {
        if (row.problems.length > 0) return [];
        if (included.has(row.row)) return row.tool_ids;
        if (row.duplicate !== null) return [];
        return row.tool_ids.filter((toolId) => !(String(toolId) in (row.held_tools ?? {})));
    };

    const entries = preview.rows.reduce((sum, row) => sum + addedTools(row).length, 0);
    const rowsUsed = preview.rows.filter((row) => addedTools(row).length > 0);
    const totalQty = rowsUsed.reduce((sum, row) => sum + row.qty, 0);

    // life_used_after dari server = tanpa baris ditahan; tambahkan yang dicentang "Tetap masukkan".
    const toolImpact = preview.tools.map((tool) => {
        const extra = preview.rows
            .filter((row) => included.has(row.row) && row.problems.length === 0 && row.tool_ids.includes(tool.tool_id))
            .filter((row) => row.duplicate !== null || String(tool.tool_id) in (row.held_tools ?? {}))
            .filter((row) => !tool.last_reset_date || row.usage_date >= tool.last_reset_date)
            .reduce((sum, row) => sum + row.qty, 0);

        return {
            ...tool,
            before: lifeSummary(tool.life_time, tool.life_used_before),
            after: lifeSummary(tool.life_time, tool.life_used_after + extra),
        };
    });

    const overLimit = toolImpact.filter((tool) => tool.after.percent > 100).length;
    const rows = onlyProblems ? preview.rows.filter((row) => row.problems.length > 0 || isHeld(row)) : preview.rows;
    const applyErrors = Object.values(form.errors);

    const apply = () => {
        form.transform((data) => ({ ...data, include_rows: [...included] }));
        form.post('/sync/usage-apply', { preserveScroll: true, preserveState: true, onSuccess: onClose });
    };

    return (
        <Card className="mt-5">
            <CardHeader
                icon={Gauge}
                title={`Review Import Machine Result · ${preview.period_label}`}
                subtitle={`File: ${preview.file_name} · belum ada data yang dimasukkan`}
                actions={
                    <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input
                            type="checkbox"
                            checked={onlyProblems}
                            onChange={(event) => setOnlyProblems(event.target.checked)}
                            className="size-4"
                        />
                        Hanya baris bermasalah / ditahan
                    </label>
                }
            />

            <div className="grid grid-cols-2 gap-2 border-b border-slate-200 p-4 sm:grid-cols-3 lg:grid-cols-6">
                <Stat label="Baris End (master)" value={formatInt(preview.summary.end_rows)} />
                <Stat
                    label="Bermasalah"
                    value={formatInt(preview.summary.error_rows)}
                    valueClassName={hasErrors ? 'text-red-600' : undefined}
                />
                <Stat
                    label="Ditahan"
                    value={formatInt(preview.summary.held_rows)}
                    valueClassName={preview.summary.held_rows ? 'text-amber-700' : undefined}
                />
                <Stat label="Tool terdampak" value={formatInt(preview.summary.tools)} />
                <Stat label="Akan dimasukkan" value={`${formatInt(entries)} baris`} />
                <Stat label="Total pcs (per tool)" value={formatInt(totalQty)} />
            </div>

            {hasErrors && (
                <Notice tone="red">
                    {formatInt(preview.summary.error_rows)} baris End bermasalah — TIDAK ADA yang dimasukkan. Perbaiki baris
                    merah di file Machine Result, lalu upload ulang.
                </Notice>
            )}
            {preview.previous && (
                <Notice tone="blue">
                    Periode {preview.period_label} sudah pernah diimport ({formatInt(preview.previous.rows)} baris,{' '}
                    {preview.previous.batches.join(', ')}). Saat diterapkan, import lama itu <b>dibatalkan</b> dan
                    diganti file ini. Pastikan file ini berisi semua mesin untuk periode tersebut.
                </Notice>
            )}
            {preview.other_inputs && (
                <Notice tone="amber">
                    Ada {formatInt(preview.other_inputs.rows)} input pemakaian lain (manual / template lama, total{' '}
                    {formatInt(preview.other_inputs.total_qty)} pcs) di periode ini untuk {formatInt(preview.other_inputs.tools)}{' '}
                    tool yang sama. Input itu <b>tidak ikut diganti</b> — cek di Riwayat Pemakaian agar tidak terhitung
                    dobel.
                </Notice>
            )}
            {!hasErrors && preview.summary.held_rows > 0 && (
                <Notice tone="amber">
                    Baris kuning ditahan: tanggalnya sudah tercakup count di laporan kerusakan Ganti Baru, atau J/O No. +
                    Lot No. muncul dua kali di file. Tidak dimasukkan, kecuali Anda centang "Tetap masukkan".
                </Notice>
            )}
            {(preview.summary.outside_period_rows > 0 || preview.summary.zero_rows > 0) && (
                <p className="border-b border-slate-200 px-4 py-2 text-xs text-slate-500">
                    Diabaikan: {formatInt(preview.summary.outside_period_rows)} baris End di luar {preview.period_label}
                    {preview.summary.zero_rows > 0 && ` · ${formatInt(preview.summary.zero_rows)} baris End 0 pcs`}.
                </p>
            )}

            <div className="max-h-[480px] overflow-auto">
                <table className="w-full min-w-[960px] text-sm">
                    <thead className="sticky top-0 bg-slate-800 text-[11px] font-bold tracking-wide text-white uppercase">
                        <tr>
                            <th className="px-3 py-2 text-left">Baris</th>
                            <th className="px-3 py-2 text-left">Mesin / Part No.</th>
                            <th className="px-3 py-2 text-left">Input Date</th>
                            <th className="px-3 py-2 text-right">Result (pcs)</th>
                            <th className="px-3 py-2 text-left">Operator / J/O · Lot</th>
                            <th className="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-3 py-8 text-center text-slate-500">
                                    Tidak ada baris bermasalah atau ditahan.
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => {
                            const cellError = (field) =>
                                row.problems.some((item) => item.field === field) && 'bg-red-50 text-red-700';
                            const held = heldCount(row);

                            return (
                                <tr
                                    key={row.row}
                                    className={cn(
                                        row.problems.length > 0 && 'bg-red-50/40',
                                        row.problems.length === 0 && isHeld(row) && 'bg-amber-50/60',
                                    )}
                                >
                                    <td className="px-3 py-2 text-slate-500 tabular-nums">{row.row}</td>
                                    <td className="px-3 py-2">
                                        <p className="font-bold">{row.machine_name}</p>
                                        <p className="font-mono text-xs text-slate-500">
                                            {row.part_no} · {formatInt(row.tools_count)} tool
                                        </p>
                                    </td>
                                    <td className={cn('px-3 py-2 whitespace-nowrap', cellError('input_date'))}>
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
                                        <p className="text-xs text-slate-500">
                                            {[row.jo_no, row.lot_no, row.shift].filter(Boolean).join(' · ') || '—'}
                                        </p>
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
                                        ) : isHeld(row) ? (
                                            <div className="space-y-1">
                                                <p className="font-semibold text-amber-800">
                                                    {row.duplicate
                                                        ? `Kemungkinan duplikat — ${row.duplicate}`
                                                        : `${held} dari ${row.tools_count} tool ditahan — sudah tercakup count laporan Ganti Baru (${[...new Set(Object.values(row.held_tools))].join(', ')})`}
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

            {preview.skipped.length > 0 && (
                <details className="border-t border-slate-200 p-4">
                    <summary className="cursor-pointer text-xs font-bold tracking-wide text-slate-500 uppercase">
                        Dilewati — tidak ada di master ({formatInt(preview.summary.skipped_rows)} baris End)
                    </summary>
                    <div className="mt-2 overflow-auto rounded-md border border-slate-200">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="bg-slate-50 text-[11px] font-bold text-slate-600 uppercase">
                                <tr>
                                    <th className="px-3 py-2 text-left">Machine Name</th>
                                    <th className="px-3 py-2 text-left">Part No.</th>
                                    <th className="px-3 py-2 text-left">Alasan</th>
                                    <th className="px-3 py-2 text-right">Baris End</th>
                                    <th className="px-3 py-2 text-right">Total pcs</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {preview.skipped.map((item) => (
                                    <tr key={`${item.machine_name}|${item.part_no}`}>
                                        <td className="px-3 py-2 font-semibold">{item.machine_name}</td>
                                        <td className="px-3 py-2 font-mono text-xs">{item.part_no}</td>
                                        <td className="px-3 py-2 text-xs text-slate-600">{item.reason}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{formatInt(item.end_rows)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{formatInt(item.qty)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </details>
            )}

            {toolImpact.length > 0 && (
                <div className="border-t border-slate-200 p-4">
                    <p className="mb-2 text-xs font-bold tracking-wide text-slate-500 uppercase">
                        Dampak ke Life Time ({formatInt(toolImpact.length)} tool)
                    </p>
                    {overLimit > 0 && (
                        <p className="mb-2 flex items-center gap-2 rounded-md bg-red-50 px-3 py-2 text-xs font-semibold text-red-800">
                            <TriangleAlert className="size-4 shrink-0" />
                            {overLimit} tool akan melewati 100% life time. Biasanya ada Ganti Tool yang belum diinput —
                            input dulu di aplikasi (detail tool → Ganti Tool), lalu upload ulang file periode ini.
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
                                            <p className="text-xs text-slate-500">
                                                {tool.tools_name} · {tool.machine_name}
                                                {tool.last_reset_date &&
                                                    ` · Ganti Tool terakhir ${tool.last_reset_date.split('-').reverse().join('/')}`}
                                            </p>
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
                <Button variant="ghost" onClick={onClose}>
                    Batal
                </Button>
                <Button
                    variant="success"
                    onClick={apply}
                    loading={form.processing}
                    disabled={hasErrors || !preview.token || entries === 0}
                >
                    <Gauge className="size-4" /> {preview.previous ? 'Ganti & Terapkan' : 'Terapkan'}{' '}
                    {formatInt(entries)} Baris Pemakaian
                </Button>
            </div>
        </Card>
    );
}

const NOTICE_TONES = {
    red: 'border-red-200 bg-red-50 text-red-800 font-semibold text-sm',
    amber: 'border-amber-200 bg-amber-50 text-amber-900 text-xs',
    blue: 'border-blue-200 bg-blue-50 text-blue-900 text-xs',
};

function Notice({ tone, children }) {
    return (
        <p className={cn('flex items-center gap-2 border-b px-4 py-2', NOTICE_TONES[tone])}>
            <TriangleAlert className="size-4 shrink-0" />
            <span>{children}</span>
        </p>
    );
}
