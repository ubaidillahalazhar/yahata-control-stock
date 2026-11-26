import { ArrowDown, ArrowUp, Check, TriangleAlert } from 'lucide-react';
import { cn } from '@/Components/ui';
import { formatInt, formatSigned } from '@/lib/format';
import { CONFIRM_VARIANCE_PERCENT } from '@/lib/shotCount';

const TONES = {
    ok: { icon: Check, className: 'border-emerald-300 bg-emerald-50 text-emerald-900' },
    up: { icon: ArrowUp, className: 'border-blue-300 bg-blue-50 text-blue-900' },
    down: { icon: ArrowDown, className: 'border-amber-300 bg-amber-50 text-amber-900' },
};

/**
 * Angka sistem sebagai pembanding (tidak diisi otomatis ke field) + selisih dengan counter mesin.
 * `mode`: 'close' = laporan ganti baru (selisih positif → koreksi Actual), 'running' = tool masih jalan.
 */
export default function ShotCountCompare({ system, comparison, mode = 'close' }) {
    const tone = comparison ? TONES[comparison.tone] : null;
    const Icon = tone?.icon;

    return (
        <div className="space-y-2 text-sm">
            <p className="rounded-lg bg-slate-100 px-3 py-2 text-slate-700">
                Sistem mencatat <b className="tabular-nums">{formatInt(system)}</b> pcs sejak ganti tool terakhir.
                <span className="block text-xs text-slate-500">
                    Isi sesuai angka di counter mesin, bukan angka sistem.
                </span>
            </p>

            {comparison && (
                <div className={cn('rounded-lg border px-3 py-2', tone.className)}>
                    <p className="flex items-center gap-1.5 font-semibold">
                        <Icon className="size-4 shrink-0" aria-hidden="true" />
                        {comparison.variance === 0
                            ? 'Sama dengan sistem.'
                            : `Selisih ${formatSigned(comparison.variance)} pcs dari sistem.`}
                    </p>
                    <p className="mt-0.5 text-xs">
                        {comparison.tone === 'up' &&
                            (mode === 'close'
                                ? 'Selisih dicatat sebagai koreksi Actual bulan ini (tidak menambah life tool baru).'
                                : 'Selisih ditambahkan ke life tool ini.')}
                        {comparison.tone === 'down' &&
                            'Counter lebih kecil dari sistem: data tidak dikurangi, admin akan meninjau.'}
                    </p>
                    {comparison.confirm && (
                        <p className="mt-1 flex items-center gap-1 text-xs font-bold">
                            <TriangleAlert className="size-3.5" /> Selisih &gt; {CONFIRM_VARIANCE_PERCENT}% — Anda akan
                            diminta konfirmasi.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
