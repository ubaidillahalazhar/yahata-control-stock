import { Link } from '@inertiajs/react';
import { Ban, Gauge, RotateCcw } from 'lucide-react';
import { Button, cn } from '@/Components/ui';
import { formatInt } from '@/lib/format';

/**
 * Riwayat input pemakaian life time. Baris yang dibatalkan tetap tampil (dicoret).
 */
export default function UsageLogTable({ logs, showTool = false, onCancel }) {
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
                    {onCancel && <th className="px-4 py-2 text-right">Aksi</th>}
                </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
                {logs.map((log) => {
                    const isReset = log.type === 'RESET';

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
                                            : 'bg-blue-50 text-blue-700 ring-blue-600/30',
                                    )}
                                >
                                    {isReset ? <RotateCcw className="size-3" /> : <Gauge className="size-3" />}
                                    {isReset ? 'GANTI TOOL' : 'PAKAI'}
                                </span>
                                {log.is_cancelled && (
                                    <p
                                        className="mt-1 text-[11px] font-bold text-red-600"
                                        title={log.cancel_reason ?? undefined}
                                    >
                                        DIBATALKAN · {log.cancelled_by}
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
                                {log.is_cancelled && log.cancel_reason && (
                                    <p className="text-xs text-red-500">Alasan batal: {log.cancel_reason}</p>
                                )}
                            </td>
                            {onCancel && (
                                <td className="px-4 py-2 text-right">
                                    {!log.is_cancelled && (
                                        <Button
                                            variant="outline-danger"
                                            size="xs"
                                            onClick={() => onCancel(log)}
                                            title="Batalkan input ini"
                                        >
                                            <Ban className="size-3.5" /> Batalkan
                                        </Button>
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
