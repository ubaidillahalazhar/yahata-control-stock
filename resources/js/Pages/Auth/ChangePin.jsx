import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, KeyRound, LogOut, ShieldAlert } from 'lucide-react';
import FlashMessages from '@/Components/FlashMessages';
import PinInput from '@/Components/PinInput';
import { Button, Card, Field, Input } from '@/Components/ui';

/**
 * Ganti PIN (PIC) / password (admin). `forced` = setelah admin mereset → wajib ganti dulu.
 */
export default function ChangePin({ isAdmin, forced }) {
    const field = isAdmin ? 'password' : 'pin';
    const label = isAdmin ? 'Password' : 'PIN';

    const { data, setData, put, processing, errors, reset } = useForm({
        current: '',
        [field]: '',
        [`${field}_confirmation`]: '',
    });

    const submit = (e) => {
        e.preventDefault();
        put('/account/pin', { onError: () => reset() });
    };

    const secretInput = (name, autoComplete) =>
        isAdmin ? (
            <Input
                type="password"
                value={data[name]}
                onChange={(e) => setData(name, e.target.value)}
                invalid={Boolean(errors[name])}
                autoComplete={autoComplete}
                className="h-12"
            />
        ) : (
            <PinInput
                value={data[name]}
                onChange={(value) => setData(name, value)}
                invalid={Boolean(errors[name])}
                autoComplete={autoComplete}
            />
        );

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-900 px-4 py-10">
            <Head title={`Ganti ${label}`} />

            <div className="w-full max-w-sm">
                <div className="mb-5 flex flex-col items-center text-center">
                    <span className="mb-3 flex size-12 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg">
                        <KeyRound className="size-6" aria-hidden="true" />
                    </span>
                    <h1 className="text-xl font-extrabold text-white">Ganti {label}</h1>
                </div>

                <FlashMessages />

                <Card className="p-6">
                    {forced && (
                        <div className="mb-4 flex gap-2.5 rounded-lg bg-amber-50 px-3.5 py-3 text-xs text-amber-900">
                            <ShieldAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <p>
                                {label} Anda baru saja direset admin. Ganti dengan {label} baru yang hanya Anda ketahui
                                sebelum melanjutkan.
                            </p>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <Field label={forced ? `${label} sementara dari admin` : `${label} lama`} required error={errors.current}>
                            {secretInput('current', 'current-password')}
                        </Field>
                        <Field
                            label={`${label} baru`}
                            required
                            error={errors[field]}
                            hint={isAdmin ? 'Minimal 8 karakter' : '6 digit angka'}
                        >
                            {secretInput(field, 'new-password')}
                        </Field>
                        <Field label={`Ulangi ${label} baru`} required error={errors[`${field}_confirmation`]}>
                            {secretInput(`${field}_confirmation`, 'new-password')}
                        </Field>

                        <Button type="submit" size="lg" className="w-full" loading={processing}>
                            <KeyRound className="size-4" /> Simpan {label} Baru
                        </Button>
                    </form>

                    <div className="mt-5 border-t border-slate-200 pt-4 text-center">
                        {forced ? (
                            <button
                                type="button"
                                onClick={() => router.post('/logout')}
                                className="inline-flex items-center gap-1.5 text-sm font-bold text-slate-600 hover:text-red-700"
                            >
                                <LogOut className="size-4" /> Keluar
                            </button>
                        ) : (
                            <Link
                                href="/"
                                className="inline-flex items-center gap-1.5 text-sm font-bold text-slate-600 hover:text-blue-700"
                            >
                                <ArrowLeft className="size-4" /> Kembali
                            </Link>
                        )}
                    </div>
                </Card>
            </div>
        </div>
    );
}
