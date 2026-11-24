import { Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Plus, Printer } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import logoYahata from '@/assets/logo-yahata.jpeg';
import { formatInt } from '@/lib/format';

export default function Show({ transaction }) {
    const isInbound = transaction.type === 'IN';

    // Opsional: otomatis membuka dialog print 400ms setelah halaman dimuat
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('autoprint') === '1') {
            const timer = setTimeout(() => window.print(), 400);
            return () => clearTimeout(timer);
        }
    }, []);

    return (
        <AppLayout
            title="Bukti Transaksi Stok"
            subtitle={`Referensi ${transaction.reference_no}`}
            actions={
                <div className="flex flex-wrap gap-2 print:hidden">
                    <Button as={Link} href="/transactions/create" variant="outline">
                        <Plus className="size-4" /> Transaksi Baru
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
                        Mutasi stok <b>{isInbound ? 'MASUK (IN)' : 'KELUAR (OUT)'}</b> berhasil disimpan ke database.
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
                        <span
                            className={`inline-block rounded-md border px-2.5 py-1 text-xs font-extrabold tracking-wider uppercase ${
                                isInbound
                                    ? 'border-emerald-600 bg-emerald-50 text-emerald-800'
                                    : 'border-blue-600 bg-blue-50 text-blue-800'
                            }`}
                        >
                            {isInbound ? 'SLIP BARANG MASUK (IN)' : 'SLIP PENGAMBILAN (OUT)'}
                        </span>
                        <p className="mt-1.5 font-mono text-xs font-bold text-slate-800">
                            No: {transaction.reference_no}
                        </p>
                        <p className="text-[11px] text-slate-500">Waktu: {transaction.created_at}</p>
                    </div>
                </div>

                {/* Informasi Item & PIC */}
                <div className="mt-5 grid grid-cols-2 gap-4 rounded-lg border border-slate-200 bg-slate-50/70 p-4 text-sm print:bg-white">
                    <div>
                        <p className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">Tools Name YES</p>
                        <p className="mt-0.5 font-mono text-base font-extrabold text-slate-900">
                            {transaction.tool.tools_name_yes}
                        </p>
                        {transaction.tool.item_name && (
                            <p className="text-xs text-slate-600">{transaction.tool.item_name}</p>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <p className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">Lokasi Rak</p>
                            <p className="mt-0.5 font-semibold text-slate-900">
                                {transaction.tool.rack_location ?? '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">PIC / Operator</p>
                            <p className="mt-0.5 font-semibold text-slate-900">{transaction.pic_name || '—'}</p>
                        </div>
                    </div>
                </div>

                {/* Tabel Rincian Mutasi Stok */}
                <table className="mt-5 w-full border-collapse border border-slate-300 text-sm">
                    <thead>
                        <tr className="bg-slate-100 text-xs font-bold text-slate-700 uppercase">
                            <th className="border border-slate-300 px-3 py-2 text-left">Keterangan Mutasi</th>
                            <th className="border border-slate-300 px-3 py-2 text-right">Stok Awal</th>
                            <th className="border border-slate-300 px-3 py-2 text-right">Jumlah ({transaction.type})</th>
                            <th className="border border-slate-300 px-3 py-2 text-right">Stok Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td className="border border-slate-300 px-3 py-3 text-slate-800">
                                {transaction.notes || (isInbound ? 'Penerimaan / Restock Tooling' : 'Pemakaian Lini Produksi')}
                            </td>
                            <td className="border border-slate-300 px-3 py-3 text-right font-mono tabular-nums">
                                {formatInt(transaction.stock_before)}
                            </td>
                            <td
                                className={`border border-slate-300 px-3 py-3 text-right font-mono font-extrabold tabular-nums ${
                                    isInbound ? 'text-emerald-700' : 'text-blue-700'
                                }`}
                            >
                                {transaction.qty_change > 0
                                    ? `+${formatInt(transaction.qty_change)}`
                                    : formatInt(transaction.qty_change)}
                            </td>
                            <td className="border border-slate-300 px-3 py-3 text-right font-mono font-extrabold text-slate-900 tabular-nums">
                                {formatInt(transaction.stock_after)}
                            </td>
                        </tr>
                    </tbody>
                </table>

                {/* Kolom Tanda Tangan */}
                <div className="mt-8 grid grid-cols-3 gap-6 text-center text-xs text-slate-700">
                    <div>
                        <p className="font-semibold">Diserahkan Oleh,</p>
                        <div className="mt-14 border-b border-slate-400" />
                        <p className="mt-1 font-medium text-slate-500">Admin Tooling / GA-IT</p>
                    </div>
                    <div>
                        <p className="font-semibold">Diterima / Diambil Oleh,</p>
                        <div className="mt-14 border-b border-slate-400" />
                        <p className="mt-1 font-bold text-slate-800">{transaction.pic_name || '( Nama PIC )'}</p>
                    </div>
                    <div>
                        <p className="font-semibold">Mengetahui,</p>
                        <div className="mt-14 border-b border-slate-400" />
                        <p className="mt-1 font-medium text-slate-500">Leader / Supervisor Produksi</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}