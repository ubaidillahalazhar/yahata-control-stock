import { router, usePage } from '@inertiajs/react';
import { Ban, FileSpreadsheet, Filter, Gauge, History, X } from 'lucide-react';
import { useCallback, useState } from 'react';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import ToolCombobox from '@/Components/ToolCombobox';
import UsageForm, { CancelBatchForm, CancelUsageForm, CorrectUsageForm } from '@/Components/UsageForm';
import UsageLogTable from '@/Components/UsageLogTable';
import { Button, Card, CardHeader, EmptyState, Field, Input, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import PicLayout from '@/Layouts/PicLayout';
import { formatInt } from '@/lib/format';
import { LIFE_TONES } from '@/lib/life';
import { cleanParams } from '@/lib/urls';

const EMPTY_FILTERS = { tool_id: '', batch: '', month: '', type: '', state: '' };

export default function Index({ tools, logs, filters, totals, changeWindowHours = 24 }) {
    const isAdmin = Boolean(usePage().props.auth?.user?.is_admin);
    const [form, setForm] = useState({ ...EMPTY_FILTERS, ...filters });
    const [cancelling, setCancelling] = useState(null);
    const closeCancel = useCallback(() => setCancelling(null), []);
    const [correcting, setCorrecting] = useState(null);
    const closeCorrect = useCallback(() => setCorrecting(null), []);
    const [inputOpen, setInputOpen] = useState(false);
    const closeInput = useCallback(() => setInputOpen(false), []);
    const [batchCancelOpen, setBatchCancelOpen] = useState(false);
    const closeBatchCancel = useCallback(() => setBatchCancelOpen(false), []);
    const activeBatch = filters.batch ?? '';

    const apply = (next) => {
        setForm(next);
        router.get('/tool-usages', cleanParams(next), { preserveState: true, preserveScroll: true, replace: true });
    };
    const change = (key) => (value) => apply({ ...form, [key]: value });
    const hasFilter = Object.values(form).some((value) => value !== '' && value !== null);

    const content = (
        <>
            <Card className="min-w-0">
                <CardHeader
                    icon={History}
                    title="Riwayat Input Pemakaian Life Time (Day)"
                    subtitle={`Total pemakaian aktif (sesuai filter): ${formatInt(totals.qty)} pcs dari ${formatInt(totals.rows)} input`}
                    actions={
                        <ul className="flex flex-wrap gap-1.5 text-xs">
                            {Object.entries(LIFE_TONES).map(([key, tone]) => (
                                <li key={key} className={`rounded px-2 py-1 font-semibold ring-1 ring-inset ${tone.soft}`}>
                                    {tone.range} · {key === 'red' ? 'ORDER' : 'SECURE'}
                                </li>
                            ))}
                        </ul>
                    }
                />

                <div className="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_200px_160px_150px_150px_auto] lg:items-end">
                    <Field label="Tool">
                        <ToolCombobox tools={tools} value={form.tool_id} onChange={change('tool_id')} />
                    </Field>
                    <Field label="Batch Excel">
                        <Input
                            value={form.batch}
                            onChange={(event) => setForm({ ...form, batch: event.target.value })}
                            onBlur={(event) => event.target.value !== (filters.batch ?? '') && apply({ ...form, batch: event.target.value.trim() })}
                            onKeyDown={(event) => event.key === 'Enter' && apply({ ...form, batch: event.currentTarget.value.trim() })}
                            placeholder="PLT-…"
                            className="font-mono"
                        />
                    </Field>
                    <Field label="Bulan">
                        <Input
                            type="month"
                            value={form.month}
                            onChange={(event) => change('month')(event.target.value)}
                        />
                    </Field>
                    <Field label="Jenis">
                        <Select value={form.type} onChange={(event) => change('type')(event.target.value)}>
                            <option value="">Semua</option>
                            <option value="USAGE">Pemakaian</option>
                            <option value="RESET">Ganti Tool</option>
                            <option value="ADJUST">Koreksi Count</option>
                        </Select>
                    </Field>
                    <Field label="Status">
                        <Select value={form.state} onChange={(event) => change('state')(event.target.value)}>
                            <option value="">Semua</option>
                            <option value="active">Aktif</option>
                            <option value="cancelled">Dibatalkan</option>
                        </Select>
                    </Field>
                    <Button variant="ghost" onClick={() => apply(EMPTY_FILTERS)} disabled={!hasFilter}>
                        <X className="size-4" /> Reset
                    </Button>
                </div>

                {activeBatch && (
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-sky-50 px-4 py-2 text-sm text-sky-900">
                        <span className="flex items-center gap-2">
                            <FileSpreadsheet className="size-4" />
                            Import Excel <b className="font-mono">{activeBatch}</b> · {formatInt(totals.rows)} input aktif ·{' '}
                            {formatInt(totals.qty)} pcs
                        </span>
                        {isAdmin && totals.rows > 0 && (
                            <Button variant="outline-danger" size="xs" onClick={() => setBatchCancelOpen(true)}>
                                <Ban className="size-3.5" /> Batalkan seluruh batch
                            </Button>
                        )}
                    </div>
                )}

                {logs.data.length === 0 ? (
                    <EmptyState
                        icon={hasFilter ? Filter : History}
                        title={hasFilter ? 'Tidak ada input yang cocok' : 'Belum ada input pemakaian'}
                        description={hasFilter ? 'Ubah atau reset filter.' : 'Input pertama akan muncul di sini.'}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <UsageLogTable logs={logs.data} showTool onCancel={setCancelling} onCorrect={setCorrecting} />
                    </div>
                )}
                <Pagination meta={logs.meta} links={logs.links} />
            </Card>

            <Modal
                open={inputOpen}
                onClose={closeInput}
                title="Input Pemakaian Life Time"
                subtitle="1 input = 1 baris riwayat"
            >
                {inputOpen && (
                    <UsageForm tools={tools} initialToolId={form.tool_id || null} onSuccess={closeInput} />
                )}
            </Modal>

            <Modal open={batchCancelOpen} onClose={closeBatchCancel} title="Batalkan Batch Import" subtitle={activeBatch}>
                {batchCancelOpen && activeBatch && (
                    <CancelBatchForm
                        batchNo={activeBatch}
                        activeRows={totals.rows}
                        totalQty={totals.qty}
                        onSuccess={closeBatchCancel}
                    />
                )}
            </Modal>

            <Modal
                open={cancelling !== null}
                onClose={closeCancel}
                title="Batalkan Input"
                subtitle={cancelling?.tool?.tools_name_yes}
            >
                {cancelling && <CancelUsageForm key={cancelling.id} log={cancelling} onSuccess={closeCancel} />}
            </Modal>

            <Modal
                open={correcting !== null}
                onClose={closeCorrect}
                title="Koreksi Input Pemakaian"
                subtitle={correcting?.tool?.tools_name_yes}
            >
                {correcting && <CorrectUsageForm key={correcting.id} log={correcting} onSuccess={closeCorrect} />}
            </Modal>
        </>
    );

    if (isAdmin) {
        return (
            <AppLayout
                title="Pemakaian Life Time (Day)"
                subtitle="Riwayat lengkap seluruh input pemakaian & ganti tool · salah jumlah / tanggal → Koreksi"
                actions={
                    <Button size="sm" onClick={() => setInputOpen(true)}>
                        <Gauge className="size-4" /> Input Pemakaian
                    </Button>
                }
            >
                {content}
            </AppLayout>
        );
    }

    return (
        <PicLayout showMenuButton>
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">
                        Count Lifetime — Pemakaian Life Time (Day)
                    </h1>
                    <p className="text-xs text-slate-500 sm:text-sm">
                        Catat &amp; pantau pemakaian harian tooling · input Anda bisa dikoreksi / dibatalkan
                        maks. {changeWindowHours} jam setelah dicatat; lewat dari itu hubungi admin
                    </p>
                </div>
                <Button size="sm" onClick={() => setInputOpen(true)}>
                    <Gauge className="size-4" /> Input Pemakaian
                </Button>
            </div>
            {content}
        </PicLayout>
    );
}