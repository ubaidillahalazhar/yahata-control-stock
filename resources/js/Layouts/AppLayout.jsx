import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ClipboardList,
    Factory,
    FileSpreadsheet,
    FileWarning,
    Gauge,
    History,
    LayoutDashboard,
    Menu,
    QrCode,
    ScanLine,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import FlashMessages from '@/Components/FlashMessages';
import { cn } from '@/Components/ui';

const NAV_SECTIONS = [
    {
        title: 'Monitoring',
        items: [
            {
                href: '/',
                label: 'Dashboard Stok',
                icon: LayoutDashboard,
                isActive: (path) => path === '/' || path.startsWith('/tools'),
            },
            {
                href: '/audit-log',
                label: 'Audit Log Stok',
                icon: History,
                isActive: (path) => path.startsWith('/audit-log'),
            },
        ],
    },
    {
        title: 'Transaksi',
        items: [
            {
                href: '/transactions/create',
                label: 'Transaksi IN / OUT',
                icon: ArrowLeftRight,
                isActive: (path) => path.startsWith('/transactions'),
            },
            {
                href: '/tool-usages',
                label: 'Pemakaian Life Time (Day)',
                icon: Gauge,
                isActive: (path) => path.startsWith('/tool-usages'),
            },
            {
                href: '/damage-reports/create',
                label: 'Form Kerusakan (QR)',
                icon: ScanLine,
                isActive: (path) => path === '/damage-reports/create',
            },
            {
                href: '/damage-reports',
                label: 'Riwayat Kerusakan',
                icon: ClipboardList,
                isActive: (path) => path.startsWith('/damage-reports') && path !== '/damage-reports/create',
            },
        ],
    },
    {
        title: 'Master Data',
        items: [
            {
                href: '/sync',
                label: 'Sinkronisasi Excel',
                icon: FileSpreadsheet,
                isActive: (path) => path.startsWith('/sync'),
            },
            {
                href: '/import-issues',
                label: 'Excel Belum Lengkap',
                icon: FileWarning,
                isActive: (path) => path.startsWith('/import-issues'),
            },
            {
                href: '/qr-labels',
                label: 'Label QR Code',
                icon: QrCode,
                isActive: (path) => path.startsWith('/qr-labels'),
            },
        ],
    },
];

export default function AppLayout({ title, subtitle, actions, children }) {
    const { url, props } = usePage();
    const path = url.split('?')[0];
    const [navOpen, setNavOpen] = useState(false);
    const company = props.app?.company ?? 'PT. Yahata Fastener Indonesia';
    const department = props.app?.department ?? 'Production Department';

    useEffect(() => setNavOpen(false), [url]);

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900 print:bg-white">
            <Head title={title} />

            {/* Top bar mobile */}
            <div className="sticky top-0 z-40 flex h-14 items-center justify-between bg-slate-900 px-4 text-white lg:hidden print:hidden">
                <Brand compact />
                <button
                    type="button"
                    onClick={() => setNavOpen(true)}
                    className="rounded-md p-2 hover:bg-slate-800"
                    aria-label="Buka menu"
                >
                    <Menu className="size-5" />
                </button>
            </div>

            {navOpen && (
                <div
                    className="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"
                    onClick={() => setNavOpen(false)}
                    aria-hidden="true"
                />
            )}

            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-slate-900 text-slate-300 transition-transform duration-200 lg:translate-x-0 print:hidden',
                    navOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="flex h-16 items-center justify-between border-b border-slate-800 px-4">
                    <Brand />
                    <button
                        type="button"
                        onClick={() => setNavOpen(false)}
                        className="rounded-md p-1.5 hover:bg-slate-800 lg:hidden"
                        aria-label="Tutup menu"
                    >
                        <X className="size-5" />
                    </button>
                </div>

                <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                    {NAV_SECTIONS.map((section) => (
                        <div key={section.title}>
                            <p className="mb-2 px-3 text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                {section.title}
                            </p>
                            <ul className="space-y-1">
                                {section.items.map(({ href, label, icon: Icon, isActive }) => {
                                    const active = isActive(path);

                                    return (
                                        <li key={href}>
                                            <Link
                                                href={href}
                                                className={cn(
                                                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                                                    active
                                                        ? 'bg-blue-600 text-white shadow'
                                                        : 'hover:bg-slate-800 hover:text-white',
                                                )}
                                            >
                                                <Icon className="size-4.5 shrink-0" aria-hidden="true" />
                                                {label}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </nav>

                <div className="border-t border-slate-800 px-5 py-4 text-xs text-slate-500">
                    <p className="font-semibold text-slate-400">{company}</p>
                    <p>{department} · Control Stock Tool</p>
                </div>
            </aside>

            <div className="lg:pl-64 print:pl-0">
                <header className="border-b border-slate-200 bg-white print:hidden">
                    <div className="flex flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                        <div className="min-w-0">
                            <h1 className="truncate text-xl font-extrabold tracking-tight text-slate-900">{title}</h1>
                            {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                    </div>
                </header>

                <main className="px-4 py-6 sm:px-6 lg:px-8 print:p-0">
                    <FlashMessages />
                    {children}
                </main>
            </div>
        </div>
    );
}

function Brand({ compact = false }) {
    return (
        <Link href="/" className="flex items-center gap-2.5">
            <span className="flex size-9 items-center justify-center rounded-md bg-blue-600 text-white">
                <Factory className="size-5" aria-hidden="true" />
            </span>
            <span className="leading-tight">
                <span className="block text-sm font-extrabold tracking-wider text-white">YAHATA</span>
                {!compact && <span className="block text-[11px] font-medium text-slate-400">Control Stock Tool</span>}
            </span>
        </Link>
    );
}
