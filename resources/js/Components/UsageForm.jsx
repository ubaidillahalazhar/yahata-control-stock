import { useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Ban, Gauge, PencilLine, RotateCcw, TriangleAlert } from 'lucide-react';
import { useMemo } from 'react';
import LifeBar from '@/Components/LifeBar';
import StatusBadge from '@/Components/StatusBadge';
import ToolCombobox from '@/Components/ToolCombobox';
import { Button, Field, Input, Textarea, cn } from '@/Components/ui';
import { formatInt, toDateInput } from '@/lib/format';
import { lifeOf, lifeSummary } from '@/lib/life';
import { readStorage, STORAGE_KEYS, writeStorage } from '@/lib/storage';
import { unusualReason } from '@/lib/usageAnomaly';

const today = () => toDateInput(new Date());

/**
 * Nama PIC: PIC selalu atas nama akunnya sendiri (dikunci, server juga memaksa);
 * admin bebas mengisi (diingat di browser).
 */
function usePicName() {
    const user = usePage().props.auth?.user ?? null;
    const locked = Boolean(user && !user.is_admin);

    return { locked, initial: locked ? user.name : readStorage(STORAGE_KEYS.picName) };
}

function PicNameField({ value, onChange, error, locked }) {
    return (
        <Field label="Nama PIC" required error={error} hint={locked ? 'Sesuai akun Anda' : undefined}>
            <Input
                value={value}
                onChange={(event) => onChange(event.target.value)}
                invalid={Boolean(error)}
                readOnly={locked}
                className={locked ? 'bg-slate-100' : undefined}
                placeholder="Nama petugas"
                autoComplete="name"
            />
        </Field>
    );
}

/**
 * Peringatan angka janggal + centang konfirmasi. Muncul langsung untuk aturan 10% life time,
 * atau setelah server menolak (aturan rata-rata harian).
 */
function UnusualConfirm({ clientReason, serverError, checked, onChange }) {
    const message = serverError ?? clientReason;

    if (!message) {
        return null;
    }

    return (
        <div className="space-y-2 rounded-md border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-900">
            <p className="flex items-start gap-2 font-semibold">
                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                {message}
            </p>
            <label className="flex items-center gap-2 font-semibold">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    className="size-4"
                />
                Saya sudah cek, angka ini benar
            </label>
        </div>
    );
}

/**
 * Input pemakaian life time (tanggal bebas, maks. hari ini). Dipakai di halaman Input Pemakaian & modal.
 */
