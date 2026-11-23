import { router } from '@inertiajs/react';
import { Filter, Gauge, History, X } from 'lucide-react';
import { useCallback, useState } from 'react';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import ToolCombobox from '@/Components/ToolCombobox';
import UsageForm, { CancelUsageForm } from '@/Components/UsageForm';
import UsageLogTable from '@/Components/UsageLogTable';
import { Button, Card, CardHeader, EmptyState, Field, Input, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatInt } from '@/lib/format';
import { LIFE_TONES } from '@/lib/life';
import { cleanParams } from '@/lib/urls';

const EMPTY_FILTERS = { tool_id: '', month: '', type: '', state: '' };

export default function Index({ tools, logs, filters, totals, initialToolId }) {
    const [form, setForm] = useState({ ...EMPTY_FILTERS, ...filters });
    const [cancelling, setCancelling] = useState(null);
    const closeCancel = useCallback(() => setCancelling(null), []);

    const apply = (next) => {
        setForm(next);
        router.get('/tool-usages', cleanParams(next), { preserveState: true, preserveScroll: true, replace: true });
    };
    const change = (key) => (value) => apply({ ...form, [key]: value });
    const hasFilter = Object.values(form).some((value) => value !== '' && value !== null);

    return (
        <AppLayout
            title="Input Forecast / Pemakaian"
            subtitle="Catat pemakaian life time tool kapan saja (tanggal bebas). Sisa life & Status Life langsung diperbarui."
        >
            <div className="grid gap-5 xl:grid-cols-[minmax(0,520px)_1fr]">
                <div className="space-y-5">
                    <Card>
                        <CardHeader
                            icon={Gauge}
                            title="Form Pemakaian Life Time"
                            subtitle="1 input = 1 baris riwayat · salah input → batalkan, lalu input ulang"
                        />
                        <div className="p-5">
                            <UsageForm tools={tools} initialToolId={initialToolId} />
                        </div>
                    </Card>

                    <Card className="p-4">
                        <p className="mb-2 text-xs font-bold tracking-wide text-slate-500 uppercase">
                            Warna &amp; Status Life (tetap)
                        </p>
                        <ul className="grid grid-cols-3 gap-2 text-center text-xs">
                            {Object.entries(LIFE_TONES).map(([key, tone]) => (
                                <li key={key} className={`rounded-md px-2 py-2 font-semibold ring-1 ring-inset ${tone.soft}`}>
                                    <span className="block text-sm font-extrabold">{tone.range}</span>
                                    {tone.label} · {key === 'red' ? 'ORDER' : 'SECURE'}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>

                <Card className="min-w-0">
                    <CardHeader
                        icon={History}
                        title="Riwayat Input"
                        subtitle={`Total pemakaian aktif (sesuai filter): ${formatInt(totals.qty)} pcs dari ${formatInt(totals.rows)} input`}
                    />

                    <div className="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[minmax(0,1fr)_160px_150px_150px_auto] md:items-end">
                        <Field label="Tool">
                            <ToolCombobox tools={tools} value={form.tool_id} onChange={change('tool_id')} />
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

                    {logs.data.length === 0 ? (
                        <EmptyState
                            icon={hasFilter ? Filter : History}
                            title={hasFilter ? 'Tidak ada input yang cocok' : 'Belum ada input pemakaian'}
                            description={hasFilter ? 'Ubah atau reset filter.' : 'Input pertama akan muncul di sini.'}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <UsageLogTable logs={logs.data} showTool onCancel={setCancelling} />
                        </div>
                    )}
                    <Pagination meta={logs.meta} links={logs.links} />
                </Card>
            </div>

            <Modal
                open={cancelling !== null}
                onClose={closeCancel}
                title="Batalkan Input"
                subtitle={cancelling?.tool?.tools_name_yes}
            >
                {cancelling && <CancelUsageForm key={cancelling.id} log={cancelling} onSuccess={closeCancel} />}
            </Modal>
        </AppLayout>
    );
}
