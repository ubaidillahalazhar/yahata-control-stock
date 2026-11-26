import { useForm, usePage } from '@inertiajs/react';
import {
    Gauge,
    Hammer,
    MapPin,
    Minus,
    PackagePlus,
    Plus,
    Send,
    Settings2,
    TriangleAlert,
    Unlink,
    X,
    Zap,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import StatusBadge from '@/Components/StatusBadge';
import ToolCombobox from '@/Components/ToolCombobox';
import { Button, Card, Field, Input, Textarea, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import PicLayout from '@/Layouts/PicLayout';
import { formatInt } from '@/lib/format';
import { readStorage, STORAGE_KEYS, writeStorage } from '@/lib/storage';

const REASON_ICONS = { CHIPPING: Zap, WEAR_NORMAL: Gauge, BROKEN: Unlink, SETUP_ERROR: Settings2 };
const ACTION_ICONS = { REPLACE_NEW: PackagePlus, REGRIND_REPAIR: Hammer };
const SHIFTS = [1, 2, 3];

export default function Create({ tools, selectedToolId, scope, reasons, actions }) {
    const authUser = usePage().props.auth?.user ?? null;
    const isAdmin = Boolean(authUser?.is_admin);
    // PIC melapor atas nama akunnya sendiri; hanya admin yang boleh mengisi nama operator lain.
    const operatorLocked = Boolean(authUser && !isAdmin);
    const savedShift = Number(readStorage(STORAGE_KEYS.shift));

    const { data, setData, post, processing, errors } = useForm({
        tool_id: selectedToolId ?? '',
        operator_name: operatorLocked ? authUser.name : readStorage(STORAGE_KEYS.operatorName),
        shift: SHIFTS.includes(savedShift) ? savedShift : '',
        qty_replaced: 1,
        actual_shot_count: '',
        damage_reason: '',
        action: 'REPLACE_NEW',
        notes: '',
    });

    const hasScope = Boolean(scope?.machine || scope?.rack);
    const [scoped, setScoped] = useState(hasScope);

    const scopedTools = useMemo(
        () =>
            tools.filter(
                (tool) =>
                    (!scope?.machine || tool.machine_name === scope.machine) &&
                    (!scope?.rack || tool.rack_location === scope.rack),
            ),
        [tools, scope],
    );
    const options = scoped && scopedTools.length > 0 ? scopedTools : tools;

    const tool = useMemo(() => tools.find((item) => item.id === Number(data.tool_id)) ?? null, [tools, data.tool_id]);
    const qty = Math.max(0, Number.parseInt(data.qty_replaced, 10) || 0);
    const cutsStock = actions.find((action) => action.value === data.action)?.cuts_stock ?? false;
    const stockAfter = tool ? tool.current_stock - (cutsStock ? qty : 0) : null;
    const insufficient = Boolean(tool && cutsStock && stockAfter < 0);
    const shotCount = Number(data.actual_shot_count);
    const lifeUsage =
        tool && data.actual_shot_count !== '' && tool.life_time > 0
            ? Math.round((shotCount / tool.life_time) * 100)
            : null;

    const submit = (event) => {
        event.preventDefault();
        if (!operatorLocked) {
            writeStorage(STORAGE_KEYS.operatorName, data.operator_name);
        }
        writeStorage(STORAGE_KEYS.shift, data.shift);
        post('/damage-reports', { preserveScroll: true });
    };

    const formContent = (
        <div className="mx-auto max-w-xl">
            {!isAdmin && (
                <div className="mb-4">
                    <h1 className="text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">
                        Laporan Kerusakan &amp; Penggantian Part
                    </h1>
                    <p className="text-xs text-slate-500 sm:text-sm">
                        Pilih item tooling yang rusak dan lengkapi detail penggantian part.
                    </p>
                </div>
            )}

            <form onSubmit={submit} className="space-y-4 pb-24">
                <Section number={1} title="Item Tooling">
                    {hasScope && (
                        <div className="mb-3 flex flex-wrap items-center gap-2 text-xs">
                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-800">
                                <MapPin className="size-3.5" />
                                {[scope.machine && `Mesin ${scope.machine}`, scope.rack && `Rak ${scope.rack}`]
                                    .filter(Boolean)
                                    .join(' · ')}
                                <span className="text-blue-500">({scopedTools.length} item)</span>
                            </span>
                            <button
                                type="button"
                                onClick={() => setScoped(!scoped)}
                                className="font-semibold text-blue-700 underline"
                            >
                                {scoped ? 'Tampilkan semua item' : 'Batasi ke QR ini'}
                            </button>
                        </div>
                    )}
                    <ToolCombobox
                        tools={options}
                        value={data.tool_id}
                        onChange={(id) => setData('tool_id', id)}
                        invalid={Boolean(errors.tool_id)}
                        size="lg"
                    />
                    {errors.tool_id && <p className="mt-1 text-xs font-semibold text-red-600">{errors.tool_id}</p>}

                    {tool && (
                        <div className="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-slate-50 p-3 text-center">
                            <Mini
                                label="Stok"
                                value={formatInt(tool.current_stock)}
                                strong={tool.status_info === 'ORDER' ? 'text-red-600' : 'text-slate-900'}
                            />
                            <Mini label="Min (E)" value={formatInt(tool.min_stock_e)} />
                            <div className="flex flex-col items-center justify-center gap-1">
                                <span className="text-[10px] font-semibold text-slate-500 uppercase">Status</span>
                                <StatusBadge status={tool.status_info} />
                            </div>
                            <p className="col-span-3 text-xs text-slate-500">
                                {tool.tools_name} · Step {tool.step ?? '—'} · Life time {formatInt(tool.life_time)} pcs
                            </p>
                        </div>
                    )}
                </Section>

                <Section number={2} title="Operator & Shift">
                    <div className="space-y-4">
                        <Field
                            label="Nama Operator"
                            required
                            error={errors.operator_name}
                            hint={operatorLocked ? 'Sesuai akun yang sedang login' : undefined}
                        >
                            <Input
                                value={data.operator_name}
                                onChange={(event) => setData('operator_name', event.target.value)}
                                invalid={Boolean(errors.operator_name)}
                                placeholder="Nama lengkap"
                                autoComplete="name"
                                readOnly={operatorLocked}
                                className={cn('h-12 text-base', operatorLocked && 'bg-slate-100 font-semibold text-slate-700')}
                            />
                        </Field>
                        <Field label="Shift" required error={errors.shift}>
                            <div className="grid grid-cols-3 gap-2">
                                {SHIFTS.map((shift) => (
                                    <button
                                        key={shift}
                                        type="button"
                                        onClick={() => setData('shift', shift)}
                                        aria-pressed={data.shift === shift}
                                        className={cn(
                                            'h-12 rounded-lg border text-base font-bold transition',
                                            data.shift === shift
                                                ? 'border-blue-600 bg-blue-600 text-white'
                                                : 'border-slate-300 bg-white text-slate-700',
                                        )}
                                    >
                                        Shift {shift}
                                    </button>
                                ))}
                            </div>
                        </Field>
                    </div>
                </Section>

                <Section number={3} title="Detail Kerusakan">
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Qty Diganti" required error={errors.qty_replaced}>
                                <div className="flex items-stretch">
                                    <button
                                        type="button"
                                        onClick={() => setData('qty_replaced', Math.max(1, qty - 1))}
                                        className="flex w-11 items-center justify-center rounded-l-md border border-r-0 border-slate-300 bg-slate-50"
                                        aria-label="Kurangi"
                                    >
                                        <Minus className="size-4" />
                                    </button>
                                    <Input
                                        type="number"
                                        inputMode="numeric"
                                        min={1}
                                        value={data.qty_replaced}
                                        onChange={(event) => setData('qty_replaced', event.target.value)}
                                        invalid={Boolean(errors.qty_replaced)}
                                        className="h-12 rounded-none text-center text-lg font-bold"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setData('qty_replaced', qty + 1)}
                                        className="flex w-11 items-center justify-center rounded-r-md border border-l-0 border-slate-300 bg-slate-50"
                                        aria-label="Tambah"
                                    >
                                        <Plus className="size-4" />
                                    </button>
                                </div>
                            </Field>
                            <Field
                                label="Aktual Shot Count"
                                required
                                error={errors.actual_shot_count}
                                hint={lifeUsage !== null ? `${lifeUsage}% dari life time` : 'Shot sebelum rusak'}
                            >
                                <Input
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    value={data.actual_shot_count}
                                    onChange={(event) => setData('actual_shot_count', event.target.value)}
                                    invalid={Boolean(errors.actual_shot_count)}
                                    placeholder="mis. 185000"
                                    className="h-12 text-base font-semibold tabular-nums"
                                />
                            </Field>
                        </div>

                        <Field label="Alasan Kerusakan" required error={errors.damage_reason}>
                            <div className="grid grid-cols-2 gap-2">
                                {reasons.map((reason) => {
                                    const Icon = REASON_ICONS[reason.value] ?? TriangleAlert;
                                    const active = data.damage_reason === reason.value;

                                    return (
                                        <button
                                            key={reason.value}
                                            type="button"
                                            onClick={() => setData('damage_reason', reason.value)}
                                            aria-pressed={active}
                                            className={cn(
                                                'flex min-h-14 items-center gap-2 rounded-lg border px-3 text-left text-sm font-bold transition',
                                                active
                                                    ? 'border-red-600 bg-red-50 text-red-800 ring-2 ring-red-600'
                                                    : 'border-slate-300 bg-white text-slate-700',
                                            )}
                                        >
                                            <Icon className="size-5 shrink-0" aria-hidden="true" />
                                            {reason.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </Field>
                    </div>
                </Section>

                <Section number={4} title="Tindakan">
                    <div className="grid gap-2">
                        {actions.map((action) => {
                            const Icon = ACTION_ICONS[action.value] ?? Hammer;
                            const active = data.action === action.value;

                            return (
                                <button
                                    key={action.value}
                                    type="button"
                                    onClick={() => setData('action', action.value)}
                                    aria-pressed={active}
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg border px-4 py-3 text-left transition',
                                        active
                                            ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600'
                                            : 'border-slate-300 bg-white',
                                    )}
                                >
                                    <Icon
                                        className={cn('size-6 shrink-0', active ? 'text-blue-700' : 'text-slate-500')}
                                        aria-hidden="true"
                                    />
                                    <span>
                                        <span className="block text-sm font-bold text-slate-900">{action.label}</span>
                                        <span className="block text-xs text-slate-500">
                                            {action.cuts_stock
                                                ? 'Stok otomatis dipotong sesuai qty'
                                                : 'Part diperbaiki, stok tidak berubah'}
                                        </span>
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {errors.action && <p className="mt-1 text-xs font-semibold text-red-600">{errors.action}</p>}

                    {tool && cutsStock && (
                        <div
                            className={cn(
                                'mt-3 rounded-lg border px-4 py-3 text-sm',
                                insufficient
                                    ? 'border-red-300 bg-red-50 text-red-800'
                                    : 'border-emerald-300 bg-emerald-50 text-emerald-900',
                            )}
                        >
                            {insufficient ? (
                                <p className="flex items-center gap-2 font-semibold">
                                    <TriangleAlert className="size-4" /> Stok sistem hanya{' '}
                                    {formatInt(tool.current_stock)} pcs. Hubungi admin gudang.
                                </p>
                            ) : (
                                <p>
                                    Stok akan berubah <b>{formatInt(tool.current_stock)}</b> →{' '}
                                    <b>{formatInt(stockAfter)}</b> pcs
                                    {stockAfter < tool.min_stock_e && (
                                        <span className="font-bold text-red-700"> (di bawah Min Stock → ORDER)</span>
                                    )}
                                </p>
                            )}
                        </div>
                    )}

                    <Field label="Catatan" className="mt-4" error={errors.notes}>
                        <Textarea
                            rows={2}
                            value={data.notes}
                            onChange={(event) => setData('notes', event.target.value)}
                            placeholder="opsional"
                        />
                    </Field>
                </Section>

                <div
                    className={cn(
                        'fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur',
                        isAdmin && 'lg:pl-64',
                    )}
                >
                    <div className="mx-auto flex max-w-xl gap-2">
                        {tool && (
                            <Button
                                variant="outline"
                                size="lg"
                                onClick={() => setData('tool_id', '')}
                                aria-label="Ganti item"
                            >
                                <X className="size-5" />
                            </Button>
                        )}
                        <Button
                            type="submit"
                            size="lg"
                            className="flex-1"
                            loading={processing}
                            disabled={!tool || insufficient}
                        >
                            <Send className="size-5" /> Kirim Laporan
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    );

    if (isAdmin) {
        return (
            <AppLayout
                title="Form Kerusakan (QR)"
                subtitle="Input laporan kerusakan & penggantian part tooling"
            >
                {formContent}
            </AppLayout>
        );
    }

    return <PicLayout showMenuButton>{formContent}</PicLayout>;
}

function Section({ number, title, children }) {
    return (
        <Card className="p-4">
            <h2 className="mb-3 flex items-center gap-2 text-sm font-extrabold tracking-wide text-slate-800 uppercase">
                <span className="flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">
                    {number}
                </span>
                {title}
            </h2>
            {children}
        </Card>
    );
}

function Mini({ label, value, strong = 'text-slate-900' }) {
    return (
        <div>
            <p className="text-[10px] font-semibold text-slate-500 uppercase">{label}</p>
            <p className={cn('text-xl font-extrabold tabular-nums', strong)}>{value}</p>
        </div>
    );
}