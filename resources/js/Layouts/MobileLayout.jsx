import { Head, Link, router, usePage } from '@inertiajs/react';
import { ClipboardList, LayoutDashboard, LogOut } from 'lucide-react';
import FlashMessages from '@/Components/FlashMessages';
import logoYahata from '@/assets/logo-yahata.jpeg';

const HEADER_LINK =
    'flex items-center gap-1 rounded-md px-2 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800';

/**
 * Layout ringan untuk form yang dibuka operator via scan QR (HP / PC stasiun ruang tooling).
 */
export default function MobileLayout({ title, children }) {
    const { props } = usePage();
    const company = props.app?.company ?? 'PT. Yahata Fastener Indonesia';
    const user = props.auth?.user ?? null;

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900">
            <Head title={title} />
            <header className="sticky top-0 z-30 bg-slate-900 text-white shadow-md">
                <div className="mx-auto flex max-w-xl items-center gap-3 px-4 py-3">
                    <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white p-1 shadow-xs">
                        <img
                            src={logoYahata}
                            alt="Logo PT. Yahata Fastener Indonesia"
                            className="h-full w-full object-contain"
                        />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-[11px] font-bold tracking-wider text-blue-300 uppercase">
                            {user && !user.is_admin ? user.name : company}
                        </p>
                        <h1 className="truncate text-base font-extrabold">{title}</h1>
                    </div>
                    {user?.is_admin ? (
                        <Link href="/" className={HEADER_LINK}>
                            <LayoutDashboard className="size-4" /> Dashboard
                        </Link>
                    ) : (
                        <>
                            <Link href="/damage-reports" className={HEADER_LINK} aria-label="Riwayat laporan saya">
                                <ClipboardList className="size-4" /> <span className="hidden sm:inline">Riwayat</span>
                            </Link>
                            <button
                                type="button"
                                onClick={() => router.post('/logout')}
                                className={`${HEADER_LINK} hover:text-red-300`}
                            >
                                <LogOut className="size-4" /> Keluar
                            </button>
                        </>
                    )}
                </div>
            </header>
            <main className="mx-auto max-w-xl px-4 pt-5 pb-28">
                <FlashMessages />
                {children}
            </main>
        </div>
    );
}
