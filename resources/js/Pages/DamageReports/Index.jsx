import { Link, router } from '@inertiajs/react';
import { ClipboardList, Filter, Printer, ScanLine, X } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/Components/Pagination';
import { Button, Card, EmptyState, Field, Input, Select, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { daysAgo, formatInt } from '@/lib/format';
import { buildUrl, cleanParams } from '@/lib/urls';

const EMPTY_FILTERS = {
    date_from: '',
    date_to: '',
    shift: '',
    damage_reason: '',
    action: '',
    operator: '',
    search: '',
};

export default function Index({ reports, summary, filters, reasons, actions }) {
    const [form, setForm] = useState({ ...EMPTY_FILTERS, ...filters });
    const set = (key) => (event) => setForm((prev) => ({ ...prev, [key]: event.target.value }));

    const apply = (next = form) => {
        router.get('/damage-reports', cleanParams(next), { preserveState: true, preserveScroll: true, replace: true });
    };

    const submit = (event) => {
        event.preventDefault();
        apply();
    };

    const reset = () => {
        setForm(EMPTY_FILTERS);
        apply(EMPTY_FILTERS);
    };

    const quickRange = (days) => {
        const next = { ...form, date_from: daysAgo(days), date_to: daysAgo(0) };
        setForm(next);
        apply(next);
    };

    const totalReports = Object.values(summary).reduce((sum, item) => sum + item.reports, 0);

    return (
        <AppLayout
            title="Riwayat Laporan Kerusakan"
            subtitle="Seluruh laporan kerusakan & penggantian part dari form QR"
            actions={
                <Button as={Link} href="/damage-reports/create" size="sm">
                    <ScanLine className="size-4" /> Form Kerusakan
                </Button>
            }
        >
            <section className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {reasons.map((reason) => {
                    const item = summary[reason.value] ?? { reports: 0, qty: 0 };

                    return (
                        <Card key={reason.value} className="border-l-4 border-l-red-600 p-4">
                            <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">{reason.label}</p>
                            <p className="mt-1 text-2xl font-extrabold tabular-nums">{formatInt(item.reports)}</p>
                            <p className="text-xs text-slate-500">
                                laporan · {formatInt(item.qty)} pcs diganti
                                {totalReports > 0 && ` · ${Math.round((item.reports / totalReports) * 100)}%`}
                            </p>
                        </Card>
                    );
                })}
            </section>

            <Card>
                <form
                    onSubmit={submit}
                    className="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8"
                >
                    <Field label="Dari">
                        <Input type="date" value={form.date_from} onChange={set('date_from')} />
                    </Field>
                    <Field label="Sampai">
                        <Input type="date" value={form.date_to} onChange={set('date_to')} />
                    </Field>
                    <Field label="Shift">
                        <Select value={form.shift} onChange={set('shift')}>
                            <option value="">Semua</option>
                            {[1, 2, 3].map((shift) => (
                                <option key={shift} value={shift}>
                                    Shift {shift}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Alasan">
                        <Select value={form.damage_reason} onChange={set('damage_reason')}>
                            <option value="">Semua</option>
                            {reasons.map((reason) => (
                                <option key={reason.value} value={reason.value}>
                                    {reason.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Tindakan">
                        <Select value={form.action} onChange={set('action')}>
                            <option value="">Semua</option>
                            {actions.map((action) => (
                                <option key={action.value} value={action.value}>
                                    {action.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Operator">
                        <Input value={form.operator} onChange={set('operator')} placeholder="Nama" />
                    </Field>
                    <Field label="Cari" className="xl:col-span-2">
                        <Input
                            value={form.search}
                            onChange={set('search')}
                            placeholder="No. laporan / Tools Name YES / mesin"
                        />
                    </Field>
                    <div className="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-8">
                        <Button type="submit" size="sm">
                            <Filter className="size-4" /> Terapkan
                        </Button>
                        <Button variant="ghost" size="sm" onClick={reset}>
                            <X className="size-4" /> Reset
                        </Button>
                        <span className="mx-1 h-5 w-px bg-slate-200" />
                        <Button variant="outline" size="xs" onClick={() => quickRange(0)}>
                            Hari ini
                        </Button>
                        <Button variant="outline" size="xs" onClick={() => quickRange(6)}>
                            7 hari
                        </Button>
                        <Button variant="outline" size="xs" onClick={() => quickRange(29)}>
                            30 hari
                        </Button>
                    </div>
                </form>

                {reports.data.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Belum ada laporan"
                        description="Laporan dari form QR akan muncul di sini."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1100px] text-sm">
                            <thead className="bg-slate-800 text-[11px] font-bold tracking-wide text-white uppercase">
                                <tr>
                                    <th className="px-3 py-2.5 text-left">No. Laporan</th>
                                    <th className="px-3 py-2.5 text-left">Waktu</th>
                                    <th className="px-3 py-2.5 text-left">Item Tooling</th>
                                    <th className="px-3 py-2.5 text-left">Operator</th>
                                    <th className="px-3 py-2.5 text-left">Alasan</th>
                                    <th className="px-3 py-2.5 text-left">Tindakan</th>
                                    <th className="px-3 py-2.5 text-right">Qty</th>
                                    <th className="px-3 py-2.5 text-right">Shot Count</th>
                                    <th className="px-3 py-2.5 text-right">Stok</th>
                                    <th className="px-3 py-2.5 text-center">Slip</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {reports.data.map((report) => (
                                    <tr key={report.id} className="hover:bg-blue-50/60">
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/damage-reports/${report.id}`}
                                                className="font-mono text-xs font-bold text-blue-700 hover:underline"
                                            >
                                                {report.report_no}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 whitespace-nowrap text-slate-600">
                                            {report.reported_at_display}
                                        </td>
                                        <td className="px-3 py-2">
                                            {report.tool ? (
                                                <>
                                                    <Link
                                                        href={`/tools/${report.tool.id}`}
                                                        className="font-mono text-[13px] font-bold hover:text-blue-700"
                                                    >
                                                        {report.tool.tools_name_yes}
                                                    </Link>
                                                    <p className="text-xs text-slate-500">
                                                        {report.tool.machine_name} · Rak{' '}
                                                        {report.tool.rack_location ?? '—'}
                                                    </p>
                                                </>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <p className="font-semibold">{report.operator_name}</p>
                                            <p className="text-xs text-slate-500">Shift {report.shift}</p>
                                        </td>
                                        <td className="px-3 py-2">{report.damage_reason_label}</td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={cn(
                                                    'rounded px-1.5 py-0.5 text-[11px] font-bold',
                                                    report.cuts_stock
                                                        ? 'bg-red-50 text-red-700'
                                                        : 'bg-blue-50 text-blue-700',
                                                )}
                                            >
                                                {report.cuts_stock ? 'REPLACE NEW' : 'REGRIND / REPAIR'}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right font-bold tabular-nums">
                                            {formatInt(report.qty_replaced)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatInt(report.actual_shot_count)}
                                            {report.life_time_usage_pct !== null && (
                                                <p className="text-[11px] text-slate-500">
                                                    {report.life_time_usage_pct}% LT
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                            {formatInt(report.stock_before)} → <b>{formatInt(report.stock_after)}</b>
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            <Button
                                                as={Link}
                                                href={buildUrl(`/damage-reports/${report.id}`, { print: 1 })}
                                                variant="outline"
                                                size="xs"
                                                title="Cetak slip"
                                            >
                                                <Printer className="size-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={reports.meta} links={reports.links} />
            </Card>
        </AppLayout>
    );
}
