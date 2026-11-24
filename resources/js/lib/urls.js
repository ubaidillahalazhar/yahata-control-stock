export function cleanParams(params = {}) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
}

export function buildUrl(path, params = {}) {
    const query = new URLSearchParams(cleanParams(params)).toString();

    return query ? `${path}?${query}` : path;
}

const LOCAL_HOST = /^(https?:\/\/)?(localhost|127\.0\.0\.1|\[::1\])(:\d+)?/i;

/**
 * Base URL untuk QR Code. Jika admin membuka aplikasi via localhost, pakai APP_URL
 * (mis. http://192.168.1.10:8000) agar QR bisa dibuka HP operator di jaringan pabrik.
 */
export function resolveQrBase(appUrl) {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    if (origin && !LOCAL_HOST.test(origin)) {
        return { base: origin, isLocal: false };
    }

    if (appUrl && !LOCAL_HOST.test(appUrl)) {
        return { base: appUrl.replace(/\/$/, ''), isLocal: false };
    }

    return { base: origin || appUrl || '', isLocal: true };
}

export function damageFormUrl(base, params) {
    return `${base}${buildUrl('/damage-reports/create', params)}`;
}
