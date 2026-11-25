import { useCallback, useEffect, useRef, useState } from 'react';

/** Jeda polling saat tab terlihat / tersembunyi. */
const VISIBLE_INTERVAL_MS = 10_000;
const HIDDEN_INTERVAL_MS = 60_000;

/*
 * Disimpan di level modul (bukan state komponen) karena AppLayout dipasang ulang setiap pindah
 * halaman: notifikasi yang sudah pernah terlihat tidak boleh muncul lagi sebagai toast.
 */
const seenIds = new Set();
let primed = false;

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function request(url, method = 'GET') {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(method !== 'GET' ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
        },
    });

    // Sesi habis / akun dinonaktifkan → server mengarahkan ke halaman login (bukan JSON).
    if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
        throw Object.assign(new Error('session'), { sessionLost: true });
    }
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

function beep() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
        osc.connect(gain).connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.35);
        osc.onended = () => ctx.close();
    } catch {
        // Browser memblokir audio sebelum ada interaksi user — abaikan.
    }
}

/**
 * Notifikasi admin via polling. `enabled` = false untuk non-admin (tidak ada request sama sekali).
 */
export function useAdminFeed(enabled, initialPending = 0) {
    const [feed, setFeed] = useState({ unread_count: 0, pending_accounts: initialPending, items: [] });
    const [toasts, setToasts] = useState([]);
    const stopped = useRef(false);

    const load = useCallback(async () => {
        if (stopped.current) {
            return;
        }

        try {
            const data = await request('/notifications/feed');
            const fresh = data.items.filter((item) => !item.read && !seenIds.has(item.id));
            data.items.forEach((item) => seenIds.add(item.id));

            // Muatan pertama hanya mencatat yang sudah ada; toast hanya untuk yang benar-benar baru.
            if (primed && fresh.length > 0) {
                setToasts((prev) => [...fresh.slice(0, 3), ...prev].slice(0, 3));
                beep();
            }
            primed = true;
            setFeed(data);
        } catch (error) {
            if (error.sessionLost) {
                stopped.current = true;
                window.location.reload();
            }
            // Gangguan jaringan sesaat → coba lagi di putaran berikutnya.
        }
    }, []);

    useEffect(() => {
        if (!enabled) {
            return undefined;
        }

        stopped.current = false;
        let timer;
        const schedule = () => {
            clearTimeout(timer);
            timer = setTimeout(
                async () => {
                    await load();
                    schedule();
                },
                document.visibilityState === 'visible' ? VISIBLE_INTERVAL_MS : HIDDEN_INTERVAL_MS,
            );
        };
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                load();
                schedule();
            }
        };

        load();
        schedule();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            stopped.current = true;
            clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [enabled, load]);

    const dismissToast = useCallback((id) => setToasts((prev) => prev.filter((toast) => toast.id !== id)), []);

    const markRead = useCallback(
        async (id) => {
            setFeed((prev) => ({
                ...prev,
                unread_count: Math.max(0, prev.unread_count - (prev.items.find((i) => i.id === id && !i.read) ? 1 : 0)),
                items: prev.items.map((item) => (item.id === id ? { ...item, read: true } : item)),
            }));
            dismissToast(id);
            await request(`/notifications/${id}/read`, 'POST').catch(() => load());
        },
        [dismissToast, load],
    );

    const markAllRead = useCallback(async () => {
        setFeed((prev) => ({ ...prev, unread_count: 0, items: prev.items.map((item) => ({ ...item, read: true })) }));
        setToasts([]);
        await request('/notifications/read-all', 'POST').catch(() => load());
    }, [load]);

    return { feed, toasts, dismissToast, markRead, markAllRead };
}
