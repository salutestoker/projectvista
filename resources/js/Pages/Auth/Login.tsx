import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, CheckCircle2 } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            {status && (
                <div className="mb-5 rounded-md border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-200">
                    {status}
                </div>
            )}

            <div className="mb-6">
                <div className="text-xs tracking-[0.25em] text-[#d6b36a] uppercase">
                    Welcome back
                </div>
                <h1 className="mt-3 text-3xl font-semibold text-white">
                    Log in to ProjectVista
                </h1>
                <p className="mt-3 text-sm leading-6 text-white/55">
                    Open the polished project portal for updates, approvals,
                    selections, documents, and client messages.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel
                        htmlFor="email"
                        value="Email"
                        className="text-white/68"
                    />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-2 block w-full border-white/10 bg-white/[0.06] text-white placeholder:text-white/30 focus:border-[#d6b36a] focus:ring-[#d6b36a]"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password"
                        value="Password"
                        className="text-white/68"
                    />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-2 block w-full border-white/10 bg-white/[0.06] text-white placeholder:text-white/30 focus:border-[#d6b36a] focus:ring-[#d6b36a]"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-white/55">
                            Remember me
                        </span>
                    </label>
                </div>

                <div className="flex items-center justify-between gap-4">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-white/50 underline underline-offset-4 transition hover:text-[#d6b36a] focus:ring-2 focus:ring-[#d6b36a] focus:ring-offset-2 focus:ring-offset-[#090b0f] focus:outline-none"
                        >
                            Forgot your password?
                        </Link>
                    )}

                    <PrimaryButton
                        className="border-0 bg-[#d6b36a] text-black hover:bg-[#f0d58c] focus:bg-[#f0d58c] active:bg-[#caa65d]"
                        disabled={processing}
                    >
                        Log in <ArrowRight className="ms-2 h-4 w-4" />
                    </PrimaryButton>
                </div>
            </form>

            <div className="mt-6 rounded-lg border border-[#d6b36a]/25 bg-[#d6b36a]/10 p-4">
                <div className="flex gap-3">
                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-[#d6b36a]" />
                    <div>
                        <div className="text-sm font-medium text-[#f5dfa6]">
                            Demo access
                        </div>
                        <p className="mt-1 text-xs leading-5 text-white/55">
                            Try manager@omnipools.test with password `password`
                            to view the seeded Smith Residence workflow.
                        </p>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
