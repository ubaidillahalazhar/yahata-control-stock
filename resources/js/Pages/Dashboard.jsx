import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowDownToLine,
    ArrowLeftRight,
    ArrowUp,
    ArrowUpFromLine,
    Boxes,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    Download,
    Eye,
    FileSpreadsheet,
    FileWarning,
    Gauge,
    Hourglass,
    PackageX,
    Search,
    ShieldCheck,
    TriangleAlert,
    Wrench,
    X,
} from 'lucide-react';
import { useCallback, useDeferredValue, useMemo, useState } from 'react';
import KpiCard from '@/Components/KpiCard';
import LifeBar from '@/Components/LifeBar';
import Modal from '@/Components/Modal';
import StatusBadge from '@/Components/StatusBadge';
import TransactionForm from '@/Components/TransactionForm';
import UsageForm from '@/Components/UsageForm';
import { Button, Card, EmptyState, Input, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatInt, formatNumber, monthLabels, percent } from '@/lib/format';
import { LIFE_TONES, lifeOf, priorityOf } from '@/lib/life';
import { buildUrl } from '@/lib/urls';

const PAGE_SIZE = 50;

const STATUS_FILTERS = [
    { value: 'ALL', label: 'Semua' },
    { value: 'ORDER', label: 'ORDER' },
    { value: 'SECURE', label: 'SECURE' },
];

const LIFE_FILTERS = [
    { value: 'ALL', label: 'Semua' },
    { value: 'green', label: LIFE_TONES.green.range },
    { value: 'yellow', label: LIFE_TONES.yellow.range },
    { value: 'red', label: LIFE_TONES.red.range },
];

const LIFE_FILTER_ACTIVE = {
    ALL: 'bg-slate-800 text-white',
    green: 'bg-emerald-600 text-white',
    yellow: 'bg-amber-400 text-slate-900',
    red: 'bg-red-600 text-white',
};

// Need (A), SS B, SS C, SS D ada di halaman detail tool & export Excel.
const COLUMNS = [
    { key: 'tools_name_yes', label: 'Tools Name YES', excel: 'H', align: 'left' },
    { key: 'step', label: 'Step', excel: 'D', align: 'left' },
    { key: 'tools_name', label: 'Tools Name', excel: 'E', align: 'left' },
    { key: 'rack_location', label: 'Rak', excel: 'I', align: 'left' },
    { key: 'forecast_avg', label: 'Forecast Avg', excel: 'R', align: 'right' },
    { key: 'min_stock_e', label: 'Min (E)', excel: 'AG', align: 'right' },
    { key: 'max_total_stand_by', label: 'Max StandBy', excel: 'AH', align: 'right' },
    { key: 'life_usage_percent', label: 'Life Time', excel: 'L', align: 'left', title: 'Pemakaian life time sejak tool terakhir diganti' },
    { key: 'life_status', label: 'Status Life', excel: '≥80%', align: 'center', title: 'ORDER bila pemakaian life ≥ 80%' },
    { key: 'current_stock', label: 'Stok', excel: 'K', align: 'right' },
    { key: 'status_info', label: 'Status Stok', excel: 'AI', align: 'center' },
];

function compareBy(key) {
    return (a, b) => {
        const left = a[key];
        const right = b[key];

        if (typeof left === 'number' && typeof right === 'number') {
            return left - right;
        }

        return String(left ?? '').localeCompare(String(right ?? ''), 'id', { numeric: true, sensitivity: 'base' });
    };
}

