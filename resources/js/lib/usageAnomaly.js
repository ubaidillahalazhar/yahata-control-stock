import { formatInt } from '@/lib/format';

/**
 * Aturan "angka janggal" yang bisa dihitung di browser (sama dengan UsageAnomalyService::LIFE_PERCENT).
 * Aturan rata-rata harian hanya dicek server; pesannya kembali sebagai errors.confirm_unusual.
 */
export const UNUSUAL_LIFE_PERCENT = 10;

export function unusualReason(qty, lifeTime) {
    if (lifeTime > 0 && qty * 100 > UNUSUAL_LIFE_PERCENT * lifeTime) {
        return `Jumlah ${formatInt(qty)} pcs lebih dari ${UNUSUAL_LIFE_PERCENT}% life time tool (${formatInt(
            Math.floor((lifeTime * UNUSUAL_LIFE_PERCENT) / 100),
        )} pcs). Pastikan tidak salah ketik (mis. kelebihan angka 0).`;
    }

    return null;
}
