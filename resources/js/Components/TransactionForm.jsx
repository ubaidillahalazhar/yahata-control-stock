import { useForm } from '@inertiajs/react';
import { ArrowDownToLine, ArrowRight, ArrowUpFromLine, Minus, Plus, Printer, TriangleAlert } from 'lucide-react';
import { useMemo } from 'react';
import StatusBadge from '@/Components/StatusBadge';
import ToolCombobox from '@/Components/ToolCombobox';
import { Button, Field, Input, Textarea, cn } from '@/Components/ui';
import { formatInt } from '@/lib/format';
import { readStorage, STORAGE_KEYS, writeStorage } from '@/lib/storage';

const TYPES = [
    {
        value: 'IN',
        label: 'IN — Barang Masuk',
        hint: 'Penerimaan dari supplier / restock',
        icon: ArrowDownToLine,
        active: 'border-emerald-600 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-600/25 shadow-xs',
        iconBadge: 'bg-emerald-600 text-white',
    },
    {
        value: 'OUT',
        label: 'OUT — Barang Keluar',
        hint: 'Pengambilan reguler lini produksi',
        icon: ArrowUpFromLine,
        active: 'border-blue-600 bg-blue-50 text-blue-800 ring-2 ring-blue-600/25 shadow-xs',
        iconBadge: 'bg-blue-600 text-white',
    },
];

/**
 * Form transaksi stok IN / OUT (dipakai di halaman Transaksi & modal Dashboard).
 */
