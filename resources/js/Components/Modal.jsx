import { X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/Components/ui';

export default function Modal({ open, onClose, title, subtitle, maxWidth = 'max-w-lg', children }) {
    const closeRef = useRef(onClose);
    closeRef.current = onClose;

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                closeRef.current?.();
            }
        };

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    if (!open) {
        return null;
    }

    return createPortal(
        <div
            className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
        >
            <div className="absolute inset-0 bg-slate-950/60" onClick={() => closeRef.current?.()} aria-hidden="true" />
            <div
                className={cn(
                    'relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-xl bg-white shadow-2xl sm:rounded-xl',
                    maxWidth,
                )}
            >
                <div className="flex items-start justify-between gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <div className="min-w-0">
                        <h2 className="text-base font-bold text-slate-900">{title}</h2>
                        {subtitle && <p className="mt-0.5 truncate font-mono text-xs text-slate-500">{subtitle}</p>}
                    </div>
                    <button
                        type="button"
                        onClick={() => closeRef.current?.()}
                        className="rounded-md p-1 text-slate-500 hover:bg-slate-200 hover:text-slate-900"
                        aria-label="Tutup"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <div className="overflow-y-auto px-5 py-5">{children}</div>
            </div>
        </div>,
        document.body,
    );
}
