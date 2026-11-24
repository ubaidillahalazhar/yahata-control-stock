import { usePage } from '@inertiajs/react';
import { CircleCheck, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/Components/ui';

export default function FlashMessages({ className }) {
    const { flash } = usePage().props;
    const [dismissed, setDismissed] = useState({});

    // Objek flash baru di setiap kunjungan → tampilkan kembali pesan terbaru.
    useEffect(() => setDismissed({}), [flash]);

    const messages = [
        { key: 'success', text: flash?.success, tone: 'success' },
        { key: 'error', text: flash?.error, tone: 'error' },
    ].filter((message) => message.text && !dismissed[message.key]);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className={cn('mb-5 space-y-2 print:hidden', className)}>
            {messages.map(({ key, text, tone }) => {
                const Icon = tone === 'success' ? CircleCheck : TriangleAlert;

                return (
                    <div
                        key={key}
                        role="status"
                        className={cn(
                            'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm font-medium shadow-sm',
                            tone === 'success'
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                                : 'border-red-300 bg-red-50 text-red-900',
                        )}
                    >
                        <Icon
                            className={cn(
                                'mt-0.5 size-5 shrink-0',
                                tone === 'success' ? 'text-emerald-600' : 'text-red-600',
                            )}
                        />
                        <p className="flex-1">{text}</p>
                        <button
                            type="button"
                            onClick={() => setDismissed((prev) => ({ ...prev, [key]: true }))}
                            className="rounded p-0.5 opacity-60 hover:opacity-100"
                            aria-label="Tutup pesan"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
