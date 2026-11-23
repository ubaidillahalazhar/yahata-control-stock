import { usePage } from '@inertiajs/react';
import { Layers, Printer, QrCode, Search, TriangleAlert } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useMemo, useState } from 'react';
import { Button, Card, EmptyState, Input, Select, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { damageFormUrl, resolveQrBase } from '@/lib/urls';

const MODES = [
    { value: 'item', label: 'Per Item Tooling', icon: QrCode },
    { value: 'group', label: 'Per Mesin & Rak', icon: Layers },
];

export default function Index({ tools, groups }) {
    const { app } = usePage().props;
    const [mode, setMode] = useState('item');
    const [rack, setRack] = useState('ALL');
    const [machine, setMachine] = useState('ALL');
    const [query, setQuery] = useState('');
    const { base, isLocal } = useMemo(() => resolveQrBase(app?.url), [app?.url]);

    const racks = useMemo(() => [...new Set(tools.map((tool) => tool.rack_location).filter(Boolean))].sort(), [tools]);
    const machines = useMemo(
        () => [...new Set(tools.map((tool) => tool.machine_name).filter(Boolean))].sort(),
        [tools],
    );

    const filteredTools = useMemo(() => {
        const tokens = query.toLowerCase().split(/\s+/).filter(Boolean);

        return tools.filter((tool) => {
            const haystack = [tool.tools_name_yes, tool.tools_name, tool.part_no, tool.step]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return (
                (rack === 'ALL' || tool.rack_location === rack) &&
                (machine === 'ALL' || tool.machine_name === machine) &&
                tokens.every((token) => haystack.includes(token))
            );
        });
    }, [tools, rack, machine, query]);

    const filteredGroups = useMemo(
        () =>
            groups.filter(
                (group) =>
                    (rack === 'ALL' || group.rack_location === rack) &&
                    (machine === 'ALL' || group.machine_name === machine),
            ),
        [groups, rack, machine],
    );

    const count = mode === 'item' ? filteredTools.length : filteredGroups.length;

    return (
        <AppLayout
            title="Label QR Code"
            subtitle="Tempel di mesin / rak. Scan → form laporan kerusakan langsung terisi itemnya."
            actions={
                <Button size="sm" onClick={() => window.print()} disabled={count === 0}>
                    <Printer className="size-4" /> Cetak {count} Label
                </Button>
            }
        >
            <Card className="mb-5 print:hidden">
                <div className="flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
                    <div className="inline-flex rounded-md border border-slate-300 bg-slate-50 p-0.5">
                        {MODES.map(({ value, label, icon: Icon }) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setMode(value)}
                                aria-pressed={mode === value}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-xs font-bold',
                                    mode === value ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-white',
                                )}
                            >
                                <Icon className="size-4" /> {label}
                            </button>
                        ))}
                    </div>
                    <Select
                        value={machine}
                        onChange={(event) => setMachine(event.target.value)}
                        className="h-9 lg:w-56"
                    >
                        <option value="ALL">Semua Mesin</option>
                        {machines.map((item) => (
                            <option key={item} value={item}>
                                {item}
                            </option>
                        ))}
                    </Select>
                    <Select value={rack} onChange={(event) => setRack(event.target.value)} className="h-9 lg:w-40">
                        <option value="ALL">Semua Rak</option>
                        {racks.map((item) => (
                            <option key={item} value={item}>
                                Rak {item}
                            </option>
                        ))}
                    </Select>
                    {mode === 'item' && (
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                type="search"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Cari Tools Name YES / part no…"
                                className="h-9 pl-9"
                            />
                        </div>
                    )}
                </div>
                <div
                    className={cn(
                        'border-t px-4 py-2 text-xs',
                        isLocal ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-slate-200 text-slate-500',
                    )}
                >
                    {isLocal && <TriangleAlert className="mr-1 inline size-4" />}
                    Base URL QR: <span className="font-mono font-bold">{base}</span>
                    {isLocal &&
                        ' — masih localhost. Set APP_URL ke IP server (mis. http://192.168.1.10:8000) agar bisa dibuka dari HP operator.'}
                </div>
            </Card>

            {count === 0 ? (
                <Card>
                    <EmptyState
                        icon={QrCode}
                        title="Tidak ada label"
                        description="Ubah filter atau import master tooling terlebih dulu."
                    />
                </Card>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 print:grid-cols-2 print:gap-2">
                    {mode === 'item'
                        ? filteredTools.map((tool) => (
                              <Label
                                  key={tool.id}
                                  url={damageFormUrl(base, { tool_id: tool.id })}
                                  caption="Scan untuk lapor kerusakan"
                                  title={tool.tools_name_yes}
                                  lines={[
                                      `${tool.tools_name} · Step ${tool.step ?? '—'}`,
                                      `${tool.machine_name ?? '—'} · Rak ${tool.rack_location ?? '—'}`,
                                      tool.part_no,
                                  ]}
                              />
                          ))
                        : filteredGroups.map((group) => (
                              <Label
                                  key={`${group.machine_name}|${group.rack_location}`}
                                  url={damageFormUrl(base, { machine: group.machine_name, rack: group.rack_location })}
                                  caption="Scan untuk lapor kerusakan · pilih item"
                                  title={group.machine_name ?? 'Tanpa mesin'}
                                  lines={[`Rak ${group.rack_location ?? '—'}`, `${group.count} item tooling`]}
                                  large
                              />
                          ))}
                </div>
            )}
        </AppLayout>
    );
}

function Label({ url, caption, title, lines, large = false }) {
    const { props } = usePage();

    return (
        <div className="flex break-inside-avoid items-center gap-3 rounded-md border-2 border-slate-900 bg-white p-3 print:rounded-none">
            <QRCodeSVG value={url} size={large ? 132 : 108} level="M" className="shrink-0" />
            <div className="min-w-0">
                <p className="text-[9px] font-black tracking-widest text-slate-500 uppercase">
                    {props.app?.company ?? 'PT. Yahata Fastener Indonesia'}
                </p>
                <p className="mb-1 text-[10px] font-bold text-blue-700 uppercase">{caption}</p>
                <p
                    className={cn(
                        'font-mono leading-tight font-extrabold break-words text-slate-900',
                        large ? 'text-lg' : 'text-sm',
                    )}
                >
                    {title}
                </p>
                {lines.filter(Boolean).map((line) => (
                    <p key={line} className="text-xs text-slate-700">
                        {line}
                    </p>
                ))}
            </div>
        </div>
    );
}