export default function UsageForm({ tools, initialToolId = null, onSuccess }) {
    const pic = usePicName();
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        tool_id: initialToolId ?? '',
        usage_date: today(),
        qty: '',
        pic_name: pic.initial,
        notes: '',
        confirm_unusual: false,
    });

    const tool = useMemo(() => tools.find((item) => item.id === Number(data.tool_id)) ?? null, [tools, data.tool_id]);
    const qty = Math.max(0, Number.parseInt(data.qty, 10) || 0);
    const before = tool ? lifeOf(tool) : null;
    const after = tool ? lifeSummary(tool.life_time, tool.life_used + qty) : null;
    const clientUnusual = tool && qty > 0 ? unusualReason(qty, tool.life_time) : null;
    const needsConfirm = Boolean(clientUnusual || errors.confirm_unusual);

    // Angka / tool berubah → konfirmasi lama tidak berlaku lagi.
    const changeQty = (key, value) => {
        setData((current) => ({ ...current, [key]: value, confirm_unusual: false }));
        clearErrors('confirm_unusual');
    };

    const submit = (event) => {
        event.preventDefault();
        if (!pic.locked) writeStorage(STORAGE_KEYS.picName, data.pic_name);

        post('/tool-usages', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset('qty', 'notes', 'confirm_unusual');
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
                        onChange={(id) => changeQty('tool_id', id)}
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
                        onChange={(event) => changeQty('qty', event.target.value)}
                        invalid={Boolean(errors.qty)}
                        placeholder="mis. 600"
                        className="text-right text-lg font-bold tabular-nums"
                    />
                </Field>
            </div>

            <UnusualConfirm
                clientReason={clientUnusual}
                serverError={errors.confirm_unusual}
                checked={data.confirm_unusual}
                onChange={(checked) => setData('confirm_unusual', checked)}
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <PicNameField
                    value={data.pic_name}
                    onChange={(value) => setData('pic_name', value)}
                    error={errors.pic_name}
                    locked={pic.locked}
                />
                <Field label="Catatan" error={errors.notes}>
                    <Input
                        value={data.notes}
                        onChange={(event) => setData('notes', event.target.value)}
                        placeholder="opsional, mis. no. WO / lot"
                    />
                </Field>
            </div>

            <Button
                type="submit"
                size="lg"
                className="w-full"
                loading={processing}
                disabled={!tool || qty < 1 || (needsConfirm && !data.confirm_unusual)}
            >
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
    const pic = usePicName();
    const { data, setData, post, processing, errors } = useForm({
        pic_name: pic.initial,
        cancel_reason: '',
    });

    const submit = (event) => {
        event.preventDefault();
        if (!pic.locked) writeStorage(STORAGE_KEYS.picName, data.pic_name);
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

            {log.type === 'USAGE' && (
                <p className="text-xs text-slate-500">
                    Hanya salah jumlah / tanggal? Gunakan <b>Koreksi</b> agar angka lama & baru tetap terhubung di
                    riwayat.
                </p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <PicNameField
                    value={data.pic_name}
                    onChange={(value) => setData('pic_name', value)}
                    error={errors.pic_name}
                    locked={pic.locked}
                />
                <Field label="Alasan" error={errors.cancel_reason}>
                    <Input
                        value={data.cancel_reason}
                        onChange={(event) => setData('cancel_reason', event.target.value)}
                        placeholder="mis. input dobel"
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
 * Koreksi satu input Pemakaian: baris lama dibatalkan (alasan wajib), baris baru menunjuk baris lama.
 */
export function CorrectUsageForm({ log, onSuccess }) {
    const pic = usePicName();
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        usage_date: log.usage_date,
        qty: String(log.qty),
        pic_name: pic.initial,
        reason: '',
        confirm_unusual: false,
    });

    const qty = Math.max(0, Number.parseInt(data.qty, 10) || 0);
    const lifeTime = log.tool?.life_time ?? 0;
    const delta = qty - log.qty;
    const unchanged = qty === log.qty && data.usage_date === log.usage_date;
    const clientUnusual = qty > 0 ? unusualReason(qty, lifeTime) : null;
    const needsConfirm = Boolean(clientUnusual || errors.confirm_unusual);

    const changeQty = (value) => {
        setData((current) => ({ ...current, qty: value, confirm_unusual: false }));
        clearErrors('confirm_unusual');
    };

    const submit = (event) => {
        event.preventDefault();
        if (!pic.locked) writeStorage(STORAGE_KEYS.picName, data.pic_name);
        post(`/tool-usages/${log.id}/correct`, { preserveScroll: true, onSuccess: () => onSuccess?.() });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <p className="font-semibold text-slate-900">
                    Input lama: {log.usage_date_display} · <span className="tabular-nums">{formatInt(log.qty)} pcs</span>
                </p>
                <p className="text-xs text-slate-500">
                    Dicatat {log.created_at_display} oleh {log.pic_name}
                    {log.change_until_display && ` · bisa diubah sampai ${log.change_until_display}`}
                </p>
                <p className="mt-1 text-xs text-slate-500">
                    Input lama dibatalkan (tetap tercatat, dicoret) dan diganti input baru di bawah.
                </p>
            </div>

            {errors.usage && <p className="text-sm font-semibold text-red-600">{errors.usage}</p>}

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Tanggal pemakaian" required error={errors.usage_date}>
                    <Input
                        type="date"
                        max={today()}
                        value={data.usage_date}
                        onChange={(event) => setData('usage_date', event.target.value)}
                        invalid={Boolean(errors.usage_date)}
                    />
                </Field>
                <Field
                    label="Jumlah yang benar (pcs)"
                    required
                    error={errors.qty}
                    hint={qty > 0 && delta !== 0 ? `${delta > 0 ? '+' : '−'}${formatInt(Math.abs(delta))} pcs dari input lama` : undefined}
                >
                    <Input
                        type="number"
                        inputMode="numeric"
                        min={1}
                        value={data.qty}
                        onChange={(event) => changeQty(event.target.value)}
                        invalid={Boolean(errors.qty)}
                        className="text-right text-lg font-bold tabular-nums"
                    />
                </Field>
            </div>

            <UnusualConfirm
                clientReason={clientUnusual}
                serverError={errors.confirm_unusual}
                checked={data.confirm_unusual}
                onChange={(checked) => setData('confirm_unusual', checked)}
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <PicNameField
                    value={data.pic_name}
                    onChange={(value) => setData('pic_name', value)}
                    error={errors.pic_name}
                    locked={pic.locked}
                />
                <Field label="Alasan koreksi" required error={errors.reason}>
                    <Input
                        value={data.reason}
                        onChange={(event) => setData('reason', event.target.value)}
                        invalid={Boolean(errors.reason)}
                        placeholder="mis. salah ketik, kelebihan angka 0"
                    />
                </Field>
            </div>

            <Button
                type="submit"
                size="lg"
                className="w-full"
                loading={processing}
                disabled={unchanged || qty < 1 || data.reason.trim().length < 3 || (needsConfirm && !data.confirm_unusual)}
            >
                <PencilLine className="size-5" /> Simpan Koreksi
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
