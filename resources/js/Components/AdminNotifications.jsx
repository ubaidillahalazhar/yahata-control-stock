import { router } from '@inertiajs/react';
import { Bell, CheckCheck, ClipboardList, Clock, Info, TriangleAlert, UserPlus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/Components/ui';

const KIND_STYLES = {
    registration: { icon: UserPlus, tone: 'bg-amber-100 text-amber-700' },
    damage_report: { icon: ClipboardList, tone: 'bg-red-100 text-red-700' },
    review: { icon: TriangleAlert, tone: 'bg-amber-100 text-amber-700' },
    count_overdue: { icon: Clock, tone: 'bg-red-100 text-red-700' },
    info: { icon: Info, tone: 'bg-slate-100 text-slate-600' },
};

const TOAST_LIFETIME_MS = 8000;

function KindIcon({ kind, className }) {
    const { icon: Icon, tone } = KIND_STYLES[kind] ?? KIND_STYLES.info;

    return (
        <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-full', tone, className)}>
            <Icon className="size-4" aria-hidden="true" />
        </span>
    );
}

/**
 * Lonceng notifikasi admin + dropdown. Data dari useAdminFeed (polling).
 */
export function NotificationBell({ feed, onOpenItem, onReadAll, dark = false }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const unread = feed.unread_count;

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const close = (event) => {
            if (ref.current && !ref.current.contains(event.target)) {
                setOpen(false);
            }
        };
        const onKey = (event) => event.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', onKey);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <div ref={ref} className="relative print:hidden">
            <button
                type="button"
                onClick={() => setOpen((prev) => !prev)}
                aria-label={unread > 0 ? `${unread} notifikasi belum dibaca` : 'Notifikasi'}
                aria-expanded={open}
                className={cn(
                    'relative rounded-md p-2 transition',
                    dark ? 'text-slate-200 hover:bg-slate-800' : 'text-slate-600 hover:bg-slate-100',
                )}
            >
                <Bell className="size-5" />
                {unread > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex min-w-4.5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-4.5 font-extrabold text-white">
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-xl">
                    <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <p className="text-sm font-extrabold">Notifikasi</p>
                        {unread > 0 && (
                            <button
                                type="button"
                                onClick={onReadAll}
                                className="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:underline"
                            >
                                <CheckCheck className="size-3.5" /> Tandai semua dibaca
                            </button>
                        )}
                    </div>
                    {feed.items.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-500">Belum ada notifikasi.</p>
                    ) : (
                        <ul className="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                            {feed.items.map((item) => (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setOpen(false);
                                            onOpenItem(item);
                                        }}
                                        className={cn(
                                            'flex w-full gap-3 px-4 py-3 text-left transition hover:bg-slate-50',
                                            !item.read && 'bg-blue-50/60',
                                        )}
                                    >
                                        <KindIcon kind={item.kind} />
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center gap-1.5">
                                                <span className="truncate text-sm font-bold">{item.title}</span>
                                                {!item.read && (
                                                    <span className="size-2 shrink-0 rounded-full bg-blue-600" aria-label="belum dibaca" />
                                                )}
                                            </span>
                                            <span className="block text-xs text-slate-600">{item.body}</span>
                                            <span className="mt-0.5 block text-[11px] text-slate-400">{item.created_at_human}</span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Toast pojok kanan bawah untuk notifikasi yang baru masuk.
 */
export function NotificationToasts({ toasts, onOpenItem, onDismiss }) {
    return (
        <div
            className="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2 print:hidden"
            aria-live="polite"
        >
            {toasts.map((toast) => (
                <Toast key={toast.id} toast={toast} onOpen={() => onOpenItem(toast)} onDismiss={() => onDismiss(toast.id)} />
            ))}
        </div>
    );
}

function Toast({ toast, onOpen, onDismiss }) {
    // Ref: render ulang (mis. tiap polling) tidak boleh me-reset hitung mundur toast.
    const dismissRef = useRef(onDismiss);
    dismissRef.current = onDismiss;

    useEffect(() => {
        const timer = setTimeout(() => dismissRef.current(), TOAST_LIFETIME_MS);
        return () => clearTimeout(timer);
    }, []);

    return (
        <div className="pointer-events-auto flex gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
            <KindIcon kind={toast.kind} />
            <button type="button" onClick={onOpen} className="min-w-0 flex-1 text-left">
                <span className="block text-sm font-bold text-slate-900">{toast.title}</span>
                <span className="block text-xs text-slate-600">{toast.body}</span>
            </button>
            <button
                type="button"
                onClick={onDismiss}
                aria-label="Tutup"
                className="self-start rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}

/** Buka halaman tujuan notifikasi dan tandai dibaca. */
export function openNotification(item, markRead) {
    markRead(item.id);
    if (item.url) {
        router.visit(item.url);
    }
}
