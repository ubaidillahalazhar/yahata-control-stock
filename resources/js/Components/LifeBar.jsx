import { cn } from '@/Components/ui';
import { formatInt, formatNumber } from '@/lib/format';
import { LIFE_TONES } from '@/lib/life';

export function formatPercent(value) {
    return `${formatNumber(value, { min: 0, max: 1 })}%`;
}

/**
 * Progress bar pemakaian life time (hijau < 50% · kuning 50–79% · merah ≥ 80%).
 *
 * @param {{ life: { used: number, remaining: number, over: number, percent: number, level: string }, lifeTime: number, size?: 'sm' | 'lg' }} props
 */
export default function LifeBar({ life, lifeTime, size = 'sm', className }) {
    const tone = LIFE_TONES[life.level] ?? LIFE_TONES.green;
    const width = Math.min(100, Math.max(0, life.percent));
    const large = size === 'lg';

    return (
        <div className={cn(large ? 'space-y-2' : 'min-w-[190px] space-y-1', className)}>
            <div className="flex items-center gap-2">
                <div
                    className={cn('flex-1 overflow-hidden rounded-full bg-slate-200', large ? 'h-4' : 'h-2')}
                    role="progressbar"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={width}
                    aria-label={`Pemakaian life time ${formatPercent(life.percent)}`}
                >
                    <div className={cn('h-full rounded-full transition-[width]', tone.bar)} style={{ width: `${width}%` }} />
                </div>
                <span
                    className={cn(
                        'shrink-0 text-right font-extrabold tabular-nums',
                        tone.text,
                        large ? 'w-20 text-2xl' : 'w-12 text-xs',
                    )}
                >
                    {formatPercent(life.percent)}
                </span>
            </div>
            {large ? null : (
                <p className="text-[11px] leading-tight whitespace-nowrap text-slate-500 tabular-nums">
                    terpakai <b className="text-slate-700">{formatInt(life.used)}</b> · sisa{' '}
                    <b className={life.remaining === 0 ? 'text-red-700' : 'text-slate-700'}>{formatInt(life.remaining)}</b>
                    <span className="block">dari {formatInt(lifeTime)}</span>
                </p>
            )}
        </div>
    );
}

export function LifeLevelDot({ level, className }) {
    const tone = LIFE_TONES[level] ?? LIFE_TONES.green;

    return <span className={cn('inline-block size-2.5 rounded-full', tone.dot, className)} aria-hidden="true" />;
}
