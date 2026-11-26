import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, Info, UserPlus } from 'lucide-react';
import { useState } from 'react';
import FlashMessages from '@/Components/FlashMessages';
import PinInput from '@/Components/PinInput';
import { Button, Card, Field, Input } from '@/Components/ui';
import logoYahata from '@/assets/logo-yahata.jpeg';

export default function Register() {
    const [showPin, setShowPin] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        username: '',
        machine_name: '',
        pin: '',
        pin_confirmation: '',
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
                    <p className="mt-1 text-xs text-slate-400">Untuk membuat laporan kerusakan &amp; penggantian part</p>
                </div>

                <FlashMessages />

                <Card className="p-6 sm:p-7">
                    <div className="mb-4 flex gap-2.5 rounded-lg bg-blue-50 px-3.5 py-3 text-xs text-blue-900">
                        <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <p>
                            Setelah mendaftar, akun Anda <b>menunggu persetujuan admin</b>. Anda baru bisa login
                            setelah disetujui.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Nama Lengkap" required error={errors.name}>
                            <Input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                invalid={Boolean(errors.name)}
                                placeholder="mis. Ahmad Fauzi"
                                autoComplete="name"
                                autoFocus
                            />
                        </Field>

                        <Field
                            label="Username"
                            required
                            error={errors.username}
                            hint="Huruf kecil, angka, titik (.) atau garis bawah (_). Dipakai untuk login."
                        >
                            <Input
                                value={data.username}
                                onChange={(e) => setData('username', e.target.value.toLowerCase().replace(/\s/g, ''))}
                                invalid={Boolean(errors.username)}
                                placeholder="mis. ahmad.heading"
                                autoComplete="username"
                                autoCapitalize="none"
                                autoCorrect="off"
                                spellCheck={false}
                                maxLength={30}
                            />
                        </Field>

                        <Field label="Process / Section yang Dipegang" required error={errors.machine_name}>
                            <Input
                                value={data.machine_name}
                                onChange={(e) => setData('machine_name', e.target.value)}
                                invalid={Boolean(errors.machine_name)}
                                placeholder="mis. Heading / Rolling"
                            />
                        </Field>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="PIN (6 digit)" required error={errors.pin}>
                                <PinInput
                                    value={data.pin}
                                    onChange={(value) => setData('pin', value)}
                                    invalid={Boolean(errors.pin)}
                                    visible={showPin}
                                    autoComplete="new-password"
                                />
                            </Field>
                            <Field label="Ulangi PIN" required error={errors.pin_confirmation}>
                                <PinInput
                                    value={data.pin_confirmation}
                                    onChange={(value) => setData('pin_confirmation', value)}
                                    invalid={Boolean(errors.pin_confirmation)}
                                    visible={showPin}
                                    autoComplete="new-password"
                                />
                            </Field>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowPin((prev) => !prev)}
                            className="-mt-2 inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-800"
                        >
                            {showPin ? <EyeOff className="size-3.5" /> : <Eye className="size-3.5" />}
                            {showPin ? 'Sembunyikan PIN' : 'Lihat PIN'}
                        </button>

                        <Button type="submit" size="lg" className="w-full" loading={processing}>
                            <UserPlus className="size-4" /> Kirim Pendaftaran
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