import { Link } from '@inertiajs/react';
import { Ban, FileSpreadsheet, Gauge, PencilLine, RotateCcw, SlidersHorizontal } from 'lucide-react';
import { Button, cn } from '@/Components/ui';
import { formatInt } from '@/lib/format';
import { buildUrl } from '@/lib/urls';

/**
 * Riwayat input pemakaian life time. Baris yang dibatalkan / dikoreksi tetap tampil (dicoret).
 * Tombol Koreksi / Batalkan hanya muncul bila `log.can_change` (PIC: input sendiri ≤ 24 jam).
 */
export default function UsageLogTable({ logs, showTool = false, onCancel, onCorrect }) {
    const hasActions = Boolean(onCancel || onCorrect);

    return (
        <table className="w-full text-sm">
            <thead className="sticky top-0 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                <tr>
                    <th className="px-4 py-2 text-left">Tanggal</th>
                    {showTool && <th className="px-4 py-2 text-left">Tool</th>}
                    <th className="px-4 py-2 text-left">Jenis</th>
                    <th className="px-4 py-2 text-right">Qty</th>
                    <th className="px-4 py-2 text-right">Life terpakai</th>
                    <th className="px-4 py-2 text-left">PIC / Catatan</th>
                    {hasActions && <th className="px-4 py-2 text-right">Aksi</th>}
                </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
                {logs.map((log) => {
                    const isReset = log.type === 'RESET';
                    const isAdjust = log.type === 'ADJUST';

                    return (
                        <tr key={log.id} className={cn(log.is_cancelled && 'bg-slate-50 text-slate-400')}>
                            <td className="px-4 py-2 whitespace-nowrap">
                                <p className={cn('font-semibold', log.is_cancelled && 'line-through')}>
                                    {log.usage_date_display}
                                </p>
                                <p className="text-[11px] text-slate-400">input {log.created_at_display}</p>
                            </td>
                            {showTool && (
                                <td className="px-4 py-2">
                                    {log.tool ? (
                                        <Link
                                            href={`/tools/${log.tool.id}`}
                                            className="font-mono text-[13px] font-bold text-slate-900 hover:text-blue-700"
                                        >
                                            {log.tool.tools_name_yes}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                    {log.tool && (
                                        <p className="text-xs text-slate-500">
                                            {log.tool.tools_name} · Life {formatInt(log.tool.life_time)}
                                        </p>
                                    )}
                                </td>
                            )}
                            <td className="px-4 py-2">
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-bold whitespace-nowrap ring-1 ring-inset',
                                        isReset
                                            ? 'bg-slate-800 text-white ring-slate-900'
                                            : isAdjust
                                              ? 'bg-violet-50 text-violet-700 ring-violet-600/30'
                                              : 'bg-blue-50 text-blue-700 ring-blue-600/30',
                                    )}
                                    title={isAdjust ? 'Selisih counter mesin untuk tool yang sudah diganti — masuk Actual, tidak menambah life tool sekarang' : undefined}
                                >
                                    {isReset ? (
                                        <RotateCcw className="size-3" />
                                    ) : isAdjust ? (
                                        <SlidersHorizontal className="size-3" />
                                    ) : (
                                        <Gauge className="size-3" />
                                    )}
                                    {isReset ? 'GANTI TOOL' : isAdjust ? 'KOREKSI COUNT' : 'PAKAI'}
                                </span>
                                {log.source && log.source !== 'manual' && log.source !== 'excel' && (
                                    <p className="mt-1 text-[11px] text-slate-500">{log.source_label}</p>
                                )}
                                {log.corrects && (
                                    <p
                                        className="mt-1 inline-flex items-center gap-1 rounded bg-orange-50 px-1.5 py-0.5 text-[11px] font-bold text-orange-800 ring-1 ring-orange-600/30 ring-inset"
                                        title="Input pengganti hasil koreksi"
                                    >
                                        <PencilLine className="size-3" /> KOREKSI dari {log.corrects.usage_date_display} ·{' '}
                                        {formatInt(log.corrects.qty)} pcs
                                    </p>
                                )}
                                {log.is_cancelled && (
                                    <p
                                        className="mt-1 text-[11px] font-bold text-red-600"
                                        title={log.cancel_reason ?? undefined}
                                    >
                                        {log.is_corrected ? 'DIKOREKSI' : 'DIBATALKAN'} · {log.cancelled_by}
                                    </p>
                                )}
                            </td>
                            <td
                                className={cn(
                                    'px-4 py-2 text-right font-bold whitespace-nowrap tabular-nums',
                                    log.is_cancelled && 'line-through',
                                )}
                            >
                                {isReset ? `−${formatInt(log.qty)}` : `+${formatInt(log.qty)}`}
                            </td>
                            <td className="px-4 py-2 text-right whitespace-nowrap text-slate-600 tabular-nums">
                                {formatInt(log.life_used_before)} → <b>{formatInt(log.life_used_after)}</b>
                            </td>
                            <td className="px-4 py-2">
                                <p className="font-semibold">{log.pic_name}</p>
                                {log.notes && <p className="text-xs text-slate-500">{log.notes}</p>}
                                {log.batch_no && (
                                    <Link
                                        href={buildUrl('/tool-usages', { batch: log.batch_no })}
                                        className="mt-0.5 inline-flex items-center gap-1 rounded bg-sky-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-sky-800 ring-1 ring-sky-600/20 ring-inset hover:bg-sky-100"
                                        title="Lihat semua input dari import Excel ini"
                                    >
                                        <FileSpreadsheet className="size-3" /> Excel {log.batch_no}
                                    </Link>
                                )}
                                {log.is_cancelled && log.cancel_reason && !log.is_corrected && (
                                    <p className="text-xs text-red-500">Alasan batal: {log.cancel_reason}</p>
                                )}
                                {log.is_corrected && (
                                    <p className="text-xs text-orange-700">{log.cancel_reason}</p>
                                )}
                            </td>
                            {hasActions && (
                                <td className="px-4 py-2 text-right">
                                    {log.can_change && (
                                        <div className="flex flex-col items-end gap-1">
                                            <div className="flex justify-end gap-1.5">
                                                {onCorrect && log.correctable && (
                                                    <Button
                                                        variant="outline"
                                                        size="xs"
                                                        onClick={() => onCorrect(log)}
                                                        title="Ubah jumlah / tanggal input ini"
                                                    >
                                                        <PencilLine className="size-3.5" /> Koreksi
                                                    </Button>
                                                )}
                                                {onCancel && (
                                                    <Button
                                                        variant="outline-danger"
                                                        size="xs"
                                                        onClick={() => onCancel(log)}
                                                        title="Batalkan input ini"
                                                    >
                                                        <Ban className="size-3.5" /> Batalkan
                                                    </Button>
                                                )}
                                            </div>
                                            {log.change_until_display && (
                                                <span className="text-[11px] whitespace-nowrap text-slate-400">
                                                    s.d. {log.change_until_display}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </td>
                            )}
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}
