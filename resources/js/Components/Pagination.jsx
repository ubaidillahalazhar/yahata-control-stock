import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { formatInt } from '@/lib/format';
import { cn } from '@/Components/ui';

/**
 * Pagination untuk response Laravel API Resource ({ data, links, meta }).
 */
export default function Pagination({ meta, links }) {
    if (!meta || meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-sm print:hidden">
            <p className="text-slate-600">
                Menampilkan <b className="tabular-nums">{formatInt(meta.from)}</b>–
                <b className="tabular-nums">{formatInt(meta.to)}</b> dari{' '}
                <b className="tabular-nums">{formatInt(meta.total)}</b> data
            </p>
            {meta.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <PageLink href={links?.prev}>
                        <ChevronLeft className="size-4" /> Sebelumnya
                    </PageLink>
                    <span className="px-1 text-slate-600 tabular-nums">
                        {meta.current_page} / {meta.last_page}
                    </span>
                    <PageLink href={links?.next}>
                        Berikutnya <ChevronRight className="size-4" />
                    </PageLink>
                </div>
            )}
        </div>
    );
}

function PageLink({ href, children }) {
    const className =
        'inline-flex h-8 items-center gap-1 rounded-md border border-slate-300 bg-white px-3 font-semibold';

    if (!href) {
        return <span className={cn(className, 'cursor-not-allowed text-slate-400')}>{children}</span>;
    }

    return (
        <Link href={href} preserveScroll preserveState className={cn(className, 'text-slate-700 hover:bg-slate-50')}>
            {children}
        </Link>
    );
}
