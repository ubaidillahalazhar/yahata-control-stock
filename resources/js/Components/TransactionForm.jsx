import { useForm } from '@inertiajs/react';
import { ArrowDownToLine, ArrowRight, ArrowUpFromLine, Minus, Plus, TriangleAlert } from 'lucide-react';
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
        hint: 'Penerimaan dari supplier',
        icon: ArrowDownToLine,
        active: 'border-emerald-600 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-600',
    },
    {
        value: 'OUT',
        label: 'OUT — Pengambilan',
        hint: 'Pengambilan reguler',
        icon: ArrowUpFromLine,
        active: 'border-slate-800 bg-slate-100 text-slate-900 ring-2 ring-slate-800',
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
    const stockAfter = tool ? tool.current_stock + (isIn ? qty : -qty) : null;
    const insufficient = tool !== null && stockAfter < 0;
    const statusAfter = tool && !insufficient ? (stockAfter >= tool.min_stock_e ? 'SECURE' : 'ORDER') : null;

    const submit = (event) => {
        event.preventDefault();
        writeStorage(STORAGE_KEYS.picName, data.pic_name);

        post('/transactions', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset('qty', 'reference_no', 'notes');
                onSuccess?.();
            },
        });
    };

    const changeQty = (delta) => setData('qty', Math.max(1, qty + delta));

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-2">
                {TYPES.map(({ value, label, hint, icon: Icon, active }) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => setData('type', value)}
                        aria-pressed={data.type === value}
                        className={cn(
                            'flex items-center gap-2 rounded-lg border px-3 py-3 text-left transition',
                            data.type === value ? active : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50',
                        )}
                    >
                        <Icon className="size-5 shrink-0" aria-hidden="true" />
                        <span className="min-w-0">
                            <span className="block text-sm font-bold">{label}</span>
                            <span className="block truncate text-xs opacity-75">{hint}</span>
                        </span>
                    </button>
                ))}
            </div>
            {errors.type && <p className="text-xs font-semibold text-red-600">{errors.type}</p>}

            <Field label="Item Tooling" required error={errors.tool_id}>
                <ToolCombobox
                    tools={tools}
                    value={data.tool_id}
                    onChange={(id) => setData('tool_id', id)}
                    invalid={Boolean(errors.tool_id)}
                />
            </Field>

            {tool && (
                <div
                    className={cn(
                        'grid grid-cols-[1fr_auto_1fr] items-center gap-3 rounded-lg border px-4 py-3',
                        insufficient ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-slate-50',
                    )}
                >
                    <div>
                        <p className="text-[11px] font-semibold text-slate-500 uppercase">Stok saat ini</p>
                        <p className="text-2xl font-extrabold tabular-nums">{formatInt(tool.current_stock)}</p>
                        <p className="text-xs text-slate-500">
                            Min (E) {formatInt(tool.min_stock_e)} · Max {formatInt(tool.max_total_stand_by)}
                        </p>
                    </div>
                    <ArrowRight className="size-5 text-slate-400" aria-hidden="true" />
                    <div className="text-right">
                        <p className="text-[11px] font-semibold text-slate-500 uppercase">Setelah transaksi</p>
                        <p className={cn('text-2xl font-extrabold tabular-nums', insufficient && 'text-red-600')}>
                            {formatInt(stockAfter)}
                        </p>
                        {statusAfter && <StatusBadge status={statusAfter} />}
                    </div>
                </div>
            )}

            {insufficient && (
                <p className="flex items-center gap-2 text-sm font-semibold text-red-700">
                    <TriangleAlert className="size-4" /> Jumlah OUT melebihi stok yang tersedia.
                </p>
            )}

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
                        invalid={Boolean(errors.qty)}
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
                        placeholder={isIn ? 'mis. SJ-2026-0012' : 'opsional'}
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

            <Button
                type="submit"
                variant={isIn ? 'success' : 'dark'}
                size="lg"
                className="w-full"
                loading={processing}
                disabled={!tool || qty < 1 || insufficient}
            >
                {isIn ? <ArrowDownToLine className="size-5" /> : <ArrowUpFromLine className="size-5" />}
                Simpan Transaksi {data.type}
            </Button>
        </form>
    );
}
