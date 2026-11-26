import { Link, router, usePage } from '@inertiajs/react';
import { ClipboardList, LayoutGrid, LogOut } from 'lucide-react';

export default function PicLayout({ children, showMenuButton = false }) {
    const { auth, flash } = usePage().props;
    const user = auth?.user ?? {};

    const operatorName = user.name || 'Operator PIC';
    const machineLine = user.machine_name || 'Line Produksi';

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <div className="min-h-screen bg-slate-100/80 text-slate-900">
            {/* HEADER PIC SESUAI SKETSA */}
            <header className="sticky top-0 z-30 border-b border-slate-800 bg-[#0f172a] text-white shadow-md">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3.5 sm:px-6">
                    {/* Kiri: Logo YFI + Nama Operator (Atas) & Mesin/Line Aktif (Bawah) */}
                    <Link href="/pic/dashboard" className="flex min-w-0 items-center gap-3.5 group">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white px-1.5 text-xs font-black tracking-tighter text-slate-900 shadow-sm transition group-hover:scale-105">
                            YFI
                        </div>

                        <div className="min-w-0">
                            <p className="truncate text-[11px] font-bold tracking-wider text-slate-300 uppercase sm:text-xs">
                                {operatorName}
                            </p>
                            <div className="mt-0.5 flex items-center gap-1.5">
                                <span
                                    className="inline-flex size-2 shrink-0 rounded-full bg-emerald-400 ring-4 ring-emerald-400/20"
                                    title="Status Mesin Aktif"
                                />
                                <h1 className="truncate text-sm font-extrabold tracking-tight text-white sm:text-base">
                                    {machineLine}
                                </h1>
                            </div>
                        </div>
                    </Link>

                    {/* Kanan: Navigasi Menu (opsional di sub-halaman), Riwayat & Keluar */}
                    <div className="flex shrink-0 items-center gap-2 sm:gap-2.5">
                        {showMenuButton && (
                            <Link
                                href="/pic/dashboard"
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-700/80 bg-slate-800/80 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:border-slate-600 hover:bg-slate-700"
                            >
                                <LayoutGrid className="size-4 text-emerald-400" />
                                <span className="hidden sm:inline">Menu</span>
                            </Link>
                        )}

                        <Link
                            href="/damage-reports"
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-700/80 bg-slate-800/80 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:border-slate-600 hover:bg-slate-700"
                        >
                            <ClipboardList className="size-4 text-blue-400" />
                            <span>Riwayat</span>
                        </Link>

                        <button
                            type="button"
                            onClick={handleLogout}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-700/80 bg-slate-800/50 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:border-red-500/40 hover:bg-red-500/15 hover:text-red-300"
                        >
                            <LogOut className="size-4" />
                            <span>Keluar</span>
                        </button>
                    </div>
                </div>
            </header>

            {/* Flash Message Global (Sukses / Error) */}
            {(flash?.success || flash?.error) && (
                <div className="mx-auto max-w-5xl px-4 pt-4 sm:px-6">
                    {flash?.success && (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800 sm:text-sm">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800 sm:text-sm">
                            {flash.error}
                        </div>
                    )}
                </div>
            )}

            {/* Konten Halaman */}
            <main className="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
                {children}
            </main>
        </div>
    );
}