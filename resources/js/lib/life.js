/**
 * Batas warna life time — TETAP, sama persis dengan App\Services\ToolLifeService.
 * Hijau < 50% · Kuning 50–79% · Merah ≥ 80% (= Status Life ORDER).
 */
export const LIFE_YELLOW_FROM = 50;
export const LIFE_RED_FROM = 80;

export const LIFE_TONES = {
    green: {
        label: 'Hijau',
        range: `< ${LIFE_YELLOW_FROM}%`,
        bar: 'bg-emerald-500',
        text: 'text-emerald-700',
        soft: 'bg-emerald-50 text-emerald-700 ring-emerald-600/40',
        dot: 'bg-emerald-500',
    },
    yellow: {
        label: 'Kuning',
        range: `${LIFE_YELLOW_FROM}–${LIFE_RED_FROM - 1}%`,
        bar: 'bg-amber-400',
        text: 'text-amber-700',
        soft: 'bg-amber-50 text-amber-800 ring-amber-500/50',
        dot: 'bg-amber-400',
    },
    red: {
        label: 'Merah',
        range: `≥ ${LIFE_RED_FROM}%`,
        bar: 'bg-red-600',
        text: 'text-red-700',
        soft: 'bg-red-50 text-red-700 ring-red-600/40',
        dot: 'bg-red-600',
    },
};

/** Versi JS dari ToolLifeService::summary() — dipakai untuk preview di form. */
export function lifeSummary(lifeTime, lifeUsed) {
    const used = Math.max(0, Number(lifeUsed) || 0);
    const total = Number(lifeTime) || 0;

    if (total <= 0) {
        return { used, remaining: 0, over: 0, percent: 0, level: 'green', status: 'SECURE' };
    }

    const level = used * 100 >= LIFE_RED_FROM * total ? 'red' : used * 100 >= LIFE_YELLOW_FROM * total ? 'yellow' : 'green';

    return {
        used,
        remaining: Math.max(0, total - used),
        over: Math.max(0, used - total),
        percent: Math.floor((used * 1000) / total) / 10,
        level,
        status: level === 'red' ? 'ORDER' : 'SECURE',
    };
}

/** Ringkasan dari payload ToolingResource. */
export function lifeOf(tool) {
    return {
        used: tool.life_used,
        remaining: tool.life_remaining,
        over: tool.life_over,
        percent: tool.life_usage_percent,
        level: tool.life_level,
        status: tool.life_status,
    };
}

/**
 * Prioritas dashboard: Life ORDER + Stok ORDER paling atas, lalu Stok ORDER, lalu Life ORDER.
 */
export function priorityOf(tool) {
    return (tool.status_info === 'ORDER' ? 2 : 0) + (tool.life_status === 'ORDER' ? 1 : 0);
}
