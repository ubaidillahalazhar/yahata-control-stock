import { cn } from '@/Components/ui';

const TONES = {
    blue: {
        accent: 'border-l-blue-600',
        icon: 'bg-blue-50 text-blue-700',
        value: 'text-slate-900',
        ring: 'ring-blue-600',
    },
    red: { accent: 'border-l-red-600', icon: 'bg-red-50 text-red-700', value: 'text-red-700', ring: 'ring-red-600' },
    emerald: {
        accent: 'border-l-emerald-600',
        icon: 'bg-emerald-50 text-emerald-700',
        value: 'text-emerald-700',
        ring: 'ring-emerald-600',
    },
    slate: {
        accent: 'border-l-slate-700',
        icon: 'bg-slate-100 text-slate-700',
        value: 'text-slate-900',
        ring: 'ring-slate-700',
    },
};

export default function KpiCard({ icon: Icon, label, value, hint, tone = 'blue', active = false, onClick }) {
    const colors = TONES[tone] ?? TONES.blue;
    const Component = onClick ? 'button' : 'div';

    return (
        <Component
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            aria-pressed={onClick ? active : undefined}
            className={cn(
                'flex w-full items-start justify-between gap-3 rounded-lg border border-l-4 border-slate-200 bg-white p-4 text-left shadow-sm transition',
                colors.accent,
                onClick && 'hover:-translate-y-0.5 hover:shadow-md',
                active && cn('ring-2', colors.ring),
            )}
        >
            <div className="min-w-0">
                <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">{label}</p>
                <p className={cn('mt-1 text-3xl font-extrabold tabular-nums', colors.value)}>{value}</p>
                {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
            </div>
            {Icon && (
                <div className={cn('flex size-10 shrink-0 items-center justify-center rounded-md', colors.icon)}>
                    <Icon className="size-5" aria-hidden="true" />
                </div>
            )}
        </Component>
    );
}
