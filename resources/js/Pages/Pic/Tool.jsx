import { Head, Link } from '@inertiajs/react';
import { MapPin, PackageSearch, Search, TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';
import StatusBadge from '@/Components/StatusBadge';
import { Button, Card, EmptyState, Input, cn } from '@/Components/ui';
import PicLayout from '@/Layouts/PicLayout';
import { formatInt } from '@/lib/format';

export default function Tools({ tools = [], defaultMachine = '' }) {
    const [search, setSearch] = useState('');
    const [onlyMyMachine, setOnlyMyMachine] = useState(Boolean(defaultMachine));
    const [statusFilter, setStatusFilter] = useState('ALL');

    const filteredTools = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        return tools.filter((tool) => {
            if (onlyMyMachine && defaultMachine) {
                const machine = (tool.machine_name ?? '').toLowerCase();
                if (!machine.includes(defaultMachine.toLowerCase())) {
                    return false;
                }
            }

            if (statusFilter !== 'ALL' && tool.status_info !== statusFilter) {
                return false;
            }

            if (!keyword) {
                return true;
            }

            return [tool.tools_name_yes, tool.tools_name, tool.machine_name, tool.rack_location]
                .filter(Boolean)
                .some((field) => String(field).toLowerCase().includes(keyword));
        });
    }, [tools, search, onlyMyMachine, defaultMachine, statusFilter]);

    return (
        <PicLayout showMenuButton>
            <Head title="Cek Stok & QR Tooling" />

            <div className="mb-5">
                <h1 className="text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">
                    Cek Stok &amp; Lokasi Rak Tooling
                </h1>
                <p className="text-xs text-slate-500 sm:text-sm">
                    Pantau sisa stok gudang, batas minimum, dan lokasi rak sebelum melakukan penggantian part.
                </p>
            </div>

            {/* Filter & Pencarian */}
            <Card className="mb-5 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari Tools Name YES, nama tooling, mesin, atau rak..."
                            className="pl-9"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {defaultMachine && (
                            <button
                                type="button"
                                onClick={() => setOnlyMyMachine((prev) => !prev)}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-bold transition',
                                    onlyMyMachine
                                        ? 'border-blue-600 bg-blue-50 text-blue-700'
                                        : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50',
                                )}
                            >
                                <MapPin className="size-3.5" />
                                {onlyMyMachine ? `Mesin: ${defaultMachine}` : 'Semua Mesin'}
                            </button>
                        )}

                        {['ALL', 'ORDER', 'SECURE'].map((status) => (
                            <button
                                key={status}
                                type="button"
                                onClick={() => setStatusFilter(status)}
                                className={cn(
                                    'rounded-lg border px-3 py-2 text-xs font-bold transition',
                                    statusFilter === status
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50',
                                )}
                            >
                                {status === 'ALL' ? 'Semua Status' : status}
                            </button>
                        ))}
                    </div>
                </div>
            </Card>

            {/* Daftar Kartu Tooling */}
            {filteredTools.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={PackageSearch}
                        title="Item tooling tidak ditemukan"
                        description="Coba ubah kata kunci pencarian atau matikan filter mesin."
                    />
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    {filteredTools.map((tool) => (
                        <Card key={tool.id} className="flex flex-col justify-between p-4">
                            <div>
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-mono text-sm font-extrabold text-slate-900">
                                            {tool.tools_name_yes}
                                        </p>
                                        <p className="text-xs font-medium text-slate-600">{tool.tools_name}</p>
                                    </div>
                                    <StatusBadge status={tool.status_info} />
                                </div>

                                <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <span>
                                        Mesin: <b className="text-slate-700">{tool.machine_name || '—'}</b>
                                    </span>
                                    <span>
                                        Rak: <b className="text-slate-700">{tool.rack_location || '—'}</b>
                                    </span>
                                    {tool.step && (
                                        <span>
                                            Step: <b className="text-slate-700">{tool.step}</b>
                                        </span>
                                    )}
                                </div>

                                <div className="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-slate-50 p-2.5 text-center">
                                    <div>
                                        <p className="text-[10px] font-semibold text-slate-500 uppercase">Stok Saat Ini</p>
                                        <p
                                            className={cn(
                                                'text-base font-extrabold tabular-nums',
                                                tool.status_info === 'ORDER' ? 'text-red-600' : 'text-slate-900',
                                            )}
                                        >
                                            {formatInt(tool.current_stock)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-semibold text-slate-500 uppercase">Min Stock</p>
                                        <p className="text-base font-extrabold text-slate-700 tabular-nums">
                                            {formatInt(tool.min_stock_e)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-semibold text-slate-500 uppercase">Life Time</p>
                                        <p className="text-base font-extrabold text-slate-700 tabular-nums">
                                            {formatInt(tool.life_time)}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-3.5 flex justify-end border-t border-slate-100 pt-3">
                                <Button
                                    as={Link}
                                    href={`/damage-reports/create?tool_id=${tool.id}`}
                                    size="xs"
                                    variant="outline"
                                >
                                    <TriangleAlert className="size-3.5 text-red-600" /> Lapor Kerusakan
                                </Button>
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </PicLayout>
    );
}