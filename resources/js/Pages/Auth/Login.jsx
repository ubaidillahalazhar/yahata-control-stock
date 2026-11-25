import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LogIn, UserPlus } from 'lucide-react';
import { useState } from 'react';
import FlashMessages from '@/Components/FlashMessages';
import { Button, Card, Field, Input } from '@/Components/ui';
import logoYahata from '@/assets/logo-yahata.jpeg';

export default function Login() {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login', { onFinish: () => setData('password', '') });
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-900 px-4 py-10">
            <Head title="Login — Yahata Control Stock" />

            <div className="w-full max-w-md">
                <div className="mb-6 flex flex-col items-center text-center">
                    <div className="mb-3 flex size-14 items-center justify-center overflow-hidden rounded-2xl bg-white p-1.5 shadow-lg">
                        <img src={logoYahata} alt="Logo PT. Yahata Fastener Indonesia" className="size-full object-contain" />
                    </div>
                    <p className="text-xs font-bold tracking-widest text-blue-400 uppercase">
                        PT. Yahata Fastener Indonesia
                    </p>
                    <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-white">
                        Control Stock Tooling
                    </h1>
                    <p className="mt-1 text-xs text-slate-400">
                        PIC Mesin: username + PIN · Admin: email + password
                    </p>
                </div>

                <FlashMessages />

                <Card className="p-6 sm:p-7">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Username / Email" required error={errors.login}>
                            <Input
                                value={data.login}
                                onChange={(e) => setData('login', e.target.value)}
                                invalid={Boolean(errors.login)}
                                placeholder="mis. budi.heading"
                                autoComplete="username"
                                autoCapitalize="none"
                                autoCorrect="off"
                                spellCheck={false}
                                className="h-12 text-base"
                                autoFocus
                            />
                        </Field>

                        <Field label="PIN / Password" required error={errors.password}>
                            <div className="relative">
                                <Input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    invalid={Boolean(errors.password)}
                                    placeholder="PIN 6 digit / password"
                                    className="h-12 pr-10 text-base"
                                    autoComplete="current-password"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((prev) => !prev)}
                                    className="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-700"
                                    aria-label={showPassword ? 'Sembunyikan PIN' : 'Lihat PIN'}
                                >
                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>
                        </Field>

                        <label className="flex items-center gap-2 text-xs font-medium text-slate-600 select-none">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            Ingat sesi login (khusus admin di perangkat pribadi)
                        </label>

                        <Button type="submit" size="lg" className="w-full" loading={processing}>
                            <LogIn className="size-4" /> Masuk ke Sistem
                        </Button>
                    </form>

                    <div className="mt-5 border-t border-slate-200 pt-4 text-center">
                        <p className="text-xs text-slate-500">Belum memiliki akun PIC Mesin?</p>
                        <Link
                            href="/register"
                            className="mt-1.5 inline-flex items-center gap-1.5 text-sm font-bold text-blue-700 hover:underline"
                        >
                            <UserPlus className="size-4" /> Daftar Akun PIC Mesin Baru
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    );
}
