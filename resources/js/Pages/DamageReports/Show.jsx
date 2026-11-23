import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer, ScanLine } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatInt } from '@/lib/format';
import { buildUrl } from '@/lib/urls';

/**
 * Slip / Formulir resmi Laporan Kerusakan & Penggantian Tooling — siap cetak (window.print()).
 */
export default function Show({ report }) {
    const { app } = usePage().props;
    const tool = report.tool ?? {};
    const company = (app?.company ?? 'PT. Yahata Fastener Indonesia').toUpperCase();

    useEffect(() => {
        if (new URLSearchParams(window.location.search).get('print') !== '1') {
            return undefined;
        }

        const timer = window.setTimeout(() => window.print(), 400);

        return () => window.clearTimeout(timer);
    }, []);

    const printedAt = new Date().toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

    return (
        <AppLayout
            title={`Slip ${report.report_no}`}
            subtitle={`${tool.tools_name_yes ?? ''} · ${report.reported_at_display}`}
            actions={
                <>
                    <Button as={Link} href="/damage-reports" variant="ghost" size="sm">
                        <ArrowLeft className="size-4" /> Riwayat
                    </Button>
                    <Button
                        as={Link}
                        href={buildUrl('/damage-reports/create', {
                            machine: tool.machine_name,
                            rack: tool.rack_location,
                        })}
                        variant="outline"
                        size="sm"
                    >
                        <ScanLine className="size-4" /> Lapor Lagi
                    </Button>
                    <Button size="sm" onClick={() => window.print()}>
                        <Printer className="size-4" /> Cetak Slip
                    </Button>
                </>
            }
        >
            <div className="mx-auto max-w-[210mm]">
                <div className="mb-4 flex gap-2 sm:hidden print:hidden">
                    <Button className="flex-1" onClick={() => window.print()}>
                        <Printer className="size-4" /> Cetak Slip
                    </Button>
                </div>

                <article className="border-2 border-slate-900 bg-white p-6 text-[13px] leading-snug text-slate-900 shadow-sm print:border-black print:shadow-none">
                    <header className="flex items-start justify-between gap-4 border-b-2 border-slate-900 pb-3">
                        <div>
                            <p className="text-lg font-black tracking-wide">{company}</p>
                            <p className="text-xs font-semibold text-slate-600">
                                {app?.department ?? 'Production Department'} · Tooling Control
                            </p>
                        </div>
                        <table className="border border-slate-900 text-xs">
                            <tbody>
                                <SlipMeta label="No. Dokumen" value={report.report_no} mono />
                                <SlipMeta label="Tanggal" value={report.reported_date} />
                                <SlipMeta
                                    label="Jam / Shift"
                                    value={`${report.reported_time} · Shift ${report.shift}`}
                                />
                            </tbody>
                        </table>
                    </header>

                    <h1 className="my-4 text-center text-base font-black tracking-wider uppercase">
                        Formulir Laporan Kerusakan &amp; Penggantian Tooling
                    </h1>

                    <SlipSection title="A. Data Tooling">
                        <SlipGrid
                            rows={[
                                ['Tools Name YES', tool.tools_name_yes, true],
                                ['Tools Name', tool.tools_name],
                                ['Machine Name', tool.machine_name],
                                ['Step', tool.step],
                                ['Part Name', tool.part_name],
                                ['Part No.', tool.part_no, true],
                                ['Customer', tool.customer],
                                ['Rack Location', tool.rack_location],
                                ['Life Time', tool.life_time ? `${formatInt(tool.life_time)} pcs` : '—'],
                            ]}
                        />
                    </SlipSection>

                    <SlipSection title="B. Detail Kerusakan">
                        <SlipGrid
                            rows={[
                                ['Nama Operator', report.operator_name],
                                ['Shift', `Shift ${report.shift}`],
                                ['Alasan Kerusakan', report.damage_reason_label],
                                ['Tindakan', report.action_label],
                                ['Qty Diganti', `${formatInt(report.qty_replaced)} pcs`],
                                [
                                    'Aktual Shot Count',
                                    `${formatInt(report.actual_shot_count)} shot${report.life_time_usage_pct !== null ? ` (${report.life_time_usage_pct}% life time)` : ''}`,
                                ],
                            ]}
                        />
                    </SlipSection>

                    <SlipSection title="C. Dampak Stok">
                        <table className="w-full border-collapse text-center">
                            <thead>
                                <tr className="bg-slate-100 text-xs font-bold uppercase print:bg-slate-100">
                                    <th className="border border-slate-900 px-2 py-1.5">Stok Sebelum</th>
                                    <th className="border border-slate-900 px-2 py-1.5">Dipotong</th>
                                    <th className="border border-slate-900 px-2 py-1.5">Stok Sesudah</th>
                                    <th className="border border-slate-900 px-2 py-1.5">Transaksi Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="text-base font-bold tabular-nums">
                                    <td className="border border-slate-900 px-2 py-2">
                                        {formatInt(report.stock_before)}
                                    </td>
                                    <td className="border border-slate-900 px-2 py-2">
                                        {report.cuts_stock ? `−${formatInt(report.qty_replaced)}` : '0'}
                                    </td>
                                    <td className="border border-slate-900 px-2 py-2">
                                        {formatInt(report.stock_after)}
                                    </td>
                                    <td className="border border-slate-900 px-2 py-2 text-xs font-semibold">
                                        {report.cuts_stock ? 'DAMAGE_REPLACEMENT' : 'Tidak ada (Regrind / Repair)'}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </SlipSection>

                    <SlipSection title="D. Catatan">
                        <div className="min-h-14 border border-slate-900 px-3 py-2 whitespace-pre-line">
                            {report.notes || '—'}
                        </div>
                    </SlipSection>

                    <div className="mt-6 grid grid-cols-3 gap-4 text-center text-xs">
                        {[
                            ['Dilaporkan oleh', 'Operator', report.operator_name],
                            ['Diperiksa oleh', 'Leader / Foreman', ''],
                            ['Diterima oleh', 'Tool Keeper / Admin', ''],
                        ].map(([caption, role, name]) => (
                            <div key={role} className="border border-slate-900">
                                <p className="border-b border-slate-900 bg-slate-100 py-1 font-bold print:bg-slate-100">
                                    {caption}
                                </p>
                                <div className="h-20" />
                                <p className="border-t border-slate-900 py-1 font-semibold">
                                    {name || '(..............................)'}
                                </p>
                                <p className="pb-1 text-[11px] text-slate-600">{role}</p>
                            </div>
                        ))}
                    </div>

                    <footer className="mt-4 flex justify-between border-t border-slate-300 pt-2 text-[10px] text-slate-500">
                        <span>Dokumen dibuat otomatis oleh Yahata Control Stock Tool</span>
                        <span>Dicetak: {printedAt}</span>
                    </footer>
                </article>
            </div>
        </AppLayout>
    );
}

function SlipMeta({ label, value, mono = false }) {
    return (
        <tr>
            <td className="border border-slate-900 bg-slate-100 px-2 py-1 font-semibold whitespace-nowrap print:bg-slate-100">
                {label}
            </td>
            <td className={`border border-slate-900 px-2 py-1 font-bold whitespace-nowrap ${mono ? 'font-mono' : ''}`}>
                {value ?? '—'}
            </td>
        </tr>
    );
}

function SlipSection({ title, children }) {
    return (
        <section className="mt-4 break-inside-avoid">
            <h2 className="mb-1.5 text-xs font-black tracking-wider uppercase">{title}</h2>
            {children}
        </section>
    );
}

function SlipGrid({ rows }) {
    return (
        <table className="w-full border-collapse">
            <tbody>
                {rows.map(([label, value, mono]) => (
                    <tr key={label}>
                        <td className="w-44 border border-slate-900 bg-slate-50 px-2 py-1 text-xs font-semibold print:bg-slate-50">
                            {label}
                        </td>
                        <td className={`border border-slate-900 px-2 py-1 font-semibold ${mono ? 'font-mono' : ''}`}>
                            {value || '—'}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
