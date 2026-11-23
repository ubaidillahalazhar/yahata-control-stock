import {
    ArrowDownToLine,
    ArrowUpFromLine,
    CircleCheck,
    CircleX,
    ClipboardCheck,
    ShieldCheck,
    TriangleAlert,
    Wrench,
} from 'lucide-react';
import { cn } from '@/Components/ui';

export default function StatusBadge({ status, size = 'sm' }) {
    const isOrder = status === 'ORDER';
    const Icon = isOrder ? TriangleAlert : ShieldCheck;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full font-bold tracking-wide ring-1 ring-inset',
                size === 'lg' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-[11px]',
                isOrder ? 'bg-red-600 text-white ring-red-700' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/40',
            )}
        >
            <Icon className={size === 'lg' ? 'size-4' : 'size-3'} aria-hidden="true" />
            {status}
        </span>
    );
}

export function CheckBadge({ value }) {
    const ok = value === 'OK';
    const Icon = ok ? CircleCheck : CircleX;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-bold',
                ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700',
            )}
        >
            <Icon className="size-3" aria-hidden="true" />
            {value}
        </span>
    );
}

const TRANSACTION_TYPES = {
    IN: { label: 'IN', icon: ArrowDownToLine, className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/30' },
    OUT: { label: 'OUT', icon: ArrowUpFromLine, className: 'bg-slate-100 text-slate-700 ring-slate-500/30' },
    OPNAME_ADJUST: { label: 'OPNAME', icon: ClipboardCheck, className: 'bg-blue-50 text-blue-700 ring-blue-600/30' },
    DAMAGE_REPLACEMENT: { label: 'DAMAGE', icon: Wrench, className: 'bg-red-50 text-red-700 ring-red-600/30' },
};

export function TransactionTypeBadge({ type, title }) {
    const config = TRANSACTION_TYPES[type] ?? TRANSACTION_TYPES.OUT;
    const Icon = config.icon;

    return (
        <span
            title={title ?? type}
            className={cn(
                'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-bold ring-1 ring-inset whitespace-nowrap',
                config.className,
            )}
        >
            <Icon className="size-3" aria-hidden="true" />
            {config.label}
        </span>
    );
}
