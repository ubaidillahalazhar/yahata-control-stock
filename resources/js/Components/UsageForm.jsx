import { useForm } from '@inertiajs/react';
import { ArrowRight, Ban, Gauge, RotateCcw, TriangleAlert } from 'lucide-react';
import { useMemo } from 'react';
import LifeBar from '@/Components/LifeBar';
import StatusBadge from '@/Components/StatusBadge';
import ToolCombobox from '@/Components/ToolCombobox';
import { Button, Field, Input, Textarea, cn } from '@/Components/ui';
import { formatInt, toDateInput } from '@/lib/format';
import { lifeOf, lifeSummary } from '@/lib/life';
import { readStorage, STORAGE_KEYS, writeStorage } from '@/lib/storage';

const today = () => toDateInput(new Date());

/**
 * Input pemakaian life time (tanggal bebas, maks. hari ini). Dipakai di halaman Input Pemakaian & modal.
 */
export default function UsageForm({ tools, initialToolId = null, onSuccess }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        tool_id: initialToolId ?? '',
        usage_date: today(),
        qty: '',
        pic_name: readStorage(STORAGE_KEYS.picName),
        notes: '',
    });

    const tool = useMemo(() => tools.find((item) => item.id === Number(data.tool_id)) ?? null, [tools, data.tool_id]);
    const qty = Math.max(0, Number.parseInt(data.qty, 10) || 0);
    const before = tool ? lifeOf(tool) : null;
    const after = tool ? lifeSummary(tool.life_time, tool.life_used + qty) : null;

    const submit = (event) => {
        event.preventDefault();
        writeStorage(STORAGE_KEYS.picName, data.pic_name);

        post('/tool-usages', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset('qty', 'notes');
                onSuccess?.();
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            {tools.length > 1 && (
                <Field label="Item Tooling" required error={errors.tool_id}>
                    <ToolCombobox
                        tools={tools}
                        value={data.tool_id}
                        onChange={(id) => setData('tool_id', id)}
                        invalid={Boolean(errors.tool_id)}
                    />
                </Field>
            )}

            {tool && (
                <div className="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                    <div>
                        <p className="mb-1 text-[11px] font-semibold text-slate-500 uppercase">Life saat ini</p>
                        <LifeBar life={before} lifeTime={tool.life_time} />
                    </div>
                    <ArrowRight className="hidden size-5 text-slate-400 sm:block" aria-hidden="true" />
                    <div>
                        <p className="mb-1 flex items-center justify-between text-[11px] font-semibold text-slate-500 uppercase">
                            Setelah input <StatusBadge status={after.status} />
                        </p>
                        <LifeBar life={after} lifeTime={tool.life_time} />
                    </div>
                </div>
            )}

            {after?.over > 0 && (
                <p className="flex items-start gap-2 rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                    Pemakaian melebihi life time {formatInt(after.over)} pcs. Jika tool sudah diganti, catat "Ganti Tool"
                    di halaman detail tool.
                </p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Tanggal pemakaian" required error={errors.usage_date} hint="Boleh tanggal mundur">
                    <Input
                        type="date"
                        max={today()}
                        value={data.usage_date}
                        onChange={(event) => setData('usage_date', event.target.value)}
                        invalid={Boolean(errors.usage_date)}
                    />
                </Field>
                <Field label="Jumlah terpakai (pcs)" required error={errors.qty}>
                    <Input
                        type="number"
                        inputMode="numeric"
                        min={1}
                        value={data.qty}
                        onChange={(event) => setData('qty', event.target.value)}
                        invalid={Boolean(errors.qty)}
                        placeholder="mis. 600"
                        className="text-right text-lg font-bold tabular-nums"
                    />
                </Field>
            </div>

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
                <Field label="Catatan" error={errors.notes}>
                    <Input
                        value={data.notes}
                        onChange={(event) => setData('notes', event.target.value)}
                        placeholder="opsional, mis. no. WO / lot"
                    />
                </Field>
            </div>

            <Button type="submit" size="lg" className="w-full" loading={processing} disabled={!tool || qty < 1}>
                <Gauge className="size-5" /> Simpan Pemakaian
            </Button>
        </form>
    );
}

/**
 * Ganti tool baru: life terpakai kembali 0 mulai tanggal ini. Stok tidak berubah.
 */
export function ResetLifeForm({ tool, onSuccess }) {
    const { data, setData, post, processing, errors } = useForm({
        usage_date: today(),
        pic_name: readStorage(STORAGE_KEYS.picName),
        notes: '',
    });

    const submit = (event) => {
        event.preventDefault();
        writeStorage(STORAGE_KEYS.picName, data.pic_name);
        post(`/tools/${tool.id}/life-reset`, { preserveScroll: true, onSuccess: () => onSuccess?.() });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Life terpakai saat ini <b className="tabular-nums">{formatInt(tool.life_used)}</b> pcs akan kembali ke{' '}
                <b>0</b> mulai tanggal penggantian. Input pemakaian dengan tanggal sebelum itu dihitung milik tool lama.
                <p className="mt-2 text-xs text-slate-500">
                    Stok tidak berubah otomatis. Jika tool pengganti diambil dari stok, catat juga lewat Transaksi OUT.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Tanggal penggantian" required error={errors.usage_date}>
                    <Input
                        type="date"
                        max={today()}
                        value={data.usage_date}
                        onChange={(event) => setData('usage_date', event.target.value)}
                        invalid={Boolean(errors.usage_date)}
                    />
                </Field>
                <Field label="Nama PIC" required error={errors.pic_name}>
                    <Input
                        value={data.pic_name}
                        onChange={(event) => setData('pic_name', event.target.value)}
                        invalid={Boolean(errors.pic_name)}
                        placeholder="Nama petugas"
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

            <Button type="submit" variant="dark" size="lg" className="w-full" loading={processing}>
                <RotateCcw className="size-5" /> Simpan Ganti Tool
            </Button>
        </form>
    );
}

/**
 * Batalkan satu input (baris tetap tersimpan, ditandai batal; life terpakai dihitung ulang).
 */
export function CancelUsageForm({ log, onSuccess }) {
    const { data, setData, post, processing, errors } = useForm({
        pic_name: readStorage(STORAGE_KEYS.picName),
        cancel_reason: '',
    });

    const submit = (event) => {
        event.preventDefault();
        writeStorage(STORAGE_KEYS.picName, data.pic_name);
        post(`/tool-usages/${log.id}/cancel`, { preserveScroll: true, onSuccess: () => onSuccess?.() });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <p className="font-semibold text-slate-900">
                    {log.type_label} · {log.usage_date_display}
                    {log.type === 'USAGE' && <span className="tabular-nums"> · {formatInt(log.qty)} pcs</span>}
                </p>
                <p className="text-xs text-slate-500">
                    Dicatat {log.created_at_display} oleh {log.pic_name}
                </p>
            </div>

            {errors.usage && <p className="text-sm font-semibold text-red-600">{errors.usage}</p>}

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Nama PIC" required error={errors.pic_name}>
                    <Input
                        value={data.pic_name}
                        onChange={(event) => setData('pic_name', event.target.value)}
                        invalid={Boolean(errors.pic_name)}
                    />
                </Field>
                <Field label="Alasan" error={errors.cancel_reason}>
                    <Input
                        value={data.cancel_reason}
                        onChange={(event) => setData('cancel_reason', event.target.value)}
                        placeholder="mis. salah ketik qty"
                    />
                </Field>
            </div>

            <Button type="submit" variant="danger" size="lg" className={cn('w-full')} loading={processing}>
                <Ban className="size-5" /> Batalkan Input
            </Button>
        </form>
    );
}

/**
 * Batalkan seluruh input aktif dari satu batch import Excel (PLT-...).
 */
export function CancelBatchForm({ batchNo, activeRows, totalQty, onSuccess }) {
    const { data, setData, post, processing, errors } = useForm({
        pic_name: readStorage(STORAGE_KEYS.picName),
        cancel_reason: '',
    });

    const submit = (event) => {
        event.preventDefault();
        writeStorage(STORAGE_KEYS.picName, data.pic_name);
        post(`/tool-usages/batches/${encodeURIComponent(batchNo)}/cancel`, {
            preserveScroll: true,
            onSuccess: () => onSuccess?.(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <p className="font-semibold">
                    Batch <span className="font-mono">{batchNo}</span> · {formatInt(activeRows)} input aktif ·{' '}
                    <span className="tabular-nums">{formatInt(totalQty)} pcs</span>
                </p>
                <p className="text-xs">
                    Semua input aktif di batch ini dicoret (tetap tercatat) dan life time tool terkait dihitung ulang.
                </p>
            </div>

            {errors.usage && <p className="text-sm font-semibold text-red-600">{errors.usage}</p>}

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Nama PIC" required error={errors.pic_name}>
                    <Input
                        value={data.pic_name}
                        onChange={(event) => setData('pic_name', event.target.value)}
                        invalid={Boolean(errors.pic_name)}
                    />
                </Field>
                <Field label="Alasan" error={errors.cancel_reason}>
                    <Input
                        value={data.cancel_reason}
                        onChange={(event) => setData('cancel_reason', event.target.value)}
                        placeholder="mis. file salah upload"
                    />
                </Field>
            </div>

            <Button type="submit" variant="danger" size="lg" className="w-full" loading={processing}>
                <Ban className="size-5" /> Batalkan Seluruh Batch
            </Button>
        </form>
    );
}
