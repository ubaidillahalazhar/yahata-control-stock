const formatters = new Map();

function formatter(min, max) {
    const key = `${min}:${max}`;

    if (!formatters.has(key)) {
        formatters.set(key, new Intl.NumberFormat('id-ID', { minimumFractionDigits: min, maximumFractionDigits: max }));
    }

    return formatters.get(key);
}

/** Format angka gaya Indonesia (1.000.000 / 0,39). Nilai kosong → "—". */
export function formatNumber(value, { min = 0, max = 2 } = {}) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
        return '—';
    }

    return formatter(min, max).format(Number(value));
}

export const formatInt = (value) => formatNumber(value, { min: 0, max: 0 });

export const formatDecimal = (value, digits = 2) => formatNumber(value, { min: digits, max: digits });

export function formatSigned(value) {
    const number = Number(value);

    return number > 0 ? `+${formatInt(number)}` : formatInt(number);
}

export function formatMonth(isoDate, fallback = '—') {
    if (!isoDate) {
        return fallback;
    }

    const date = new Date(`${isoDate}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? fallback
        : date.toLocaleDateString('id-ID', { month: 'short', year: '2-digit' });
}

/** Label 5 bulan forecast dari baris 2 Excel; fallback "Bln 1..5". */
export function monthLabels(months) {
    return Array.from({ length: 5 }, (_, index) => formatMonth(months?.[index], `Bln ${index + 1}`));
}

export function percent(part, total) {
    return total > 0 ? Math.round((part / total) * 100) : 0;
}

/** Tanggal lokal format YYYY-MM-DD (untuk input type="date"). */
export function toDateInput(date) {
    return date.toLocaleDateString('sv-SE');
}

export function daysAgo(days) {
    const date = new Date();
    date.setDate(date.getDate() - days);

    return toDateInput(date);
}
