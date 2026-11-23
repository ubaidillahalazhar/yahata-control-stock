import { ChevronsUpDown, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import StatusBadge from '@/Components/StatusBadge';
import { cn } from '@/Components/ui';
import { formatInt } from '@/lib/format';

const MAX_RESULTS = 60;

function haystackOf(tool) {
    return [
        tool.tools_name_yes,
        tool.tools_name,
        tool.machine_name,
        tool.rack_location,
        tool.part_no,
        tool.yes_name,
        tool.step,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
}

/**
 * Pencarian item tooling (Tools Name YES / mesin / rak / part no) — nyaman dipakai di HP.
 */
export default function ToolCombobox({
    tools,
    value,
    onChange,
    invalid = false,
    size = 'md',
    placeholder = 'Cari Tools Name YES, mesin, rak, part no…',
}) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [highlight, setHighlight] = useState(0);
    const containerRef = useRef(null);
    const inputRef = useRef(null);

    const selected = useMemo(() => tools.find((tool) => tool.id === Number(value)) ?? null, [tools, value]);
    const indexed = useMemo(() => tools.map((tool) => ({ tool, haystack: haystackOf(tool) })), [tools]);

    const results = useMemo(() => {
        const tokens = query.toLowerCase().split(/\s+/).filter(Boolean);
        const matches = tokens.length
            ? indexed.filter(({ haystack }) => tokens.every((token) => haystack.includes(token)))
            : indexed;

        return matches.slice(0, MAX_RESULTS).map(({ tool }) => tool);
    }, [indexed, query]);

    useEffect(() => setHighlight(0), [query]);

    useEffect(() => {
        const onPointerDown = (event) => {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, []);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        }
    }, [open]);

    const choose = (tool) => {
        onChange(tool.id);
        setQuery('');
        setOpen(false);
    };

    const onKeyDown = (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setHighlight((index) => Math.min(index + 1, results.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlight((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            if (open && results[highlight]) {
                event.preventDefault();
                choose(results[highlight]);
            }
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    };

    const height = size === 'lg' ? 'min-h-14' : 'min-h-11';

    return (
        <div ref={containerRef} className="relative">
            {selected && !open ? (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className={cn(
                        'flex w-full items-center gap-3 rounded-md border bg-white px-3 py-2 text-left shadow-xs hover:border-blue-400',
                        height,
                        invalid ? 'border-red-500' : 'border-slate-300',
                    )}
                >
                    <span className="min-w-0 flex-1">
                        <span className="block truncate font-mono text-sm font-bold text-slate-900">
                            {selected.tools_name_yes}
                        </span>
                        <span className="block truncate text-xs text-slate-500">
                            {selected.tools_name} · Step {selected.step ?? '—'} · Rak {selected.rack_location ?? '—'}
                        </span>
                    </span>
                    <StatusBadge status={selected.status_info} />
                    <ChevronsUpDown className="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                </button>
            ) : (
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        ref={inputRef}
                        type="search"
                        value={query}
                        placeholder={placeholder}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setOpen(true);
                        }}
                        onFocus={() => setOpen(true)}
                        onKeyDown={onKeyDown}
                        autoComplete="off"
                        className={cn(
                            'block w-full rounded-md border bg-white pr-10 pl-9 text-sm shadow-xs placeholder:text-slate-400',
                            'focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none',
                            height,
                            invalid ? 'border-red-500' : 'border-slate-300',
                        )}
                    />
                    {selected && (
                        <button
                            type="button"
                            onClick={() => {
                                setQuery('');
                                setOpen(false);
                            }}
                            className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-700"
                            aria-label="Batal ganti item"
                        >
                            <X className="size-4" />
                        </button>
                    )}
                </div>
            )}

            {open && (
                <ul className="absolute z-40 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-xl">
                    {results.length === 0 && (
                        <li className="px-3 py-4 text-center text-sm text-slate-500">Item tidak ditemukan</li>
                    )}
                    {results.map((tool, index) => (
                        <li key={tool.id}>
                            <button
                                type="button"
                                onMouseDown={(event) => event.preventDefault()}
                                onMouseEnter={() => setHighlight(index)}
                                onClick={() => choose(tool)}
                                className={cn(
                                    'flex w-full items-center gap-3 px-3 py-2 text-left',
                                    index === highlight ? 'bg-blue-50' : 'hover:bg-slate-50',
                                    tool.id === selected?.id && 'border-l-4 border-blue-600',
                                )}
                            >
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-mono text-[13px] font-bold text-slate-900">
                                        {tool.tools_name_yes}
                                    </span>
                                    <span className="block truncate text-xs text-slate-500">
                                        {tool.tools_name} · {tool.machine_name} · Rak {tool.rack_location ?? '—'}
                                    </span>
                                </span>
                                <span className="text-right">
                                    <span
                                        className={cn(
                                            'block text-sm font-bold tabular-nums',
                                            tool.status_info === 'ORDER' ? 'text-red-600' : 'text-slate-900',
                                        )}
                                    >
                                        {formatInt(tool.current_stock)}
                                    </span>
                                    <span className="block text-[10px] text-slate-400 uppercase">stok</span>
                                </span>
                            </button>
                        </li>
                    ))}
                    {results.length === MAX_RESULTS && (
                        <li className="px-3 py-2 text-center text-xs text-slate-400">
                            Ketik lebih spesifik untuk mempersempit hasil…
                        </li>
                    )}
                </ul>
            )}
        </div>
    );
}
