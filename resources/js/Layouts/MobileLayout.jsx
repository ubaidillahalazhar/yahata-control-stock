import { Head, Link, usePage } from '@inertiajs/react';
import { Factory, LayoutDashboard } from 'lucide-react';
import FlashMessages from '@/Components/FlashMessages';

/**
 * Layout ringan untuk halaman yang dibuka operator via scan QR di HP.
 */
export default function MobileLayout({ title, children }) {
    const { props } = usePage();
    const company = props.app?.company ?? 'PT. Yahata Fastener Indonesia';

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900">
            <Head title={title} />
            <header className="sticky top-0 z-30 bg-slate-900 text-white shadow-md">
                <div className="mx-auto flex max-w-xl items-center gap-3 px-4 py-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-blue-600">
                        <Factory className="size-5" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-[11px] font-bold tracking-wider text-blue-300 uppercase">
                            {company}
                        </p>
                        <h1 className="truncate text-base font-extrabold">{title}</h1>
                    </div>
                    <Link
                        href="/"
                        className="flex items-center gap-1 rounded-md px-2 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                    >
                        <LayoutDashboard className="size-4" /> Dashboard
                    </Link>
                </div>
            </header>
            <main className="mx-auto max-w-xl px-4 pt-5 pb-28">
                <FlashMessages />
                {children}
            </main>
        </div>
    );
}
