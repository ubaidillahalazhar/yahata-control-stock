import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Plus, Printer } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import logoYahata from '@/assets/logo-yahata.jpeg';
import { formatInt } from '@/lib/format';

const DAMAGE_CODE_LABELS = {
    P: 'Patah / Pecah (Broken)',
    L: 'Longgar / Aus (Worn Out)',
    A: 'Abnormal / Cacat Fungsi',
    C: 'Chipping / Gompal',
    G: 'Gores / Baret (Scratched)',
    S: 'Setup / Trial Mesin',
    B: 'Berkarat / Korosi',
    O: 'Lain-lain (Other)',
};

export default function Show({ report, damageReport }) {
    const data = report ?? damageReport ?? {};
    const tool = data.tool ?? {};

    const docNo = data.report_no || data.reference_no || (data.id ? `DMG-${String(data.id).padStart(6, '0')}` : '—');
    const damageCode = data.damage_code ?? '—';
    const damageDesc =
        data.damage_label ||
        data.damage_description ||
        DAMAGE_CODE_LABELS[damageCode] ||
        'Kerusakan / Penggantian Part';
    const qty = Math.abs(Number(data.qty_damaged ?? data.qty ?? data.qty_change ?? 1));
    const picName = data.pic_name || data.operator_name || data.reported_by || '—';
    const machineInfo = [data.machine_name || data.machine_no, data.shift ? `Shift ${data.shift}` : null]
        .filter(Boolean)
        .join(' · ');

    const hasStockSnapshot = data.stock_before !== undefined && data.stock_after !== undefined;
    const isOrderAlert = Boolean(data.pr_triggered || data.requires_pr || tool.status === 'ORDER');

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('autoprint') === '1') {
            const timer = setTimeout(() => window.print(), 400);
            return () => clearTimeout(timer);
        }
    }, []);

    return (
        <AppLayout
            title="Laporan Kerusakan & Penggantian Part"
            subtitle={`Nomor Dokumen ${docNo}`}
            actions={
                <div className="flex flex-wrap gap-2 print:hidden">
                    <Button as={Link} href="/damage-reports/create" variant="outline">
                        <Plus className="size-4" /> Laporan Baru
                    </Button>
                    <Button onClick={() => window.print()}>
                        <Printer className="size-4" /> Cetak Slip (A4 / PDF)
                    </Button>
                </div>
            }
        >
            {/* Banner Konfirmasi (Disembunyikan saat Print) */}
            <div className="mx-auto mb-5 flex max-w-3xl items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 print:hidden">
                <div className="flex items-center gap-2.5">
                    <CheckCircle2 className="size-5 shrink-0 text-emerald-600" />
                    <span>
                        Laporan kerusakan &amp; penggantian part <b>{docNo}</b> berhasil tercatat dan siap dicetak.
                    </span>
                </div>
                <Link href="/" className="inline-flex items-center gap-1 font-semibold text-emerald-800 hover:underline">
                    <ArrowLeft className="size-4" /> Dashboard
                </Link>
            </div>

            {/* Area Dokumen Siap Cetak */}
            <div className="mx-auto max-w-3xl rounded-xl border border-slate-300 bg-white p-6 shadow-sm sm:p-8 print:max-w-none print:rounded-none print:border-slate-800 print:p-6 print:shadow-none">
                {/* Kop Surat Perusahaan */}
                <div className="flex items-start justify-between border-b-2 border-slate-900 pb-4">
                    <div className="flex items-center gap-3.5">
                        <img
                            src={logoYahata}
                            alt="Logo PT. Yahata Fastener Indonesia"
                            className="size-12 object-contain"
                        />
                        <div>
                            <h2 className="text-base font-extrabold tracking-wide text-slate-900 uppercase">
                                PT. Yahata Fastener Indonesia
                            </h2>
                            <p className="text-xs font-medium text-slate-600">
                                Production Department · Tooling Control Stock System
                            </p>
                        </div>
                    </div>
                    <div className="text-right">
                        <span className="inline-block rounded-md border border-amber-600 bg-amber-50 px-2.5 py-1 text-xs font-extrabold tracking-wider text-amber-900 uppercase">
                            FORM KERUSAKAN &amp; PENGGANTIAN PART
                        </span>
                        <p className="mt-1.5 font-mono text-xs font-bold text-slate-800">No: {docNo}</p>
                        <p className="text-[11px] text-slate-500">Waktu: {data.created_at ?? '—'}</p>
                    </div>
                </div>

                {/* Informasi Item Tooling & Lokasi Kerja */}
                <div className="mt-5 grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-slate-50/70 p-4 text-sm sm:grid-cols-2 print:grid-cols-2 print:bg-white">
                    <div>
                        <p className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">Tools Name YES</p>
                        <p className="mt-0.5 font-mono text-base font-extrabold text-slate-900">
                            {tool.tools_name_yes ?? '—'}
                        </p>
                        {(tool.item_name || tool.part_name) && (
                            <p className="text-xs text-slate-600">{tool.item_name || tool.part_name}</p>
                        )}
                        <p className="mt-1 text-xs text-slate-500">
                            Lokasi Rak: <span className="font-semibold text-slate-800">{tool.rack_location ?? '—'}</span>
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <p className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                Mesin / Shift
                            </p>
                            <p className="mt-0.5 font-semibold text-slate-900">{machineInfo || '—'}</p>
                        </div>
                        <div>
                            <p className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                Pelapor / PIC
                            </p>
                            <p className="mt-0.5 font-semibold text-slate-900">{picName}</p>
                        </div>
                    </div>
                </div>

                {/* Tabel Rincian Kerusakan & Penggantian Stok */}
                <table className="mt-5 w-full border-collapse border border-slate-300 text-sm">
                    <thead>
                        <tr className="bg-slate-100 text-xs font-bold text-slate-700 uppercase">
                            <th className="border border-slate-300 px-3 py-2 text-center">Kode</th>
                            <th className="border border-slate-300 px-3 py-2 text-left">
                                Jenis Kerusakan &amp; Kronologi
                            </th>
                            {hasStockSnapshot && (
                                <th className="border border-slate-300 px-3 py-2 text-right">Stok Awal</th>
                            )}
                            <th className="border border-slate-300 px-3 py-2 text-right">Qty Diganti</th>
                            {hasStockSnapshot && (
                                <th className="border border-slate-300 px-3 py-2 text-right">Stok Akhir</th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td className="border border-slate-300 px-3 py-3 text-center">
                                <span className="inline-flex size-7 items-center justify-center rounded-md border border-slate-400 bg-slate-100 font-mono text-sm font-extrabold text-slate-900">
                                    {damageCode}
                                </span>
                            </td>
                            <td className="border border-slate-300 px-3 py-3 text-slate-800">
                                <p className="font-bold text-slate-900">{damageDesc}</p>
                                {data.notes && <p className="mt-0.5 text-xs text-slate-600">{data.notes}</p>}
                            </td>
                            {hasStockSnapshot && (
                                <td className="border border-slate-300 px-3 py-3 text-right font-mono tabular-nums">
                                    {formatInt(data.stock_before)}
                                </td>
                            )}
                            <td className="border border-slate-300 px-3 py-3 text-right font-mono font-extrabold text-amber-800 tabular-nums">
                                −{formatInt(qty)}
                            </td>
                            {hasStockSnapshot && (
                                <td className="border border-slate-300 px-3 py-3 text-right font-mono font-extrabold text-slate-900 tabular-nums">
                                    {formatInt(data.stock_after)}
                                </td>
                            )}
                        </tr>
                    </tbody>
                </table>

                {/* Catatan Status Purchase Request (jika stok menyentuh batas minimum) */}
                {isOrderAlert && (
                    <div className="mt-4 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-2.5 text-xs font-semibold text-amber-900 print:bg-white">
                        <AlertTriangle className="size-4 shrink-0 text-amber-700" />
                        <span>
                            Perhatian: Stok aktual berada di bawah batas minimum (Min E: {formatInt(tool.min_stock_e ?? 0)}{' '}
                            pcs). Segera ajukan Purchase Request (PR) / pengadaan ulang.
                        </span>
                    </div>
                )}

                {/* Kolom Tanda Tangan */}
                <div className="mt-8 grid grid-cols-3 gap-6 text-center text-xs text-slate-700">
                    <div>
                        <p className="font-semibold">Dilaporkan Oleh,</p>
                        <div className="mt-14 border-b border-slate-400" />
                        <p className="mt-1 font-bold text-slate-800">{picName}</p>
                        <p className="text-[11px] text-slate-500">Operator / PIC Produksi</p>
                    </div>
                    <div>
                        <p className="font-semibold">Diperiksa &amp; Diserahkan,</p>
                        <div className="mt-14 border-b border-slate-400" />
                        <p className="mt-1 font-medium text-slate-600">Admin Tooling / GA-IT</p>
                    </div>
                    <div>
                        <p className="font-semibold">Mengetahui / Menyetujui,</p>
                        <div className="mt-14 border-b border-slate-400" />
                        <p className="mt-1 font-medium text-slate-600">Leader / Supervisor Produksi</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}