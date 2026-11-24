import { router, useForm } from '@inertiajs/react';
import { Eye, EyeOff, ShieldCheck, Trash2, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';
import { Button, Card, EmptyState, Field, Input, Select, cn } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';

export default function Index({ users = [], currentUserId = null }) {
    const [showPassword, setShowPassword] = useState(false);
    const [deletingId, setDeletingId] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role: 'pic',
        machine_name: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/accounts', {
            preserveScroll: true,
            onSuccess: () => reset('name', 'email', 'machine_name', 'password', 'password_confirmation'),
        });
    };

    const handleDelete = (user) => {
        if (!window.confirm(`Hapus akun "${user.name}" (${user.email})? Tindakan ini tidak dapat dibatalkan.`)) {
            return;
        }

        setDeletingId(user.id);
        router.delete(`/accounts/${user.id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    return (
        <AppLayout
            title="Pengaturan Akun"
            subtitle="Kelola akun Admin (Mas Sigit, Pak Apri) dan akun PIC setiap mesin produksi"
        >
            <div className="grid gap-6 lg:grid-cols-12">
                {/* Kolom Kiri: Form Tambah Akun Baru */}
                <div className="lg:col-span-5">
                    <Card className="p-5 sm:p-6">
                        <div className="mb-4 flex items-center gap-2.5 border-b border-slate-200 pb-3.5">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                <UserPlus className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-sm font-extrabold text-slate-900">Tambah Akun Baru</h2>
                                <p className="text-xs text-slate-500">Buat akses untuk Admin atau PIC Mesin</p>
                            </div>
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            <Field label="Nama Lengkap / PIC" required error={errors.name}>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    invalid={Boolean(errors.name)}
                                    placeholder="mis. Budi Santoso"
                                    autoComplete="name"
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Hak Akses (Role)" required error={errors.role}>
                                    <Select value={data.role} onChange={(e) => setData('role', e.target.value)}>
                                        <option value="pic">PIC Mesin (Operator)</option>
                                        <option value="admin">Admin (Full Akses)</option>
                                    </Select>
                                </Field>

                                <Field label="Mesin / Line" error={errors.machine_name}>
                                    <Input
                                        value={data.machine_name}
                                        onChange={(e) => setData('machine_name', e.target.value)}
                                        invalid={Boolean(errors.machine_name)}
                                        placeholder={data.role === 'admin' ? 'All Lines / Admin' : 'mis. Heading #01'}
                                    />
                                </Field>
                            </div>

                            <Field label="Alamat Email" required error={errors.email}>
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    invalid={Boolean(errors.email)}
                                    placeholder="nama@yahata.co.id"
                                    autoComplete="email"
                                />
                            </Field>

                            <Field label="Password" required error={errors.password}>
                                <div className="relative">
                                    <Input
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        invalid={Boolean(errors.password)}
                                        placeholder="Minimal 6 karakter"
                                        className="pr-10"
                                        autoComplete="new-password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((prev) => !prev)}
                                        className="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-700"
                                        aria-label={showPassword ? 'Sembunyikan password' : 'Lihat password'}
                                    >
                                        {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                            </Field>

                            <Field label="Konfirmasi Password" required error={errors.password_confirmation}>
                                <Input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    invalid={Boolean(errors.password_confirmation)}
                                    placeholder="Ulangi password"
                                    autoComplete="new-password"
                                />
                            </Field>

                            <Button type="submit" className="w-full" loading={processing}>
                                <UserPlus className="size-4" /> Simpan Akun Baru
                            </Button>
                        </form>
                    </Card>
                </div>

                {/* Kolom Kanan: Daftar Akun Terdaftar */}
                <div className="lg:col-span-7">
                    <Card>
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                                    <Users className="size-5" />
                                </span>
                                <div>
                                    <h2 className="text-sm font-extrabold text-slate-900">Daftar Akun Terdaftar</h2>
                                    <p className="text-xs text-slate-500">
                                        Total <b>{users.length}</b> akun aktif dalam sistem
                                    </p>
                                </div>
                            </div>
                        </div>

                        {users.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="Belum ada akun"
                                description="Tambahkan akun pertama melalui formulir di samping."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-800 text-[11px] font-bold tracking-wide text-white uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left">Nama &amp; Role</th>
                                            <th className="px-4 py-2.5 text-left">Mesin / Line</th>
                                            <th className="px-4 py-2.5 text-left">Email</th>
                                            <th className="px-4 py-2.5 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {users.map((user) => {
                                            const isSelf = currentUserId !== null && user.id === currentUserId;
                                            const isOnlyOne = users.length <= 1;
                                            const disableDelete = isSelf || isOnlyOne || deletingId === user.id;
                                            const isUserAdmin = user.role === 'admin';

                                            return (
                                                <tr key={user.id} className="hover:bg-slate-50/80">
                                                    <td className="px-4 py-3 font-bold text-slate-900">
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <span>{user.name}</span>
                                                            <span
                                                                className={cn(
                                                                    'rounded px-1.5 py-0.5 text-[10px] font-extrabold uppercase',
                                                                    isUserAdmin
                                                                        ? 'bg-amber-100 text-amber-800'
                                                                        : 'bg-blue-100 text-blue-800',
                                                                )}
                                                            >
                                                                {isUserAdmin ? 'Admin' : 'PIC'}
                                                            </span>
                                                            {isSelf && (
                                                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                                                                    <ShieldCheck className="size-3" /> Akun Anda
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-xs text-slate-600">
                                                        {user.machine_name || '—'}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-xs text-slate-600">
                                                        {user.email}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="xs"
                                                            disabled={disableDelete}
                                                            loading={deletingId === user.id}
                                                            onClick={() => handleDelete(user)}
                                                            title={
                                                                isSelf
                                                                    ? 'Akun aktif tidak dapat dihapus'
                                                                    : isOnlyOne
                                                                      ? 'Minimal harus tersisa 1 akun'
                                                                      : 'Hapus akun ini'
                                                            }
                                                            className={cn(
                                                                !disableDelete &&
                                                                    'border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700',
                                                            )}
                                                        >
                                                            <Trash2 className="size-3.5" /> Hapus
                                                        </Button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
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