export default function Dashboard({ tools, racks, forecastMonths, lastUpdatedAt, importIssues, heldToolIds }) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('ALL');
    const [lifeLevel, setLifeLevel] = useState('ALL');
    const [emptyOnly, setEmptyOnly] = useState(false);
    const [sort, setSort] = useState({ key: null, dir: 'asc' });
    const [page, setPage] = useState(1);
    const [transaction, setTransaction] = useState(null);
    const deferredQuery = useDeferredValue(query);
    const heldIds = useMemo(() => new Set(heldToolIds), [heldToolIds]);

    const indexed = useMemo(
        () =>
            tools.map((tool) => ({
                tool,
                haystack: [
                    tool.tools_name_yes,
                    tool.tools_name,
                    tool.machine_name,
                    tool.part_no,
                    tool.yes_name,
                    tool.rack_location,
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase(),
            })),
        [tools],
    );

    const kpi = useMemo(
        () =>
            tools.reduce(
                (acc, tool) => {
                    acc.total += 1;
                    acc[tool.status_info === 'ORDER' ? 'order' : 'secure'] += 1;
                    acc.empty += tool.current_stock <= 0 ? 1 : 0;
                    acc.shortage += tool.shortage_qty;
                    acc.lifeRed += tool.life_level === 'red' ? 1 : 0;
                    acc.lifeYellow += tool.life_level === 'yellow' ? 1 : 0;
                    acc.urgent += tool.status_info === 'ORDER' && tool.life_status === 'ORDER' ? 1 : 0;

                    return acc;
                },
                { total: 0, order: 0, secure: 0, empty: 0, shortage: 0, lifeRed: 0, lifeYellow: 0, urgent: 0 },
            ),
        [tools],
    );

    const filtered = useMemo(() => {
        const tokens = deferredQuery.trim().toLowerCase().split(/\s+/).filter(Boolean);

        return indexed
            .filter(
                ({ tool, haystack }) =>
                    (status === 'ALL' || tool.status_info === status) &&
                    (lifeLevel === 'ALL' || tool.life_level === lifeLevel) &&
                    (!emptyOnly || tool.current_stock <= 0) &&
                    tokens.every((token) => haystack.includes(token)),
            )
            .map(({ tool }) => tool);
    }, [indexed, deferredQuery, status, lifeLevel, emptyOnly]);

    const sorted = useMemo(() => {
        if (!sort.key) {
            // Default: paling mendesak di atas (Life ORDER + Stok ORDER → Stok ORDER → Life ORDER), lalu urutan Excel.
            return [...filtered].sort((a, b) => priorityOf(b) - priorityOf(a));
        }

        const direction = sort.dir === 'asc' ? 1 : -1;
        const compare = compareBy(sort.key);

        return [...filtered].sort((a, b) => compare(a, b) * direction);
    }, [filtered, sort]);

    const totalPages = Math.max(1, Math.ceil(sorted.length / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);
    const visible = useMemo(
        () => sorted.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE),
        [sorted, currentPage],
    );

    const months = useMemo(() => monthLabels(forecastMonths), [forecastMonths]);
    const hasFilter = query !== '' || status !== 'ALL' || lifeLevel !== 'ALL' || emptyOnly;
    const selectedTool = transaction ? tools.find((tool) => tool.id === transaction.toolId) : null;
    const closeTransaction = useCallback(() => setTransaction(null), []);

    const withPageReset = (setter) => (value) => {
        setter(value);
        setPage(1);
    };
    const changeQuery = withPageReset(setQuery);
    const changeStatus = withPageReset(setStatus);
    const changeLifeLevel = withPageReset(setLifeLevel);
    const changeEmptyOnly = withPageReset(setEmptyOnly);

    const resetFilters = () => {
        setQuery('');
        setStatus('ALL');
        setLifeLevel('ALL');
        setEmptyOnly(false);
        setPage(1);
    };

    const toggleSort = (key) =>
        setSort((prev) => {
            if (prev.key !== key) return { key, dir: 'asc' };
            if (prev.dir === 'asc') return { key, dir: 'desc' };

            return { key: null, dir: 'asc' };
        });

    return (
        <AppLayout
            title="Live Stock Control Dashboard"
            subtitle={`Forecast ${months[0]} – ${months[4]} (rata-rata bulan ke-1 s.d. ke-5)${lastUpdatedAt ? ` · Master diperbarui ${lastUpdatedAt}` : ''}`}
            actions={
                <>
                    <Button as="a" href="/sync/control-stock-export" variant="outline" size="sm">
                        <Download className="size-4" /> Export Excel
                    </Button>
                    <Button as={Link} href="/tool-usages" variant="outline" size="sm">
                        <Gauge className="size-4" /> Input Pemakaian
                    </Button>
                    <Button as={Link} href="/transactions/create" size="sm">
                        <ArrowLeftRight className="size-4" /> Transaksi IN / OUT
                    </Button>
                </>
            }
        >
            <section className="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6">
                <KpiCard
                    icon={Boxes}
                    tone="blue"
                    label="Total Item Tooling"
                    value={formatInt(kpi.total)}
                    hint={`${racks.length} lokasi rak`}
                    active={hasFilter === false}
                    onClick={resetFilters}
                />
                <KpiCard
                    icon={TriangleAlert}
                    tone="red"
                    label="Stok ORDER"
                    value={formatInt(kpi.order)}
                    hint={`${percent(kpi.order, kpi.total)}% item · kurang ${formatInt(kpi.shortage)} pcs ke Min`}
                    active={status === 'ORDER'}
                    onClick={() => changeStatus(status === 'ORDER' ? 'ALL' : 'ORDER')}
                />
                <KpiCard
                    icon={ShieldCheck}
                    tone="emerald"
                    label="Stok SECURE"
                    value={formatInt(kpi.secure)}
                    hint={`${percent(kpi.secure, kpi.total)}% item`}
                    active={status === 'SECURE'}
                    onClick={() => changeStatus(status === 'SECURE' ? 'ALL' : 'SECURE')}
                />
                <KpiCard
                    icon={FileWarning}
                    tone={importIssues.total > 0 ? 'red' : 'emerald'}
                    label="Excel Belum Lengkap"
                    value={formatInt(importIssues.total)}
                    hint={
                        importIssues.total > 0
                            ? `${formatInt(importIssues.new)} tool belum masuk · ${formatInt(importIssues.update)} update ditahan`
                            : 'Semua baris import terakhir lengkap'
                    }
                    onClick={() => router.visit('/import-issues')}
                />
                <KpiCard
                    icon={Gauge}
                    tone="red"
                    label={`Life ${LIFE_TONES.red.range} (ORDER)`}
                    value={formatInt(kpi.lifeRed)}
                    hint={`${formatInt(kpi.urgent)} item juga Stok ORDER`}
                    active={lifeLevel === 'red'}
                    onClick={() => changeLifeLevel(lifeLevel === 'red' ? 'ALL' : 'red')}
                />
                <KpiCard
                    icon={Hourglass}
                    tone="amber"
                    label={`Life ${LIFE_TONES.yellow.range}`}
                    value={formatInt(kpi.lifeYellow)}
                    hint="Perlu diperhatikan"
                    active={lifeLevel === 'yellow'}
                    onClick={() => changeLifeLevel(lifeLevel === 'yellow' ? 'ALL' : 'yellow')}
                />
            </section>

            <Card className="mt-5">
                <div className="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            type="search"
                            value={query}
                            onChange={(event) => changeQuery(event.target.value)}
                            placeholder="Cari Tools Name YES, Tools Name, Machine Name, Part No, Rak…"
                            className="pl-9"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div
                            className="inline-flex rounded-md border border-slate-300 bg-slate-50 p-0.5"
                            role="group"
                            aria-label="Filter status stok"
                        >
                            <span className="px-2 py-1.5 text-[11px] font-bold text-slate-500 uppercase">Stok</span>
                            {STATUS_FILTERS.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => changeStatus(option.value)}
                                    aria-pressed={status === option.value}
                                    className={cn(
                                        'rounded px-3 py-1.5 text-xs font-bold transition',
                                        status === option.value
                                            ? option.value === 'ORDER'
                                                ? 'bg-red-600 text-white'
                                                : option.value === 'SECURE'
                                                  ? 'bg-emerald-600 text-white'
                                                  : 'bg-slate-800 text-white'
                                            : 'text-slate-600 hover:bg-white',
                                    )}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>

                        <div
                            className="inline-flex rounded-md border border-slate-300 bg-slate-50 p-0.5"
                            role="group"
                            aria-label="Filter life time"
                        >
                            <span className="px-2 py-1.5 text-[11px] font-bold text-slate-500 uppercase">Life</span>
                            {LIFE_FILTERS.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => changeLifeLevel(option.value)}
                                    aria-pressed={lifeLevel === option.value}
                                    className={cn(
                                        'rounded px-3 py-1.5 text-xs font-bold whitespace-nowrap transition',
                                        lifeLevel === option.value
                                            ? LIFE_FILTER_ACTIVE[option.value]
                                            : 'text-slate-600 hover:bg-white',
                                    )}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>

                        <label className="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">
                            <input
                                type="checkbox"
                                checked={emptyOnly}
                                onChange={(event) => changeEmptyOnly(event.target.checked)}
                                className="size-4 rounded border-slate-300 text-blue-600"
                            />
                            Stok 0 ({formatInt(kpi.empty)})
                        </label>

                        {hasFilter && (
                            <Button variant="ghost" size="sm" onClick={resetFilters}>
                                <X className="size-4" /> Reset
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex items-center justify-between px-4 py-2 text-xs text-slate-500">
                    <span>
                        Menampilkan <b className="text-slate-800">{formatInt(sorted.length)}</b> dari{' '}
                        {formatInt(tools.length)} item
                    </span>
                    <span className="hidden sm:inline">
                        Urutan awal: paling mendesak di atas · klik judul kolom untuk mengurutkan · huruf kecil = kolom Excel
                    </span>
                </div>

                {tools.length === 0 ? (
                    <EmptyState
                        icon={FileSpreadsheet}
                        title="Belum ada data tooling"
                        description='Import file master "yahata tools.xlsx" untuk mulai memantau stok.'
                        action={
                            <Button as={Link} href="/sync">
                                Buka Sinkronisasi Excel
                            </Button>
                        }
                    />
                ) : sorted.length === 0 ? (
                    <EmptyState
                        icon={Search}
                        title="Tidak ada item yang cocok"
                        description="Ubah kata kunci atau reset filter."
                    />
                ) : (
                    <div className="max-h-[68vh] overflow-auto border-t border-slate-200">
                        <table className="w-full min-w-[1280px] border-collapse text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-800 text-white">
                                <tr>
                                    {COLUMNS.map((column) => (
                                        <th
                                            key={column.key}
                                            scope="col"
                                            title={column.title}
                                            className={cn(
                                                'px-3 py-2.5 text-[11px] font-bold tracking-wide whitespace-nowrap uppercase',
                                                column.align === 'right' && 'text-right',
                                                column.align === 'center' && 'text-center',
                                                column.align === 'left' && 'text-left',
                                            )}
                                        >
                                            <button
                                                type="button"
                                                onClick={() => toggleSort(column.key)}
                                                className={cn(
                                                    'inline-flex items-center gap-1 hover:text-blue-200',
                                                    column.align === 'right' && 'flex-row-reverse',
                                                )}
                                            >
                                                <span>
                                                    {column.label}
                                                    <span className="ml-1 font-mono text-[9px] font-normal text-slate-400 normal-case">
                                                        {column.excel}
                                                    </span>
                                                </span>
                                                <SortIcon active={sort.key === column.key} dir={sort.dir} />
                                            </button>
                                        </th>
                                    ))}
                                    <th
                                        scope="col"
                                        className="px-3 py-2.5 text-center text-[11px] font-bold tracking-wide uppercase"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((tool) => (
                                    <ToolRow
                                        key={tool.id}
                                        tool={tool}
                                        held={heldIds.has(tool.id)}
                                        onTransaction={(type) => setTransaction({ toolId: tool.id, type })}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {totalPages > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm">
                        <span className="text-slate-600">
                            Halaman {currentPage} dari {totalPages}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={currentPage <= 1}
                                onClick={() => setPage(currentPage - 1)}
                            >
                                <ChevronLeft className="size-4" /> Sebelumnya
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={currentPage >= totalPages}
                                onClick={() => setPage(currentPage + 1)}
                            >
                                Berikutnya <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </Card>

            <Modal
                open={Boolean(transaction)}
                onClose={closeTransaction}
                title={
                    transaction?.type === 'USAGE'
                        ? 'Input Pemakaian Life Time'
                        : transaction?.type === 'OUT'
                          ? 'Pengambilan Stok (OUT)'
                          : 'Penerimaan Stok (IN)'
                }
                subtitle={selectedTool?.tools_name_yes}
            >
                {transaction?.type === 'USAGE' && selectedTool && (
                    <UsageForm
                        key={`usage-${transaction.toolId}`}
                        tools={[selectedTool]}
                        initialToolId={transaction.toolId}
                        onSuccess={closeTransaction}
                    />
                )}
                {transaction && transaction.type !== 'USAGE' && (
                    <TransactionForm
                        key={`${transaction.toolId}-${transaction.type}`}
                        tools={tools}
                        initialToolId={transaction.toolId}
                        initialType={transaction.type}
                        onSuccess={closeTransaction}
                    />
                )}
            </Modal>
        </AppLayout>
    );
}

function SortIcon({ active, dir }) {
    if (!active) {
        return <ChevronsUpDown className="size-3 text-slate-500" aria-hidden="true" />;
    }

    return dir === 'asc' ? (
        <ArrowUp className="size-3" aria-hidden="true" />
    ) : (
        <ArrowDown className="size-3" aria-hidden="true" />
    );
}

function ToolRow({ tool, held, onTransaction }) {
    const isOrder = tool.status_info === 'ORDER';
    const numberCell = 'px-3 py-2 text-right tabular-nums whitespace-nowrap';

    return (
        <tr
            className={cn(
                'border-b border-slate-100 align-top transition-colors hover:bg-blue-50/70',
                isOrder && 'bg-red-50/50',
            )}
        >
            <td className={cn('border-l-4 px-3 py-2', isOrder ? 'border-l-red-600' : 'border-l-transparent')}>
                <Link
                    href={`/tools/${tool.id}`}
                    className="font-mono text-[13px] font-bold text-slate-900 hover:text-blue-700 hover:underline"
                >
                    {tool.tools_name_yes}
                </Link>
                {held && (
                    <Link
                        href="/import-issues?status=UPDATE_HELD"
                        className="mt-0.5 flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:underline"
                        title="Baris tool ini di Excel terakhir belum lengkap — data yang tampil adalah data lama"
                    >
                        <FileWarning className="size-3.5" aria-hidden="true" /> Update Excel ditahan
                    </Link>
                )}
                <p className="mt-0.5 text-xs text-slate-500">
                    {tool.machine_name} · {tool.part_no}
                </p>
            </td>
            <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">{tool.step}</td>
            <td className="px-3 py-2 whitespace-nowrap">{tool.tools_name}</td>
            <td className="px-3 py-2">
                <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-slate-700">
                    {tool.rack_location ?? '—'}
                </span>
            </td>
            <td className={numberCell}>{formatNumber(tool.forecast_avg, { max: 0 })}</td>
            <td className={cn(numberCell, 'font-bold')}>{formatInt(tool.min_stock_e)}</td>
            <td className={numberCell}>{formatInt(tool.max_total_stand_by)}</td>
            <td className="px-3 py-2">
                <LifeBar life={lifeOf(tool)} lifeTime={tool.life_time} />
            </td>
            <td className="px-3 py-2 text-center">
                <StatusBadge status={tool.life_status} />
            </td>
            <td className={cn(numberCell, 'text-base font-extrabold', isOrder ? 'text-red-700' : 'text-slate-900')}>
                <span className="inline-flex items-center gap-1">
                    {tool.is_empty && <PackageX className="size-4 text-red-600" aria-label="Stok kosong" />}
                    {formatInt(tool.current_stock)}
                </span>
                {tool.shortage_qty > 0 && (
                    <p className="text-[11px] font-semibold text-red-600">kurang {formatInt(tool.shortage_qty)}</p>
                )}
            </td>
            <td className="px-3 py-2 text-center">
                <StatusBadge status={tool.status_info} />
            </td>
            <td className="px-3 py-2">
                <div className="flex items-center justify-center gap-1">
                    <Button size="xs" onClick={() => onTransaction('USAGE')} title="Input pemakaian life time">
                        <Gauge className="size-3.5" />
                    </Button>
                    <Button variant="success" size="xs" onClick={() => onTransaction('IN')} title="Barang masuk (IN)">
                        <ArrowDownToLine className="size-3.5" /> IN
                    </Button>
                    <Button
                        variant="dark"
                        size="xs"
                        onClick={() => onTransaction('OUT')}
                        title="Pengambilan (OUT)"
                        disabled={tool.is_empty}
                    >
                        <ArrowUpFromLine className="size-3.5" /> OUT
                    </Button>
                    <Button
                        as={Link}
                        href={buildUrl('/damage-reports/create', { tool_id: tool.id })}
                        variant="outline-danger"
                        size="xs"
                        title="Lapor kerusakan / penggantian"
                    >
                        <Wrench className="size-3.5" />
                    </Button>
                    <Button as={Link} href={`/tools/${tool.id}`} variant="ghost" size="xs" title="Detail perhitungan">
                        <Eye className="size-3.5" />
                    </Button>
                </div>
            </td>
        </tr>
    );
}
