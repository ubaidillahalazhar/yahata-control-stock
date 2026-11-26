import { Link, usePage } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowLeft,
    ArrowUpFromLine,
    BarChart3,
    Calculator,
    ClipboardList,
    Gauge,
    History,
    Info,
    QrCode,
    RotateCcw,
    Wrench,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useCallback, useMemo, useState } from 'react';
import Modal from '@/Components/Modal';
import StatusBadge, { CheckBadge, TransactionTypeBadge } from '@/Components/StatusBadge';
import LifeBar from '@/Components/LifeBar';
import TransactionForm from '@/Components/TransactionForm';
import UsageForm, { CancelUsageForm, CorrectUsageForm, ResetLifeForm } from '@/Components/UsageForm';
import UsageLogTable from '@/Components/UsageLogTable';
import { Button, Card, CardHeader, EmptyState, Stat, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDecimal, formatInt, formatMonth, formatNumber, formatSigned, monthLabels } from '@/lib/format';
import { LIFE_TONES, lifeOf } from '@/lib/life';
import { buildUrl, damageFormUrl, resolveQrBase } from '@/lib/urls';

export default function Show({ tool, transactions, damageReports, usageLogs, forecastActuals = [], monthlyActuals = [] }) {
    const { app } = usePage().props;
    const [transactionType, setTransactionType] = useState(null);
    const closeModal = useCallback(() => setTransactionType(null), []);
    // 'usage' | 'reset' | objek log (untuk dibatalkan)
    const [lifeAction, setLifeAction] = useState(null);
    const closeLifeAction = useCallback(() => setLifeAction(null), []);
    const life = lifeOf(tool);
    const lifeTone = LIFE_TONES[life.level] ?? LIFE_TONES.green;
    const lifeBorder = { green: 'border-l-emerald-600', yellow: 'border-l-amber-400', red: 'border-l-red-600' }[life.level];
    const lifeModalTitle =
        lifeAction === 'usage'
            ? 'Input Pemakaian Life Time'
            : lifeAction === 'reset'
              ? 'Ganti Tool (Reset Life)'
              : lifeAction?.kind === 'correct'
                ? 'Koreksi Input Pemakaian'
                : 'Batalkan Input';
    const months = useMemo(() => monthLabels(tool.forecast_months), [tool.forecast_months]);
    const { base, isLocal } = useMemo(() => resolveQrBase(app?.url), [app?.url]);
    const qrUrl = damageFormUrl(base, { tool_id: tool.id });
    const isOrder = tool.status_info === 'ORDER';

    const master = [
        ['Customer', tool.customer],
        ['Machine Name', tool.machine_name],
        ['Step', tool.step],
        ['Tools Name', tool.tools_name],
        ['Yes Name', tool.yes_name],
        ['Part Name', tool.part_name],
        ['Part No.', tool.part_no],
        ['Rack Location', tool.rack_location],
        ['Life Time (L)', `${formatInt(tool.life_time)} pcs`],
        ['Lead Time Order (AN)', `${formatInt(tool.lead_time_days)} hari`],
    ];

    const chain = [
        {
            code: 'R',
            label: 'Forecast Average',
            formula: 'ROUND(AVERAGEIF(M:Q, ">0"), 0) — bulan ke-1 s.d. ke-5',
            value: formatNumber(tool.forecast_avg, { max: 2 }),
        },
        { code: 'AC', label: 'A. Tooling Need', formula: 'ROUND(R / L, 2)', value: formatDecimal(tool.tooling_need_a) },
        {
            code: 'AD',
            label: 'B. Safety Stock Lead Time',
            formula: `ROUNDUP((A / 20) × ${tool.lead_time_days}, 0)`,
            value: formatInt(tool.safety_stock_lead_time_b),
        },
        {
            code: 'AE',
            label: 'C. Safety Stock Fluktuasi',
            formula: 'A × 20%',
            value: formatNumber(tool.safety_stock_fluct_c, { max: 4 }),
        },
        {
            code: 'AF',
            label: 'D. Safety Stock Tambahan',
            formula: 'IF(L ≤ 400.000, 1, 0)',
            value: formatInt(tool.safety_stock_add_d),
        },
        {
            code: 'AG',
            label: 'E. Min Stock',
            formula: 'ROUNDUP(A + D, 0)',
            value: formatInt(tool.min_stock_e),
            strong: true,
        },
        {
            code: 'AH',
            label: 'Max Total StandBy',
            formula: 'ROUNDUP(B + C + D, 0)',
            value: formatInt(tool.max_total_stand_by),
            strong: true,
        },
        {
            code: 'AI',
            label: 'Status Info',
            formula: 'IF(K ≥ E, "SECURE", "ORDER")',
            value: <StatusBadge status={tool.status_info} />,
        },
        { code: 'AJ', label: 'Est Prod Qty (Min Stock)', formula: 'E × L', value: formatInt(tool.est_prod_qty_min) },
        {
            code: 'AK',
            label: 'Est Prod Qty (Stand By)',
            formula: 'Max × L',
            value: formatInt(tool.est_prod_qty_stand_by),
        },
        {
            code: 'AL',
            label: 'Koreksi Min Stock',
            formula: 'IF(AJ ≥ R, "OK", "CEK FORMULA")',
            value: <CheckBadge value={tool.koreksi_min_stock} />,
        },
        {
            code: 'AM',
            label: 'Koreksi Stand By',
            formula: 'IF(AK ≥ R, "OK", "CEK FORMULA")',
            value: <CheckBadge value={tool.koreksi_stand_by} />,
        },
    ];

    return (
        <AppLayout
            title={tool.tools_name_yes}
            subtitle={`${tool.tools_name} · ${tool.machine_name ?? '—'} · Rak ${tool.rack_location ?? '—'}`}
            actions={
                <>
                    <Button as={Link} href="/" variant="ghost" size="sm">
                        <ArrowLeft className="size-4" /> Dashboard
                    </Button>
                    <Button size="sm" onClick={() => setLifeAction('usage')}>
                        <Gauge className="size-4" /> Input Pemakaian
                    </Button>
                    <Button variant="success" size="sm" onClick={() => setTransactionType('IN')}>
                        <ArrowDownToLine className="size-4" /> IN
                    </Button>
                    <Button variant="dark" size="sm" onClick={() => setTransactionType('OUT')} disabled={tool.is_empty}>
                        <ArrowUpFromLine className="size-4" /> OUT
                    </Button>
                    <Button
                        as={Link}
                        href={buildUrl('/damage-reports/create', { tool_id: tool.id })}
                        variant="outline-danger"
                        size="sm"
                    >
                        <Wrench className="size-4" /> Lapor Rusak
                    </Button>
                </>
            }
        >
            <div className="grid gap-5 md:grid-cols-2 2xl:grid-cols-4">
                <Card className={cn('border-l-4', isOrder ? 'border-l-red-600' : 'border-l-emerald-600')}>
                    <CardHeader title="Status Stok" subtitle="Kolom K vs E. Min Stock" />
                    <div className="space-y-4 p-5">
                        <div className="flex items-end justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold text-slate-500 uppercase">Current Stock (K)</p>
                                <p
                                    className={cn(
                                        'text-5xl font-extrabold tabular-nums',
                                        isOrder ? 'text-red-700' : 'text-slate-900',
                                    )}
                                >
                                    {formatInt(tool.current_stock)}
                                </p>
                            </div>
                            <StatusBadge status={tool.status_info} size="lg" />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Stat label="Min Stock (E)" value={formatInt(tool.min_stock_e)} />
                            <Stat label="Max StandBy" value={formatInt(tool.max_total_stand_by)} />
                            <Stat
                                label="Kurang ke Min"
                                value={formatInt(tool.shortage_qty)}
                                valueClassName={tool.shortage_qty > 0 ? 'text-red-600' : undefined}
                            />
                            <Stat label="Saran Order (Max − K)" value={formatInt(tool.suggested_order_qty)} />
                        </div>
                    </div>
                </Card>

                <Card className={cn('border-l-4', lifeBorder)}>
                    <CardHeader
                        icon={Gauge}
                        title="Status Life Time"
                        subtitle="Pemakaian sejak tool terakhir diganti"
                        actions={
                            <Button variant="ghost" size="xs" onClick={() => setLifeAction('reset')} title="Tool diganti baru">
                                <RotateCcw className="size-3.5" /> Ganti Tool
                            </Button>
                        }
                    />
                    <div className="space-y-4 p-5">
                        <div className="flex items-end justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold text-slate-500 uppercase">Sisa Life Time</p>
                                <p className={cn('text-4xl font-extrabold tabular-nums', lifeTone.text)}>
                                    {formatInt(life.remaining)}
                                </p>
                                <p className="text-xs text-slate-500">pcs dari {formatInt(tool.life_time)}</p>
                            </div>
                            <StatusBadge status={life.status} size="lg" />
                        </div>
                        <LifeBar life={life} lifeTime={tool.life_time} size="lg" />
                        <div className="grid grid-cols-2 gap-2">
                            <Stat label="Terpakai" value={formatInt(life.used)} />
                            {life.over > 0 ? (
                                <Stat label="Melebihi life" value={formatInt(life.over)} valueClassName="text-red-600" />
                            ) : (
                                <Stat
                                    label="Warna"
                                    value={`${lifeTone.label} (${lifeTone.range})`}
                                    valueClassName={cn('text-sm', lifeTone.text)}
                                />
                            )}
                        </div>
                    </div>
                </Card>

                <Card>
                    <CardHeader icon={Info} title="Master Data" subtitle="Dari sheet control stock" />
                    <dl className="divide-y divide-slate-100 px-5 py-2 text-sm">
                        {master.map(([label, value]) => (
                            <div key={label} className="flex justify-between gap-4 py-1.5">
                                <dt className="text-slate-500">{label}</dt>
                                <dd className="text-right font-semibold text-slate-900">{value ?? '—'}</dd>
                            </div>
                        ))}
                    </dl>
                </Card>

                <Card>
                    <CardHeader icon={QrCode} title="QR Form Kerusakan" subtitle="Tempel di mesin / rak" />
                    <div className="flex flex-col items-center gap-3 p-5 text-center">
                        <div className="rounded-lg border-2 border-slate-900 bg-white p-3">
                            <QRCodeSVG value={qrUrl} size={168} level="M" />
                        </div>
                        <p className="font-mono text-xs break-all text-slate-500">{qrUrl}</p>
                        {isLocal && (
                            <p className="rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">
                                URL masih localhost — set APP_URL ke IP server agar bisa discan dari HP.
                            </p>
                        )}
                        <Button as={Link} href="/qr-labels" variant="outline" size="sm">
                            Cetak label QR
                        </Button>
                    </div>
                </Card>
            </div>

            <Card className="mt-5">
                <CardHeader
                    icon={Calculator}
                    title="Forecast vs Actual & Kebutuhan Tooling"
                    subtitle="Forecast = rencana produksi (Excel) · Actual = total input Pemakaian Life Time di bulan itu · Need & Balance tetap dari Forecast"
                />
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                            <tr>
                                <th className="px-4 py-2 text-left">Baris</th>
                                {months.map((month, index) => (
                                    <th key={month + index} className="px-4 py-2 text-right">
                                        {month}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            <MonthRow label="Forecast (M..Q)" values={tool.forecasts} format={formatInt} />
                            <ActualRow
                                label="Actual Pemakaian (pcs)"
                                cells={forecastActuals}
                                render={(cell) => (cell.is_future ? '—' : formatInt(cell.actual_qty))}
                            />
                            <ActualRow
                                label="% Actual / Forecast"
                                cells={forecastActuals}
                                render={(cell) => (cell.is_future ? '—' : <PercentBadge cell={cell} />)}
                            />
                            <ActualRow
                                label="Tool Terpakai (Ganti Tool)"
                                cells={forecastActuals}
                                render={(cell) => (cell.is_future ? '—' : formatInt(cell.tools_replaced))}
                            />
                            <MonthRow
                                label="Tooling Need (S..W)"
                                values={tool.monthly_need}
                                format={(v) => formatDecimal(v)}
                            />
                            <MonthRow
                                label="Balanced Stock (X..AB)"
                                values={tool.monthly_balance}
                                format={(v) => formatDecimal(v)}
                                highlightNegative
                            />
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card className="mt-5">
                <CardHeader
                    icon={Calculator}
                    title="Rantai Perhitungan Safety Stock"
                    subtitle="Identik dengan rumus Excel kolom R, AC..AM"
                />
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <tbody className="divide-y divide-slate-100">
                            {chain.map((row) => (
                                <tr key={row.code} className={row.strong ? 'bg-blue-50/50' : undefined}>
                                    <td className="w-14 px-4 py-2 font-mono text-xs font-bold text-blue-700">
                                        {row.code}
                                    </td>
                                    <td className="px-4 py-2 font-semibold text-slate-800">{row.label}</td>
                                    <td className="px-4 py-2 font-mono text-xs text-slate-500">{row.formula}</td>
                                    <td
                                        className={cn(
                                            'px-4 py-2 text-right tabular-nums',
                                            row.strong ? 'text-lg font-extrabold' : 'font-bold',
                                        )}
                                    >
                                        {row.value}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card className="mt-5">
                <CardHeader
                    icon={BarChart3}
                    title="Actual Pemakaian per Bulan"
                    subtitle="6 bulan terakhir · Forecast hanya muncul bila bulan itu ada di Excel terakhir"
                />
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                            <tr>
                                <th className="px-4 py-2 text-left">Bulan</th>
                                <th className="px-4 py-2 text-right">Forecast (pcs)</th>
                                <th className="px-4 py-2 text-right">Actual (pcs)</th>
                                <th className="px-4 py-2 text-right">% Actual</th>
                                <th className="px-4 py-2 text-right">Tool Terpakai</th>
                                <th className="px-4 py-2 text-right">Jumlah Input</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {monthlyActuals.map((cell) => (
                                <tr key={cell.month} className={cn(cell.is_current && 'bg-sky-50/60')}>
                                    <td className="px-4 py-2 font-semibold whitespace-nowrap text-slate-700">
                                        {formatMonth(`${cell.month}-01`)}
                                        {cell.is_current && (
                                            <span className="ml-2 text-xs font-medium text-sky-700">berjalan</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums text-slate-500">
                                        {cell.forecast === null ? '—' : formatInt(cell.forecast)}
                                    </td>
                                    <td className="px-4 py-2 text-right font-semibold tabular-nums">
                                        {formatInt(cell.actual_qty)}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <PercentBadge cell={cell} />
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatInt(cell.tools_replaced)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums text-slate-500">
                                        {formatInt(cell.usage_inputs)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card className="mt-5">
                <CardHeader
                    icon={Gauge}
                    title="Riwayat Pemakaian Life Time"
                    subtitle="30 input terakhir · baris yang dibatalkan tetap tercatat"
                    actions={
                        <Button
                            as={Link}
                            href={buildUrl('/tool-usages', { tool_id: tool.id })}
                            variant="outline"
                            size="sm"
                        >
                            Semua riwayat
                        </Button>
                    }
                />
                {usageLogs.length === 0 ? (
                    <EmptyState
                        icon={Gauge}
                        title="Belum ada input pemakaian"
                        action={
                            <Button size="sm" onClick={() => setLifeAction('usage')}>
                                <Gauge className="size-4" /> Input Pemakaian
                            </Button>
                        }
                    />
                ) : (
                    <div className="max-h-[420px] overflow-auto">
                        <UsageLogTable
                            logs={usageLogs}
                            onCancel={(log) => setLifeAction({ kind: 'cancel', log })}
                            onCorrect={(log) => setLifeAction({ kind: 'correct', log })}
                        />
                    </div>
                )}
            </Card>

            <div className="mt-5 grid gap-5 xl:grid-cols-2">
                <Card>
                    <CardHeader
                        icon={History}
                        title="Histori Transaksi"
                        subtitle="50 transaksi terakhir"
                        actions={
                            <Button
                                as={Link}
                                href={buildUrl('/audit-log', { tool_id: tool.id })}
                                variant="outline"
                                size="sm"
                            >
                                Audit log
                            </Button>
                        }
                    />
                    {transactions.length === 0 ? (
                        <EmptyState icon={History} title="Belum ada transaksi" />
                    ) : (
                        <div className="max-h-[420px] overflow-auto">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                                    <tr>
                                        <th className="px-4 py-2 text-left">Waktu</th>
                                        <th className="px-4 py-2 text-left">Jenis</th>
                                        <th className="px-4 py-2 text-right">Qty</th>
                                        <th className="px-4 py-2 text-right">Stok</th>
                                        <th className="px-4 py-2 text-left">PIC / Ref</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {transactions.map((trx) => (
                                        <tr key={trx.id} title={trx.notes ?? undefined}>
                                            <td className="px-4 py-2 whitespace-nowrap text-slate-600">
                                                {trx.created_at_display}
                                            </td>
                                            <td className="px-4 py-2">
                                                <TransactionTypeBadge type={trx.type} title={trx.type_label} />
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-4 py-2 text-right font-bold tabular-nums',
                                                    trx.qty_change < 0 ? 'text-red-600' : 'text-emerald-700',
                                                )}
                                            >
                                                {formatSigned(trx.qty_change)}
                                            </td>
                                            <td className="px-4 py-2 text-right whitespace-nowrap tabular-nums">
                                                {formatInt(trx.stock_before)} → <b>{formatInt(trx.stock_after)}</b>
                                            </td>
                                            <td className="px-4 py-2">
                                                <p className="font-semibold">{trx.pic_name ?? '—'}</p>
                                                <p className="font-mono text-xs text-slate-500">{trx.reference_no}</p>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Card>
                    <CardHeader icon={ClipboardList} title="Laporan Kerusakan" subtitle="20 laporan terakhir" />
                    {damageReports.length === 0 ? (
                        <EmptyState icon={ClipboardList} title="Belum ada laporan kerusakan" />
                    ) : (
                        <div className="max-h-[420px] overflow-auto">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                                    <tr>
                                        <th className="px-4 py-2 text-left">No. / Waktu</th>
                                        <th className="px-4 py-2 text-left">Operator</th>
                                        <th className="px-4 py-2 text-left">Alasan / Tindakan</th>
                                        <th className="px-4 py-2 text-right">Qty</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {damageReports.map((report) => (
                                        <tr key={report.id}>
                                            <td className="px-4 py-2">
                                                <Link
                                                    href={`/damage-reports/${report.id}`}
                                                    className="font-mono text-xs font-bold text-blue-700 hover:underline"
                                                >
                                                    {report.report_no}
                                                </Link>
                                                <p className="text-xs text-slate-500">{report.reported_at_display}</p>
                                            </td>
                                            <td className="px-4 py-2">
                                                {report.operator_name}
                                                <p className="text-xs text-slate-500">Shift {report.shift}</p>
                                            </td>
                                            <td className="px-4 py-2">
                                                {report.damage_reason_label}
                                                <p className="text-xs text-slate-500">{report.action_label}</p>
                                            </td>
                                            <td className="px-4 py-2 text-right font-bold tabular-nums">
                                                {formatInt(report.qty_replaced)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>

            <Modal open={lifeAction !== null} onClose={closeLifeAction} title={lifeModalTitle} subtitle={tool.tools_name_yes}>
                {lifeAction === 'usage' && (
                    <UsageForm tools={[tool]} initialToolId={tool.id} onSuccess={closeLifeAction} />
                )}
                {lifeAction === 'reset' && <ResetLifeForm tool={tool} onSuccess={closeLifeAction} />}
                {lifeAction?.kind === 'cancel' && (
                    <CancelUsageForm key={lifeAction.log.id} log={lifeAction.log} onSuccess={closeLifeAction} />
                )}
                {lifeAction?.kind === 'correct' && (
                    <CorrectUsageForm
                        key={lifeAction.log.id}
                        log={{ ...lifeAction.log, tool: lifeAction.log.tool ?? { life_time: tool.life_time } }}
                        onSuccess={closeLifeAction}
                    />
                )}
            </Modal>

            <Modal
                open={transactionType !== null}
                onClose={closeModal}
                title={transactionType === 'OUT' ? 'Pengambilan Stok (OUT)' : 'Penerimaan Stok (IN)'}
                subtitle={tool.tools_name_yes}
            >
                {transactionType && (
                    <TransactionForm
                        key={transactionType}
                        tools={[tool]}
                        initialToolId={tool.id}
                        initialType={transactionType}
                        onSuccess={closeModal}
                    />
                )}
            </Modal>
        </AppLayout>
    );
}

function ActualRow({ label, cells, render }) {
    return (
        <tr className="bg-sky-50/40">
            <td className="px-4 py-2 font-semibold whitespace-nowrap text-sky-900">{label}</td>
            {Array.from({ length: 5 }, (_, index) => {
                const cell = cells[index];

                return (
                    <td key={index} className="px-4 py-2 text-right tabular-nums">
                        {cell ? render(cell) : '—'}
                    </td>
                );
            })}
        </tr>
    );
}

/** > 100% = actual melebihi forecast (perlu revisi forecast) · bulan berjalan masih bisa bertambah. */
function PercentBadge({ cell }) {
    if (cell.percent === null) {
        return <span className="text-slate-400">—</span>;
    }

    const over = cell.percent > 100;

    return (
        <span
            className={cn(
                'inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold tabular-nums',
                over ? 'bg-red-100 text-red-700' : 'bg-emerald-50 text-emerald-700',
            )}
            title={over ? 'Actual melebihi forecast' : cell.is_current ? 'Bulan berjalan, masih bisa bertambah' : undefined}
        >
            {formatInt(cell.percent)}%{over && ' ▲'}
        </span>
    );
}

function MonthRow({ label, values, format, highlightNegative = false }) {
    return (
        <tr>
            <td className="px-4 py-2 font-semibold whitespace-nowrap text-slate-700">{label}</td>
            {values.map((value, index) => (
                <td
                    key={index}
                    className={cn(
                        'px-4 py-2 text-right tabular-nums',
                        highlightNegative && value < 0 && 'font-bold text-red-600',
                    )}
                >
                    {format(value)}
                </td>
            ))}
        </tr>
    );
}
