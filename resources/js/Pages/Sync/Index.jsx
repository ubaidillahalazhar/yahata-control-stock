import { useForm, usePage } from '@inertiajs/react';
import {
    CircleCheck,
    ClipboardCheck,
    Download,
    FileDown,
    FileSpreadsheet,
    FileUp,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button, Card, CardHeader, Field, Input, Stat, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatInt, formatMonth, formatSigned } from '@/lib/format';
import { readStorage, STORAGE_KEYS, writeStorage } from '@/lib/storage';

export default function Index({ stats }) {
    const { flash } = usePage().props;
    const [preview, setPreview] = useState(flash?.opname_preview ?? null);
    const [opnameResult, setOpnameResult] = useState(flash?.opname_result ?? null);
    const [onlyDifferences, setOnlyDifferences] = useState(true);
    const masterInput = useRef(null);
    const opnameInput = useRef(null);

    useEffect(() => {
        if (flash?.opname_preview) {
            setPreview(flash.opname_preview);
            setOpnameResult(null);
        }
    }, [flash?.opname_preview]);

    useEffect(() => {
        if (flash?.opname_result) {
            setOpnameResult(flash.opname_result);
            setPreview(null);
        }
    }, [flash?.opname_result]);

    const importForm = useForm({ master_file: null, sync_stock: false, pic_name: readStorage(STORAGE_KEYS.picName) });
    const uploadForm = useForm({ opname_file: null });
    const applyForm = useForm({ pic_name: readStorage(STORAGE_KEYS.picName), reference_no: '', notes: '' });

    const submitImport = (event) => {
        event.preventDefault();
        importForm.post('/sync/master-import', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                importForm.reset('master_file');
                if (masterInput.current) masterInput.current.value = '';
            },
        });
    };

    const submitPreview = (event) => {
        event.preventDefault();
        uploadForm.post('/sync/opname-preview', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                uploadForm.reset('opname_file');
                if (opnameInput.current) opnameInput.current.value = '';
            },
        });
    };

    const applyOpname = () => {
        if (!preview || preview.rows.length === 0) return;

        writeStorage(STORAGE_KEYS.picName, applyForm.data.pic_name);
        applyForm.transform((data) => ({
            ...data,
            items: preview.rows.map((row) => ({
                tool_id: row.tool_id,
                actual_stock: row.actual_stock,
                template_system_stock: row.template_system_stock,
            })),
        }));
        applyForm.post('/sync/opname-apply', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => applyForm.reset('reference_no', 'notes'),
        });
    };

    const previewRows = useMemo(() => {
        if (!preview) return [];

        return onlyDifferences ? preview.rows.filter((row) => row.difference !== 0) : preview.rows;
    }, [preview, onlyDifferences]);

    const importResult = flash?.import_result;
    const applyErrors = Object.values(applyForm.errors);

    return (
        <AppLayout
            title="Excel Master Sync & Stock Opname"
            subtitle="Sinkronisasi file yahata tools.xlsx dan workflow stock opname berbasis Excel"
        >
            <section className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-3">
                <Stat label="Total item master" value={formatInt(stats.total_tools)} className="bg-white" />
                <Stat
                    label="Master terakhir diperbarui"
                    value={stats.last_master_update ?? '—'}
                    className="bg-white"
                    valueClassName="text-base"
                />
                <Stat
                    label="Opname terakhir"
                    value={stats.last_opname ? `${stats.last_opname.reference_no}` : '—'}
                    className="col-span-2 bg-white lg:col-span-1"
                    valueClassName="font-mono text-base"
                />
            </section>

            <div className="grid gap-5 xl:grid-cols-2">
                {/* ===== Import master ===== */}
                <Card>
                    <CardHeader
                        icon={FileSpreadsheet}
                        title="1. Import Master Tooling"
                        subtitle='Upload "yahata tools (1).xlsx" — sheet "control stock", 2 baris header'
                    />
                    <form method="post" onSubmit={submitImport} className="space-y-4 p-5">
                        <Field label="File Excel (.xlsx)" required error={importForm.errors.master_file}>
                            <input
                                ref={masterInput}
                                type="file"
                                accept=".xlsx,.xls"
                                onChange={(event) => importForm.setData('master_file', event.target.files?.[0] ?? null)}
                                className="block w-full cursor-pointer rounded-md border border-slate-300 bg-white text-sm file:mr-3 file:border-0 file:bg-slate-800 file:px-4 file:py-2.5 file:font-semibold file:text-white"
                            />
                        </Field>

                        <ul className="space-y-1 rounded-md bg-slate-50 p-3 text-xs text-slate-600">
                            <li>
                                • Upsert berdasarkan <b>Tools Name YES</b> (kolom H): master, Life Time, Lead Time & 5
                                bulan Forecast.
                            </li>
                            <li>
                                • Item baru memakai <b>Current Stock</b> (K) sebagai stok awal dan tercatat di Audit
                                Log.
                            </li>
                            <li>
                                • Stok item lama <b>tidak ditimpa</b>, kecuali opsi di bawah dicentang.
                            </li>
                        </ul>

                        <label className="flex items-start gap-3 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm">
                            <input
                                type="checkbox"
                                checked={importForm.data.sync_stock}
                                onChange={(event) => importForm.setData('sync_stock', event.target.checked)}
                                className="mt-0.5 size-4"
                            />
                            <span>
                                <b>Samakan juga Current Stock item lama dengan kolom K Excel</b>
                                <span className="block text-xs text-amber-800">
                                    Selisih dicatat sebagai OPNAME_ADJUST.
                                </span>
                            </span>
                        </label>

                        {importForm.data.sync_stock && (
                            <Field label="Nama PIC" required error={importForm.errors.pic_name}>
                                <Input
                                    value={importForm.data.pic_name}
                                    onChange={(event) => importForm.setData('pic_name', event.target.value)}
                                />
                            </Field>
                        )}

                        {importForm.progress && (
                            <div className="h-2 overflow-hidden rounded bg-slate-200">
                                <div
                                    className="h-full bg-blue-600 transition-all"
                                    style={{ width: `${importForm.progress.percentage ?? 0}%` }}
                                />
                            </div>
                        )}

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                loading={importForm.processing}
                                disabled={!importForm.data.master_file}
                            >
                                <Upload className="size-4" /> Import Master
                            </Button>
                            <Button as="a" href="/sync/control-stock-export" variant="outline">
                                <Download className="size-4" /> Export Control Stock (.xlsx)
                            </Button>
                        </div>
                    </form>

                    {importResult && (
                        <div className="border-t border-slate-200 p-5">
                            <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-emerald-700">
                                <CircleCheck className="size-4" /> Hasil Import ·{' '}
                                <span className="font-mono">{importResult.reference_no}</span>
                            </h3>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <Stat label="Item baru" value={formatInt(importResult.created)} />
                                <Stat label="Diperbarui" value={formatInt(importResult.updated)} />
                                <Stat
                                    label="Dilewati"
                                    value={formatInt(importResult.skipped)}
                                    valueClassName={importResult.skipped ? 'text-red-600' : undefined}
                                />
                                <Stat label="Stok disesuaikan" value={formatInt(importResult.stock_adjusted)} />
                            </div>
                            {importResult.forecast_months?.length > 0 && (
                                <p className="mt-3 text-xs text-slate-600">
                                    Bulan forecast:{' '}
                                    <b>
                                        {importResult.forecast_months
                                            .map((month, index) => formatMonth(month, `Bln ${index + 1}`))
                                            .join(' · ')}
                                    </b>
                                </p>
                            )}
                            <MessageList tone="warning" title="Peringatan" items={importResult.warnings} />
                            <MessageList tone="error" title="Baris dilewati" items={importResult.errors} />
                        </div>
                    )}
                </Card>

                {/* ===== Stock opname ===== */}
                <Card>
                    <CardHeader
                        icon={ClipboardCheck}
                        title="2. Stock Opname"
                        subtitle="Download template → hitung fisik → upload → review → terapkan"
                    />
                    <div className="space-y-5 p-5">
                        <div className="flex items-start gap-3 rounded-md border border-slate-200 p-4">
                            <StepNumber>A</StepNumber>
                            <div className="flex-1">
                                <p className="text-sm font-bold">Download Template Opname</p>
                                <p className="mb-3 text-xs text-slate-500">
                                    Berisi Tools Name YES, Rack Location, System Stock, dan kolom kuning{' '}
                                    <b>Actual Physical Stock</b>.
                                </p>
                                <Button as="a" href="/sync/opname-template" variant="dark" size="sm">
                                    <FileDown className="size-4" /> Download Template (.xlsx)
                                </Button>
                            </div>
                        </div>

                        <form
                            method="post"
                            onSubmit={submitPreview}
                            className="flex items-start gap-3 rounded-md border border-slate-200 p-4"
                        >
                            <StepNumber>B</StepNumber>
                            <div className="flex-1 space-y-3">
                                <div>
                                    <p className="text-sm font-bold">Upload Hasil Opname</p>
                                    <p className="text-xs text-slate-500">
                                        Sistem membandingkan stok fisik dengan stok sistem (belum mengubah data).
                                    </p>
                                </div>
                                <Field error={uploadForm.errors.opname_file}>
                                    <input
                                        ref={opnameInput}
                                        type="file"
                                        accept=".xlsx,.xls"
                                        onChange={(event) =>
                                            uploadForm.setData('opname_file', event.target.files?.[0] ?? null)
                                        }
                                        className="block w-full cursor-pointer rounded-md border border-slate-300 bg-white text-sm file:mr-3 file:border-0 file:bg-blue-600 file:px-4 file:py-2.5 file:font-semibold file:text-white"
                                    />
                                </Field>
                                <Button
                                    type="submit"
                                    size="sm"
                                    loading={uploadForm.processing}
                                    disabled={!uploadForm.data.opname_file}
                                >
                                    <FileUp className="size-4" /> Upload & Preview
                                </Button>
                            </div>
                        </form>

                        {opnameResult && (
                            <div className="rounded-md border border-emerald-300 bg-emerald-50 p-4">
                                <p className="flex items-center gap-2 text-sm font-bold text-emerald-800">
                                    <CircleCheck className="size-4" /> Opname {opnameResult.reference_no} diterapkan (
                                    {opnameResult.applied_at})
                                </p>
                                <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <Stat
                                        label="Diproses"
                                        value={formatInt(opnameResult.processed)}
                                        className="bg-white"
                                    />
                                    <Stat
                                        label="Disesuaikan"
                                        value={formatInt(opnameResult.adjusted)}
                                        className="bg-white"
                                    />
                                    <Stat
                                        label="Total +"
                                        value={`+${formatInt(opnameResult.total_plus)}`}
                                        className="bg-white"
                                        valueClassName="text-emerald-700"
                                    />
                                    <Stat
                                        label="Total −"
                                        value={`−${formatInt(opnameResult.total_minus)}`}
                                        className="bg-white"
                                        valueClassName="text-red-600"
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                </Card>
            </div>

            {preview && (
                <Card className="mt-5">
                    <CardHeader
                        icon={ClipboardCheck}
                        title="C. Review Selisih Opname"
                        subtitle={`File: ${preview.file_name}`}
                        actions={
                            <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={onlyDifferences}
                                    onChange={(event) => setOnlyDifferences(event.target.checked)}
                                    className="size-4"
                                />
                                Hanya yang berselisih
                            </label>
                        }
                    />

                    <div className="grid grid-cols-2 gap-2 border-b border-slate-200 p-4 sm:grid-cols-3 lg:grid-cols-6">
                        <Stat label="Item terisi" value={formatInt(preview.summary.rows_with_input)} />
                        <Stat
                            label="Sesuai"
                            value={formatInt(preview.summary.matched)}
                            valueClassName="text-emerald-700"
                        />
                        <Stat
                            label="Berselisih"
                            value={formatInt(preview.summary.with_difference)}
                            valueClassName="text-red-600"
                        />
                        <Stat label="Total +" value={`+${formatInt(preview.summary.total_plus)}`} />
                        <Stat label="Total −" value={`−${formatInt(preview.summary.total_minus)}`} />
                        <Stat label="Tidak dihitung" value={formatInt(preview.summary.not_counted)} />
                    </div>

                    {preview.summary.stock_moved > 0 && (
                        <p className="flex items-center gap-2 border-b border-slate-200 bg-amber-50 px-4 py-2 text-xs text-amber-900">
                            <TriangleAlert className="size-4" />
                            {preview.summary.stock_moved} item mengalami transaksi setelah template diunduh. Selisih
                            dihitung terhadap stok sistem SAAT INI.
                        </p>
                    )}

                    <MessageList
                        tone="error"
                        title="Baris bermasalah (diabaikan)"
                        items={preview.errors}
                        className="px-4"
                    />

                    <div className="max-h-120 overflow-auto">
                        <table className="w-full min-w-190 text-sm">
                            <thead className="sticky top-0 bg-slate-800 text-[11px] font-bold tracking-wide text-white uppercase">
                                <tr>
                                    <th className="px-3 py-2 text-left">Baris</th>
                                    <th className="px-3 py-2 text-left">Tools Name YES</th>
                                    <th className="px-3 py-2 text-left">Rak</th>
                                    <th className="px-3 py-2 text-right">Stok Template</th>
                                    <th className="px-3 py-2 text-right">Stok Sistem</th>
                                    <th className="px-3 py-2 text-right">Fisik</th>
                                    <th className="px-3 py-2 text-right">Selisih</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {previewRows.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-3 py-8 text-center text-slate-500">
                                            {preview.rows.length === 0
                                                ? 'Tidak ada baris yang terisi.'
                                                : 'Semua item sesuai — tidak ada selisih.'}
                                        </td>
                                    </tr>
                                )}
                                {previewRows.map((row) => (
                                    <tr key={row.tool_id} className={cn(row.difference !== 0 && 'bg-amber-50/40')}>
                                        <td className="px-3 py-2 text-slate-500 tabular-nums">{row.row}</td>
                                        <td className="px-3 py-2 font-mono text-[13px] font-bold">
                                            {row.tools_name_yes}
                                        </td>
                                        <td className="px-3 py-2">{row.rack_location ?? '—'}</td>
                                        <td
                                            className={cn(
                                                'px-3 py-2 text-right tabular-nums',
                                                row.stock_moved && 'text-amber-700',
                                            )}
                                        >
                                            {formatInt(row.template_system_stock)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatInt(row.current_stock)}
                                        </td>
                                        <td className="px-3 py-2 text-right font-bold tabular-nums">
                                            {formatInt(row.actual_stock)}
                                        </td>
                                        <td
                                            className={cn(
                                                'px-3 py-2 text-right font-extrabold tabular-nums',
                                                row.difference > 0 && 'text-emerald-700',
                                                row.difference < 0 && 'text-red-600',
                                            )}
                                        >
                                            {row.difference === 0 ? '0' : formatSigned(row.difference)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="grid gap-3 border-t border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_1fr_2fr_auto] md:items-end">
                        <Field label="Nama PIC Opname" required error={applyForm.errors.pic_name}>
                            <Input
                                value={applyForm.data.pic_name}
                                onChange={(event) => applyForm.setData('pic_name', event.target.value)}
                            />
                        </Field>
                        <Field
                            label="No. Referensi"
                            hint="Kosong = otomatis OPN-YYYYMMDD-HHMMSS"
                            error={applyForm.errors.reference_no}
                        >
                            <Input
                                value={applyForm.data.reference_no}
                                onChange={(event) => applyForm.setData('reference_no', event.target.value)}
                            />
                        </Field>
                        <Field label="Catatan" error={applyForm.errors.notes}>
                            <Input
                                value={applyForm.data.notes}
                                onChange={(event) => applyForm.setData('notes', event.target.value)}
                                placeholder="mis. Opname akhir bulan"
                            />
                        </Field>
                        <div className="flex gap-2">
                            <Button variant="ghost" onClick={() => setPreview(null)}>
                                Batal
                            </Button>
                            <Button
                                variant="success"
                                onClick={applyOpname}
                                loading={applyForm.processing}
                                disabled={preview.rows.length === 0 || !applyForm.data.pic_name}
                            >
                                <ClipboardCheck className="size-4" /> Terapkan Hasil Opname
                            </Button>
                        </div>
                        {applyErrors.length > 0 && (
                            <ul className="text-xs font-semibold text-red-600 md:col-span-4">
                                {applyErrors.slice(0, 5).map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                </Card>
            )}
        </AppLayout>
    );
}

function StepNumber({ children }) {
    return (
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-black text-white">
            {children}
        </span>
    );
}

function MessageList({ tone, title, items, className }) {
    if (!items || items.length === 0) return null;

    return (
        <details className={cn('mt-3 text-xs', className)} open={tone === 'error'}>
            <summary className={cn('cursor-pointer font-bold', tone === 'error' ? 'text-red-700' : 'text-amber-700')}>
                {title} ({items.length})
            </summary>
            <ul
                className={cn(
                    'mt-2 max-h-40 space-y-1 overflow-auto rounded p-2',
                    tone === 'error' ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-900',
                )}
            >
                {items.map((item, index) => (
                    <li key={index}>{item}</li>
                ))}
            </ul>
        </details>
    );
}
