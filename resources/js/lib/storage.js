/**
 * localStorage aman (mis. mode private / kebijakan browser) — hanya untuk kenyamanan,
 * seperti mengingat nama PIC / operator di HP.
 */
export function readStorage(key, fallback = '') {
    try {
        return window.localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
}

export function writeStorage(key, value) {
    try {
        window.localStorage.setItem(key, String(value ?? ''));
    } catch {
        // abaikan
    }
}

export const STORAGE_KEYS = {
    picName: 'yahata.pic_name',
    operatorName: 'yahata.operator_name',
    shift: 'yahata.shift',
};