export default function TransactionForm({ tools, initialToolId = null, initialType = 'IN', onSuccess }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        tool_id: initialToolId ?? '',
        type: initialType,
        qty: 1,
        reference_no: '',
        pic_name: readStorage(STORAGE_KEYS.picName),
        notes: '',
    });

    const tool = useMemo(() => tools.find((item) => item.id === Number(data.tool_id)) ?? null, [tools, data.tool_id]);
    const qty = Math.max(0, Number.parseInt(data.qty, 10) || 0);
    const isIn = data.type === 'IN';
    const delta = isIn ? qty : -qty;
    const stockAfter = tool ? tool.current_stock + delta : null;
    const insufficient = tool !== null && stockAfter < 0;
    const statusAfter = tool && !insufficient ? (stockAfter >= tool.min_stock_e ? 'SECURE' : 'ORDER') : null;

    const submit = (event) => {
        event.preventDefault();
        writeStorage(STORAGE_KEYS.picName, data.pic_name);

        post('/transactions', {
            preserveScroll: true,
            onSuccess: () => {
                reset('qty', 'reference_no', 'notes');
                onSuccess?.();
            },
        });
    };

    const changeQty = (deltaQty) => setData('qty', Math.max(1, qty + deltaQty));

    return (
        <form onSubmit={submit} className="space-y-4">
            {/* 1. Pemilih Mode Visual IN vs OUT Berbasis Kartu */}
            <div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {TYPES.map(({ value, label, hint, icon: Icon, active, iconBadge }) => {
                        const selected = data.type === value;

                        return (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setData('type', value)}
                                aria-pressed={selected}
                                className={cn(
                                    'flex items-center gap-3 rounded-xl border p-3.5 text-left transition-all duration-150',
                                    selected
                                        ? active
                                        : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:bg-slate-50',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-10 shrink-0 items-center justify-center rounded-lg transition-colors',
                                        selected ? iconBadge : 'bg-slate-100 text-slate-500',
                                    )}
                                >
                                    <Icon className="size-5" aria-hidden="true" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-sm font-bold">{label}</span>
                                    <span className="block truncate text-xs opacity-80">{hint}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
                {errors.type && <p className="mt-1.5 text-xs font-semibold text-red-600">{errors.type}</p>}
            </div>

            <Field label="Item Tooling" required error={errors.tool_id}>
                <ToolCombobox
                    tools={tools}
                    value={data.tool_id}
                    onChange={(id) => setData('tool_id', id)}
                    invalid={Boolean(errors.tool_id)}
                />
            </Field>

            <Field label={isIn ? 'Jumlah diterima (pcs)' : 'Jumlah diambil (pcs)'} required error={errors.qty}>
                <div className="flex items-stretch gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-10 w-12"
                        onClick={() => changeQty(-1)}
                        aria-label="Kurangi"
                    >
                        <Minus className="size-4" />
                    </Button>
                    <Input
                        type="number"
                        inputMode="numeric"
                        min={1}
                        value={data.qty}
                        onChange={(event) => setData('qty', event.target.value)}
                        invalid={Boolean(errors.qty) || insufficient}
                        className="text-center text-lg font-bold tabular-nums"
                    />
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-10 w-12"
                        onClick={() => changeQty(1)}
                        aria-label="Tambah"
                    >
                        <Plus className="size-4" />
                    </Button>
                </div>
            </Field>

            {/* 2. Kartu Simulasi Stok Real-Time (Stok Sekarang -> Perubahan -> Estimasi Stok Akhir) */}
            {tool && (
                <div
                    className={cn(
                        'rounded-xl border p-4 transition-colors',
                        insufficient
                            ? 'border-red-300 bg-red-50/90'
                            : isIn
                              ? 'border-emerald-200 bg-emerald-50/40'
                              : 'border-blue-200 bg-blue-50/40',
                    )}
                >
                    <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                        <div>
                            <p className="text-[11px] font-bold tracking-wide text-slate-500 uppercase">
                                Stok Sekarang
                            </p>
                            <p className="mt-0.5 font-mono text-2xl font-extrabold text-slate-900 tabular-nums">
                                {formatInt(tool.current_stock)}
                            </p>
                            <p className="text-[11px] text-slate-500">
                                Min {formatInt(tool.min_stock_e)} · Max {formatInt(tool.max_total_stand_by)}
                            </p>
                        </div>

                        <div className="flex flex-col items-center px-2">
                            <span
                                className={cn(
                                    'rounded-full px-2.5 py-0.5 font-mono text-xs font-extrabold tabular-nums',
                                    insufficient
                                        ? 'bg-red-100 text-red-700'
                                        : isIn
                                          ? 'bg-emerald-100 text-emerald-800'
                                          : 'bg-blue-100 text-blue-800',
                                )}
                            >
                                {isIn ? `+${formatInt(qty)}` : `−${formatInt(qty)}`}
                            </span>
                            <ArrowRight className="mt-1 size-4 text-slate-400" aria-hidden="true" />
                        </div>

                        <div className="text-right">
                            <p className="text-[11px] font-bold tracking-wide text-slate-500 uppercase">
                                Estimasi Stok Akhir
                            </p>
                            <p
                                className={cn(
                                    'mt-0.5 font-mono text-2xl font-extrabold tabular-nums',
                                    insufficient ? 'text-red-600' : 'text-slate-900',
                                )}
                            >
                                {formatInt(stockAfter)}
                            </p>
                            <div className="mt-0.5 flex justify-end">
                                {statusAfter ? (
                                    <StatusBadge status={statusAfter} />
                                ) : (
                                    <span className="text-[11px] font-bold text-red-600">Stok tidak cukup</span>
                                )}
                            </div>
                        </div>
                    </div>

                    {insufficient && (
                        <p className="mt-3 flex items-center gap-2 border-t border-red-200 pt-2.5 text-xs font-semibold text-red-700">
                            <TriangleAlert className="size-4 shrink-0" />
                            Jumlah OUT ({formatInt(qty)} pcs) melebihi stok yang tersedia ({formatInt(tool.current_stock)}{' '}
                            pcs).
                        </p>
                    )}
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Nama PIC" required error={errors.pic_name}>
                    <Input
                        value={data.pic_name}
                        onChange={(event) => setData('pic_name', event.target.value)}
                        invalid={Boolean(errors.pic_name)}
                        placeholder="Nama petugas"
                        autoComplete="name"
                    />
                </Field>
                <Field label={isIn ? 'No. PO / Surat Jalan' : 'No. Referensi / WO'} error={errors.reference_no}>
                    <Input
                        value={data.reference_no}
                        onChange={(event) => setData('reference_no', event.target.value)}
                        invalid={Boolean(errors.reference_no)}
                        placeholder={isIn ? 'mis. SJ-2026-0012' : 'opsional (otomatis jika kosong)'}
                    />
                </Field>
            </div>

            <Field label="Catatan" error={errors.notes}>
                <Textarea
                    rows={2}
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                    placeholder="opsional"
                />
            </Field>

            {/* 3. Tombol Aksi Utama dengan Ikon Printer */}
            <Button
                type="submit"
                variant={isIn ? 'success' : 'primary'}
                size="lg"
                className="w-full"
                loading={processing}
                disabled={!tool || qty < 1 || insufficient}
            >
                <Printer className="size-5" aria-hidden="true" />
                Simpan & Buat Bukti Cetak
            </Button>
        </form>
    );
}