import { Link } from '@inertiajs/react';
import { ArrowLeftRight, History } from 'lucide-react';
import { TransactionTypeBadge } from '@/Components/StatusBadge';
import TransactionForm from '@/Components/TransactionForm';
import { Button, Card, CardHeader, EmptyState, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatInt, formatSigned } from '@/lib/format';

export default function Create({ tools, recentTransactions, initialToolId, initialType }) {
    return (
        <AppLayout
            title="Quick Stock Transaction"
            subtitle="Catat penerimaan barang dari supplier (IN) atau pengambilan reguler (OUT)"
            actions={
                <Button as={Link} href="/audit-log" variant="outline" size="sm">
                    <History className="size-4" /> Audit Log
                </Button>
            }
        >
            <div className="grid gap-5 lg:grid-cols-[minmax(0,560px)_1fr]">
                <Card>
                    <CardHeader
                        icon={ArrowLeftRight}
                        title="Form Transaksi Stok"
                        subtitle="Stok terkunci per item selama penyimpanan (anti race condition)"
                    />
                    <div className="p-5">
                        <TransactionForm tools={tools} initialToolId={initialToolId} initialType={initialType} />
                    </div>
                </Card>

                <Card>
                    <CardHeader
                        icon={History}
                        title="Transaksi Terakhir"
                        subtitle="15 mutasi stok terbaru (semua jenis)"
                    />
                    {recentTransactions.length === 0 ? (
                        <EmptyState icon={History} title="Belum ada transaksi" />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {recentTransactions.map((trx) => (
                                <li key={trx.id} className="flex items-start gap-3 px-5 py-3">
                                    <TransactionTypeBadge type={trx.type} title={trx.type_label} />
                                    <div className="min-w-0 flex-1">
                                        {trx.tool ? (
                                            <Link
                                                href={`/tools/${trx.tool.id}`}
                                                className="block truncate font-mono text-[13px] font-bold text-slate-900 hover:text-blue-700"
                                            >
                                                {trx.tool.tools_name_yes}
                                            </Link>
                                        ) : (
                                            <span className="text-slate-400">—</span>
                                        )}
                                        <p className="truncate text-xs text-slate-500">
                                            {trx.created_at_display} · {trx.pic_name ?? '—'}
                                            {trx.reference_no ? ` · ${trx.reference_no}` : ''}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p
                                            className={cn(
                                                'font-bold tabular-nums',
                                                trx.qty_change < 0 ? 'text-red-600' : 'text-emerald-700',
                                            )}
                                        >
                                            {formatSigned(trx.qty_change)}
                                        </p>
                                        <p className="text-xs text-slate-500 tabular-nums">
                                            {formatInt(trx.stock_before)} → {formatInt(trx.stock_after)}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
