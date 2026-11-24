import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, UserPlus } from 'lucide-react';
import { useState } from 'react';
import FlashMessages from '@/Components/FlashMessages';
import { Button, Card, Field, Input } from '@/Components/ui';
import logoYahata from '@/assets/logo-yahata.jpeg';

export default function Register() {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        machine_name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-900 px-4 py-10">
            <Head title="Registrasi Akun PIC Mesin — Yahata Control Stock" />

            <div className="w-full max-w-md">
                <div className="mb-6 flex flex-col items-center text-center">
                    <div className="mb-3 flex size-14 items-center justify-center overflow-hidden rounded-2xl bg-white p-1.5 shadow-lg">
                        <img src={logoYahata} alt="Logo PT. Yahata Fastener Indonesia" className="size-full object-contain" />
                    </div>
                    <p className="text-xs font-bold tracking-widest text-blue-400 uppercase">
                        PT. Yahata Fastener Indonesia
                    </p>
                    <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-white">
                        Registrasi Akun PIC Mesin
                    </h1>
                    <p className="mt-1 text-xs text-slate-400">
                        Buat akun operator untuk transaksi stok &amp; laporan kerusakan part
                    </p>
                </div>

                <FlashMessages />

                <Card className="p-6 sm:p-7">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Nama PIC / Operator" required error={errors.name}>
                            <Input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                invalid={Boolean(errors.name)}
                                placeholder="mis. Ahmad Fauzi"
                                autoComplete="name"
                                autoFocus
                            />
                        </Field>

                        <Field label="Mesin / Line Produksi yang Dipegang" required error={errors.machine_name}>
                            <Input
                                value={data.machine_name}
                                onChange={(e) => setData('machine_name', e.target.value)}
                                invalid={Boolean(errors.machine_name)}
                                placeholder="mis. Heading M6 #01 / Rolling #03"
                            />
                        </Field>

                        <Field label="Alamat Email" required error={errors.email}>
                            <Input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                invalid={Boolean(errors.email)}
                                placeholder="operator@yahata.co.id"
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

                        <Button type="submit" size="lg" className="w-full" loading={processing}>
                            <UserPlus className="size-4" /> Daftar &amp; Masuk
                        </Button>
                    </form>

                    <div className="mt-5 border-t border-slate-200 pt-4 text-center">
                        <Link
                            href="/login"
                            className="inline-flex items-center gap-1.5 text-sm font-bold text-slate-700 hover:text-blue-700"
                        >
                            <ArrowLeft className="size-4" /> Sudah punya akun? Login di sini
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    );
}