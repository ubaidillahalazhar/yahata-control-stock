import { formatInt, formatSigned } from '@/lib/format';

/** Harus sama dengan ShotCountService::CONFIRM_VARIANCE_PERCENT. */
export const CONFIRM_VARIANCE_PERCENT = 10;

/** Sama dengan ShotCountService::needsConfirmation(). */
export function needsConfirmation(variance, system) {
    return Math.abs(variance) * 100 > CONFIRM_VARIANCE_PERCENT * Math.max(system, 1);
}

/**
 * Bandingkan angka counter mesin dengan life sistem.
 * Counter mesin di-nol-kan setiap ganti tool → angka counter = shot sejak tool dipasang.
 *
 * @returns {null | {counter: number, system: number, variance: number, confirm: boolean, tone: 'ok'|'up'|'down'}}
 */
export function compareCounter(counterInput, system) {
    if (counterInput === '' || counterInput === null || counterInput === undefined) {
        return null;
    }

    const counter = Math.max(0, Number.parseInt(counterInput, 10) || 0);
    const variance = counter - system;

    return {
        counter,
        system,
        variance,
        confirm: needsConfirmation(variance, system),
        tone: variance === 0 ? 'ok' : variance > 0 ? 'up' : 'down',
    };
}

/** Pesan konfirmasi saat selisih di atas batas. */
export function confirmMessage({ counter, system, variance }) {
    return [
        `Angka counter ${formatInt(counter)} berbeda ${formatSigned(variance)} pcs dari sistem (${formatInt(system)}).`,
        `Selisih lebih dari ${CONFIRM_VARIANCE_PERCENT}%. Pastikan angka counter sudah dibaca dengan benar.`,
        '',
        'Lanjut simpan?',
    ].join('\n');
}
