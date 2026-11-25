import { router, useForm } from '@inertiajs/react';
import {
    Ban,
    Check,
    Clock,
    Eye,
    EyeOff,
    KeyRound,
    Pencil,
    RotateCcw,
    ShieldCheck,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import PinInput from '@/Components/PinInput';
import { Button, Card, EmptyState, Field, Input, Select, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';

const EMPTY_FORM = {
    name: '',
    role: 'pic',
    machine_name: '',
    username: '',
    email: '',
    nik: '',
    password: '',
    password_confirmation: '',
    pin: '',
    pin_confirmation: '',
};

const STATUS_STYLES = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    pending: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    rejected: 'bg-red-50 text-red-700 ring-red-600/20',
    disabled: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

export default function Index({ users = [], currentUserId = null }) {
    const [showSecret, setShowSecret] = useState(false);
    const [editing, setEditing] = useState(null);
    const [busyId, setBusyId] = useState(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm(EMPTY_FORM);
    const isAdminForm = (editing?.role ?? data.role) === 'admin';

    const pending = users.filter((user) => user.status === 'pending');
    const others = users.filter((user) => user.status !== 'pending');

    const startEdit = (user) => {
        clearErrors();
        setEditing(user);
        setData({
            ...EMPTY_FORM,
            name: user.name,
            role: user.role,
            machine_name: user.machine_name ?? '',
            username: user.username ?? '',
            email: user.email ?? '',
            nik: user.nik ?? '',
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const cancelEdit = () => {
        setEditing(null);
        clearErrors();
        reset();
    };

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => cancelEdit() };

        if (editing) {
            put(`/accounts/${editing.id}`, options);
        } else {
            post('/accounts', options);
        }
    };

    const act = (user, method, url, payload = {}) => {
        setBusyId(user.id);
        router[method](url, payload, { preserveScroll: true, onFinish: () => setBusyId(null) });
    };

    const approve = (user) => act(user, 'post', `/accounts/${user.id}/status`, { status: 'active' });

    const reject = (user) => {
        const reason = window.prompt(`Tolak registrasi "${user.name}" (${user.username})?\nAlasan (opsional):`, '');
        if (reason !== null) {
            act(user, 'post', `/accounts/${user.id}/status`, { status: 'rejected', reason });
        }
    };

    const toggleActive = (user) => {
        const disabling = user.status === 'active';
        const question = disabling
            ? `Nonaktifkan akun "${user.name}"? Sesi login-nya langsung diputus.`
            : `Aktifkan kembali akun "${user.name}"?`;

        if (window.confirm(question)) {
            act(user, 'post', `/accounts/${user.id}/status`, { status: disabling ? 'disabled' : 'active' });
        }
    };

    const resetSecret = (user) => {
        const label = user.role === 'admin' ? 'password' : 'PIN';
        if (window.confirm(`Buat ${label} sementara untuk "${user.name}"? ${label} lama tidak berlaku lagi.`)) {
            act(user, 'post', `/accounts/${user.id}/reset-secret`);
        }
    };

    const destroy = (user) => {
        if (window.confirm(`Hapus akun "${user.name}"? Tindakan ini tidak dapat dibatalkan.`)) {
            act(user, 'delete', `/accounts/${user.id}`);
        }
    };

    return (
        <AppLayout
            title="Pengaturan Akun"
            subtitle="Setujui registrasi PIC, kelola akun Admin & PIC, reset PIN"
        >
            {pending.length > 0 && (
                <Card className="mb-6 border-amber-300">
                    <div className="flex items-center gap-2.5 border-b border-amber-200 bg-amber-50 px-5 py-3.5">
                        <Clock className="size-5 text-amber-700" aria-hidden="true" />
                        <div>
                            <h2 className="text-sm font-extrabold text-amber-900">
                                Menunggu Persetujuan ({pending.length})
                            </h2>
                            <p className="text-xs text-amber-800">
                                Periksa nama &amp; mesin/line sebelum menyetujui. Akun baru bisa login setelah disetujui.
                            </p>
                        </div>
                    </div>
                    <ul className="divide-y divide-slate-100">
                        {pending.map((user) => (
                            <li key={user.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div className="min-w-0">
                                    <p className="font-bold text-slate-900">{user.name}</p>
                                    <p className="text-xs text-slate-500">
                                        <span className="font-mono">{user.username}</span> · {user.machine_name || '—'} ·
                                        daftar {user.created_at}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button size="xs" variant="outline" onClick={() => startEdit(user)}>
                                        <Pencil className="size-3.5" /> Koreksi
                                    </Button>
                                    <Button
                                        size="xs"
                                        variant="outline"
                                        loading={busyId === user.id}
                                        onClick={() => reject(user)}
                                        className="border-red-200 text-red-600 hover:bg-red-50"
                                    >
                                        <X className="size-3.5" /> Tolak
                                    </Button>
                                    <Button size="xs" loading={busyId === user.id} onClick={() => approve(user)}>
                                        <Check className="size-3.5" /> Setujui
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <div className="grid gap-6 lg:grid-cols-12">
                <div className="lg:col-span-5">
                    <Card className="p-5 sm:p-6">
                        <div className="mb-4 flex items-center gap-2.5 border-b border-slate-200 pb-3.5">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                {editing ? <Pencil className="size-5" /> : <UserPlus className="size-5" />}
                            </span>
                            <div className="flex-1">
                                <h2 className="text-sm font-extrabold text-slate-900">
                                    {editing ? `Edit Akun: ${editing.name}` : 'Tambah Akun Baru'}
                                </h2>
                                <p className="text-xs text-slate-500">
                                    {editing
                                        ? 'PIN/password tidak diubah di sini — gunakan tombol Reset.'
                                        : 'Akun yang dibuat admin langsung aktif'}
                                </p>
                            </div>
                            {editing && (
                                <Button size="xs" variant="outline" onClick={cancelEdit}>
                                    Batal
                                </Button>
                            )}
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            <Field label="Nama Lengkap" required error={errors.name}>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    invalid={Boolean(errors.name)}
                                    placeholder="mis. Budi Santoso"
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Hak Akses (Role)" required error={errors.role}>
                                    <Select
                                        value={data.role}
                                        onChange={(e) => setData('role', e.target.value)}
                                        disabled={Boolean(editing)}
                                    >
                                        <option value="pic">PIC Mesin (Operator)</option>
                                        <option value="admin">Admin (Full Akses)</option>
                                    </Select>
                                </Field>
                                <Field label="Mesin / Line" required={!isAdminForm} error={errors.machine_name}>
                                    <Input
                                        value={data.machine_name}
                                        onChange={(e) => setData('machine_name', e.target.value)}
                                        invalid={Boolean(errors.machine_name)}
                                        placeholder={isAdminForm ? 'All Lines / Admin' : 'mis. Heading #01'}
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Username"
                                required={!isAdminForm}
                                error={errors.username}
                                hint={isAdminForm ? 'Opsional untuk admin' : 'Untuk login PIC'}
                            >
                                <Input
                                    value={data.username}
                                    onChange={(e) => setData('username', e.target.value.toLowerCase().replace(/\s/g, ''))}
                                    invalid={Boolean(errors.username)}
                                    placeholder="mis. budi.heading"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    maxLength={30}
                                />
                            </Field>

                            {isAdminForm && (
                                <Field label="Email" required error={errors.email} hint="Login cadangan bila Superapps tidak bisa diakses">
                                    <Input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        invalid={Boolean(errors.email)}
                                        placeholder="nama@yahata.co.id"
                                    />
                                </Field>
                            )}

                            <Field
                                label="NIK Karyawan"
                                error={errors.nik}
                                hint="Harus sama persis dengan NIK di Superapps (SSO). Kosongkan bila tidak memakai Superapps."
                            >
                                <Input
                                    value={data.nik}
                                    onChange={(e) => setData('nik', e.target.value)}
                                    invalid={Boolean(errors.nik)}
                                    placeholder="mis. 90011"
                                    inputMode="numeric"
                                />
                            </Field>

                            {!editing &&
                                (isAdminForm ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Password" required error={errors.password} hint="Minimal 8 karakter">
                                            <Input
                                                type={showSecret ? 'text' : 'password'}
                                                value={data.password}
                                                onChange={(e) => setData('password', e.target.value)}
                                                invalid={Boolean(errors.password)}
                                                autoComplete="new-password"
                                            />
                                        </Field>
                                        <Field label="Ulangi Password" required error={errors.password_confirmation}>
                                            <Input
                                                type={showSecret ? 'text' : 'password'}
                                                value={data.password_confirmation}
                                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                                autoComplete="new-password"
                                            />
                                        </Field>
                                    </div>
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="PIN (6 digit)" required error={errors.pin}>
                                            <PinInput
                                                value={data.pin}
                                                onChange={(value) => setData('pin', value)}
                                                invalid={Boolean(errors.pin)}
                                                visible={showSecret}
                                                autoComplete="new-password"
                                            />
                                        </Field>
                                        <Field label="Ulangi PIN" required error={errors.pin_confirmation}>
                                            <PinInput
                                                value={data.pin_confirmation}
                                                onChange={(value) => setData('pin_confirmation', value)}
                                                visible={showSecret}
                                                autoComplete="new-password"
                                            />
                                        </Field>
                                    </div>
                                ))}

                            {!editing && (
                                <button
                                    type="button"
                                    onClick={() => setShowSecret((prev) => !prev)}
                                    className="-mt-2 inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-800"
                                >
                                    {showSecret ? <EyeOff className="size-3.5" /> : <Eye className="size-3.5" />}
                                    {showSecret ? 'Sembunyikan' : 'Lihat'} {isAdminForm ? 'password' : 'PIN'}
                                </button>
                            )}

                            <Button type="submit" className="w-full" loading={processing}>
                                {editing ? <Check className="size-4" /> : <UserPlus className="size-4" />}
                                {editing ? 'Simpan Perubahan' : 'Simpan Akun Baru'}
                            </Button>
                        </form>
                    </Card>
                </div>

                <div className="lg:col-span-7">
                    <Card>
                        <div className="flex items-center gap-2.5 border-b border-slate-200 px-5 py-4">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                                <Users className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-sm font-extrabold text-slate-900">Daftar Akun</h2>
                                <p className="text-xs text-slate-500">
                                    <b>{others.filter((user) => user.status === 'active').length}</b> aktif dari{' '}
                                    <b>{others.length}</b> akun
                                </p>
                            </div>
                        </div>

                        {others.length === 0 ? (
                            <EmptyState icon={Users} title="Belum ada akun" description="Tambahkan akun melalui formulir." />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-800 text-[11px] font-bold tracking-wide text-white uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left">Nama &amp; Role</th>
                                            <th className="px-4 py-2.5 text-left">Login</th>
                                            <th className="px-4 py-2.5 text-left">Status</th>
                                            <th className="px-4 py-2.5 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {others.map((user) => (
                                            <AccountRow
                                                key={user.id}
                                                user={user}
                                                isSelf={user.id === currentUserId}
                                                busy={busyId === user.id}
                                                onEdit={() => startEdit(user)}
                                                onReset={() => resetSecret(user)}
                                                onToggle={() => toggleActive(user)}
                                                onDelete={() => destroy(user)}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function AccountRow({ user, isSelf, busy, onEdit, onReset, onToggle, onDelete }) {
    const isAdmin = user.role === 'admin';
    const isActive = user.status === 'active';

    return (
        <tr className="align-top hover:bg-slate-50/80">
            <td className="px-4 py-3">
                <div className="flex flex-wrap items-center gap-1.5 font-bold text-slate-900">
                    <span>{user.name}</span>
                    <span
                        className={cn(
                            'rounded px-1.5 py-0.5 text-[10px] font-extrabold uppercase',
                            isAdmin ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800',
                        )}
                    >
                        {isAdmin ? 'Admin' : 'PIC'}
                    </span>
                    {isSelf && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                            <ShieldCheck className="size-3" /> Akun Anda
                        </span>
                    )}
                </div>
                <p className="mt-0.5 text-xs text-slate-500">{user.machine_name || '—'}</p>
            </td>
            <td className="px-4 py-3 font-mono text-xs text-slate-600">
                {user.username && <p>{user.username}</p>}
                {user.email && <p>{user.email}</p>}
                {user.nik && <p className="text-slate-400">NIK {user.nik}</p>}
            </td>
            <td className="px-4 py-3">
                <span
                    className={cn(
                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1',
                        STATUS_STYLES[user.status],
                    )}
                >
                    {user.status_label}
                </span>
                {user.must_change_password && (
                    <p className="mt-1 text-[11px] text-amber-700">Wajib ganti {isAdmin ? 'password' : 'PIN'}</p>
                )}
                {user.status === 'rejected' && user.rejection_reason && (
                    <p className="mt-1 text-[11px] text-red-600">{user.rejection_reason}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex flex-wrap justify-end gap-1.5">
                    <Button size="xs" variant="outline" onClick={onEdit} title="Edit data akun">
                        <Pencil className="size-3.5" />
                    </Button>
                    {!isSelf && (
                        <>
                            <Button
                                size="xs"
                                variant="outline"
                                onClick={onReset}
                                disabled={busy}
                                title={`Reset ${isAdmin ? 'password' : 'PIN'}`}
                            >
                                <KeyRound className="size-3.5" />
                            </Button>
                            <Button
                                size="xs"
                                variant="outline"
                                onClick={onToggle}
                                disabled={busy}
                                title={isActive ? 'Nonaktifkan akun' : 'Aktifkan akun'}
                            >
                                {isActive ? <Ban className="size-3.5" /> : <RotateCcw className="size-3.5" />}
                            </Button>
                            <Button
                                size="xs"
                                variant="outline"
                                onClick={onDelete}
                                disabled={busy}
                                title="Hapus akun"
                                className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        </>
                    )}
                </div>
            </td>
        </tr>
    );
}
