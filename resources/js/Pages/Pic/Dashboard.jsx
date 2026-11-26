import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, ClipboardList, Gauge, QrCode, TriangleAlert } from 'lucide-react';
import PicLayout from '@/Layouts/PicLayout';

const MENU_CARDS = [
    {
        key: 'damage-create',
        title: 'Form Kerusakan',
        subtitle: 'Laporkan tooling rusak & penggantian part baru atau regrind',
        href: '/damage-reports/create',
        icon: TriangleAlert,
        accent: 'border-red-200 hover:border-red-400 hover:bg-red-50/40',
        iconWrap: 'bg-red-100 text-red-600 group-hover:bg-red-600 group-hover:text-white',
        badge: 'Input Cepat',
    },
    {
        key: 'damage-history',
        title: 'Riwayat',
        subtitle: 'Pantau daftar laporan kerusakan & cetak ulang slip transaksi',
        href: '/damage-reports',
        icon: ClipboardList,
        accent: 'border-blue-200 hover:border-blue-400 hover:bg-blue-50/40',
        iconWrap: 'bg-blue-100 text-blue-600 group-hover:bg-blue-600 group-hover:text-white',
        badge: 'Log Laporan',
    },
    {
        key: 'count-lifetime',
        title: 'Count Lifetime',
        subtitle: 'Catat & monitoring pemakaian harian life time tooling (Day)',
        href: '/tool-usages',
        icon: Gauge,
        accent: 'border-amber-200 hover:border-amber-400 hover:bg-amber-50/40',
        iconWrap: 'bg-amber-100 text-amber-600 group-hover:bg-amber-600 group-hover:text-white',
        badge: 'Life Time',
    },
    {
        key: 'stock-qr',
        title: 'Cek Stok & QR Tooling',
        subtitle: 'Cari ketersediaan stok part, lokasi rak, & detail QR mesin',
        href: '/pic/tools',
        icon: QrCode,
        accent: 'border-emerald-200 hover:border-emerald-400 hover:bg-emerald-50/40',
        iconWrap: 'bg-emerald-100 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white',
        badge: 'Info Stok',
    },
];

export default function Dashboard() {
    const user = usePage().props.auth?.user ?? {};

    return (
        <PicLayout>
            <Head title="Menu Utama PIC" />

            <div className="mb-6">
                <h2 className="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                    Menu Operasional Tooling
                </h2>
                <p className="mt-1 text-xs text-slate-500 sm:text-sm">
                    Pilih menu di bawah untuk pelaporan kerusakan, pemakaian life time, atau pengecekan stok di{' '}
                    <span className="font-semibold text-slate-700">{user.machine_name || 'Process/Section'}</span>.
                </p>
            </div>

            {/* Portal Menu 2x2 Grid */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5">
                {MENU_CARDS.map((item) => {
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.key}
                            href={item.href}
                            className={`group relative flex min-h-42 flex-col justify-between rounded-2xl border-2 bg-white p-5 shadow-sm transition-all duration-150 active:scale-[0.99] sm:min-h-47.5 sm:p-6 ${item.accent}`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div
                                    className={`flex size-13 items-center justify-center rounded-2xl transition-colors ${item.iconWrap}`}
                                >
                                    <Icon className="size-7" />
                                </div>

                                <div className="flex items-center gap-1.5">
                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold tracking-wide text-slate-600 uppercase">
                                        {item.badge}
                                    </span>
                                    <span className="flex size-8 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-slate-900">
                                        <ArrowUpRight className="size-4" />
                                    </span>
                                </div>
                            </div>

                            <div className="mt-5">
                                <h3 className="text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">
                                    {item.title}
                                </h3>
                                <p className="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">
                                    {item.subtitle}
                                </p>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </PicLayout>
    );
}