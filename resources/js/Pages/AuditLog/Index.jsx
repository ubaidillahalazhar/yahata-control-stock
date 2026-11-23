import { Link, router } from '@inertiajs/react';
import { Download, Filter, History, X } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/Components/Pagination';
import { TransactionTypeBadge } from '@/Components/StatusBadge';
import { Button, Card, EmptyState, Field, Input, Select, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { daysAgo, formatInt, formatSigned } from '@/lib/format';
import { buildUrl, cleanParams } from '@/lib/urls';

const EMPTY_FILTERS = { date_from: '', date_to: '', type: '', pic: '', search: '', tool_id: '' };

const TYPE_TONES = {
    IN: 'border-l-emerald-600',
    OUT: 'border-l-slate-700',
    OPNAME_ADJUST: 'border-l-blue-600',
    DAMAGE_REPLACEMENT: 'border-l-red-600',
};

export default function Index({ transactions, summary, filters, types }) {
    const [form, setForm] = useState({ ...EMPTY_FILTERS, ...filters });
    const set = (key) => (event) => setForm((prev) => ({ ...prev, [key]: event.target.value }));

    const apply = (next = form) => {
        router.get('/audit-log', cleanParams(next), { preserveState: true, preserveScroll: true, replace: true });
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

    const toggleType = (value) => {
        const next = { ...form, type: form.type === value ? '' : value };
        setForm(next);
        apply(next);
    };

    return (
        <AppLayout
            title="Audit Log History"
            subtitle="Kronologi seluruh pergerakan stok: IN, OUT, OPNAME_ADJUST, DAMAGE_REPLACEMENT"
            actions={
                <Button as="a" href={buildUrl('/audit-log/export', cleanParams(filters))} variant="outline" size="sm">
                    <Download className="size-4" /> Export Excel
                </Button>
            }
        >
            <section className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {types.map((type) => {
                    const item = summary[type.value] ?? { rows: 0, qty: 0 };
                    const active = form.type === type.value;

                    return (
                        <button
                            key={type.value}
                            type="button"
                            onClick={() => toggleType(type.value)}
                            aria-pressed={active}
                            className={cn(
                                'rounded-lg border border-l-4 border-slate-200 bg-white p-4 text-left shadow-sm transition hover:shadow-md',
                                TYPE_TONES[type.value],
                                active && 'ring-2 ring-blue-600',
                            )}
                        >
                            <TransactionTypeBadge type={type.value} title={type.label} />
                            <p className="mt-2 text-2xl font-extrabold tabular-nums">{formatInt(item.rows)}</p>
                            <p className="text-xs text-slate-500">
                                transaksi · net <b className="tabular-nums">{formatSigned(item.qty)}</b> pcs
                            </p>
                        </button>
                    );
                })}
            </section>

            <Card>
                <form
                    onSubmit={submit}
                    className="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-6"
                >
                    <Field label="Dari tanggal">
                        <Input type="date" value={form.date_from} onChange={set('date_from')} />
                    </Field>
                    <Field label="Sampai tanggal">
                        <Input type="date" value={form.date_to} onChange={set('date_to')} />
                    </Field>
                    <Field label="Jenis transaksi">
                        <Select value={form.type} onChange={set('type')}>
                            <option value="">Semua jenis</option>
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.value} — {type.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="PIC / Operator">
                        <Input value={form.pic} onChange={set('pic')} placeholder="Nama" />
                    </Field>
                    <Field label="Cari item / referensi" className="lg:col-span-2">
                        <Input
                            value={form.search}
                            onChange={set('search')}
                            placeholder="Tools Name YES, mesin, no. referensi…"
                        />
                    </Field>
                    <div className="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-6">
                        <Button type="submit" size="sm">
                            <Filter className="size-4" /> Terapkan Filter
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
                        {form.tool_id && (
                            <span className="ml-auto rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800">
                                Filter item ID {form.tool_id}
                            </span>
                        )}
                    </div>
                </form>

                {transactions.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="Tidak ada transaksi"
                        description="Ubah filter tanggal / jenis transaksi."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1000px] text-sm">
                            <thead className="bg-slate-800 text-[11px] font-bold tracking-wide text-white uppercase">
                                <tr>
                                    <th className="px-3 py-2.5 text-left">Waktu</th>
                                    <th className="px-3 py-2.5 text-left">Jenis</th>
                                    <th className="px-3 py-2.5 text-left">Item Tooling</th>
                                    <th className="px-3 py-2.5 text-right">Qty</th>
                                    <th className="px-3 py-2.5 text-right">Sebelum → Sesudah</th>
                                    <th className="px-3 py-2.5 text-left">Referensi</th>
                                    <th className="px-3 py-2.5 text-left">PIC</th>
                                    <th className="px-3 py-2.5 text-left">Catatan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {transactions.data.map((trx) => (
                                    <tr key={trx.id} className="align-top hover:bg-blue-50/60">
                                        <td className="px-3 py-2 whitespace-nowrap text-slate-600 tabular-nums">
                                            {trx.created_at_display}
                                        </td>
                                        <td className="px-3 py-2">
                                            <TransactionTypeBadge type={trx.type} title={trx.type_label} />
                                        </td>
                                        <td className="px-3 py-2">
                                            {trx.tool ? (
                                                <>
                                                    <Link
                                                        href={`/tools/${trx.tool.id}`}
                                                        className="font-mono text-[13px] font-bold hover:text-blue-700"
                                                    >
                                                        {trx.tool.tools_name_yes}
                                                    </Link>
                                                    <p className="text-xs text-slate-500">
                                                        {trx.tool.tools_name} · Rak {trx.tool.rack_location ?? '—'}
                                                    </p>
                                                </>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td
                                            className={cn(
                                                'px-3 py-2 text-right text-base font-extrabold tabular-nums',
                                                trx.qty_change < 0 ? 'text-red-600' : 'text-emerald-700',
                                            )}
                                        >
                                            {formatSigned(trx.qty_change)}
                                        </td>
                                        <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                            {formatInt(trx.stock_before)} → <b>{formatInt(trx.stock_after)}</b>
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {trx.damage_report_id ? (
                                                <Link
                                                    href={`/damage-reports/${trx.damage_report_id}`}
                                                    className="font-bold text-blue-700 hover:underline"
                                                >
                                                    {trx.reference_no}
                                                </Link>
                                            ) : (
                                                (trx.reference_no ?? '—')
                                            )}
                                        </td>
                                        <td className="px-3 py-2 font-semibold">{trx.pic_name ?? '—'}</td>
                                        <td className="max-w-xs px-3 py-2 text-xs text-slate-600">
                                            {trx.notes ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={transactions.meta} links={transactions.links} />
            </Card>
        </AppLayout>
    );
}
