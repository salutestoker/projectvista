import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    CreditCard,
    FileText,
    Home,
    MessageSquare,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import { ReactNode } from 'react';

export default function Welcome({ auth }: PageProps) {
    const signedIn = Boolean(auth.user);

    return (
        <>
            <Head title="ProjectVista" />
            <main className="min-h-screen bg-[#090b0f] text-white">
                <section className="relative isolate overflow-hidden">
                    <div
                        className="absolute inset-0 -z-10 bg-cover bg-center opacity-45"
                        style={{
                            backgroundImage:
                                "linear-gradient(90deg, rgba(9,11,15,0.96), rgba(9,11,15,0.78), rgba(9,11,15,0.56)), url('/storage/demo/smith-residence-hero.png')",
                        }}
                    />
                    <div className="absolute inset-x-0 top-0 -z-10 h-[50vw] bg-[radial-gradient(circle_at_top_left,rgba(214,179,106,0.22),transparent_18%)]" />

                    <header className="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 md:px-8">
                        <Link href="/" className="flex items-center gap-3">
                            <div className="grid h-10 w-10 place-items-center rounded-lg bg-[#d6b36a] text-black">
                                <ShieldCheck className="h-5 w-5" />
                            </div>
                            <div>
                                <div className="font-semibold tracking-wide">
                                    ProjectVista
                                </div>
                                <div className="text-xs text-white/45">
                                    Luxury project clarity
                                </div>
                            </div>
                        </Link>

                        <nav className="flex items-center gap-3">
                            {signedIn ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black transition hover:bg-[#f0d58c]"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-md border border-white/15 px-4 py-2 text-sm font-semibold text-white/80 transition hover:border-[#d6b36a]/70 hover:text-white"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="hidden rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black transition hover:bg-[#f0d58c] sm:inline-flex"
                                    >
                                        Request access
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    <div className="mx-auto grid max-w-7xl gap-10 px-5 pt-12 pb-16 md:px-8 md:pb-24 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                        <div>
                            <div className="inline-flex rounded-full border border-[#d6b36a]/30 bg-[#d6b36a]/10 px-3 py-1 text-xs font-medium tracking-[0.25em] text-[#f5dfa6] uppercase">
                                Premium homeowner portal
                            </div>
                            <h1 className="mt-6 max-w-4xl text-5xl leading-tight font-semibold text-white md:text-7xl">
                                Turn expensive home projects into calm,
                                confident experiences.
                            </h1>
                            <p className="mt-6 max-w-2xl text-lg leading-8 text-white/68">
                                ProjectVista gives homeowners a polished place
                                to see progress, approve decisions, review
                                selections, follow payments, and message the
                                team without wondering what happens next.
                            </p>

                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link
                                    href={
                                        signedIn
                                            ? route('dashboard')
                                            : route('login')
                                    }
                                    className="inline-flex items-center gap-2 rounded-md bg-[#d6b36a] px-5 py-3 text-sm font-semibold text-black transition hover:bg-[#f0d58c]"
                                >
                                    {signedIn
                                        ? 'Open dashboard'
                                        : 'View demo portal'}
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                                <a
                                    href="#experience"
                                    className="inline-flex items-center gap-2 rounded-md border border-white/15 px-5 py-3 text-sm font-semibold text-white/80 transition hover:border-[#d6b36a]/70 hover:text-white"
                                >
                                    See what clients get
                                </a>
                            </div>
                        </div>

                        <div className="rounded-lg border border-white/10 bg-black/45 p-5 shadow-2xl shadow-black/40 backdrop-blur">
                            <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="text-xs tracking-[0.25em] text-[#d6b36a] uppercase">
                                            Smith Residence
                                        </div>
                                        <h2 className="mt-2 text-2xl font-semibold">
                                            Tile Installation
                                        </h2>
                                    </div>
                                    <span className="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-medium text-amber-100">
                                        Approval needed
                                    </span>
                                </div>
                                <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                                    <div className="h-full w-[62%] rounded-full bg-[#d6b36a]" />
                                </div>
                                <div className="mt-3 text-sm text-white/50">
                                    62% complete · Scottsdale, Arizona
                                </div>
                            </div>

                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                {[
                                    [
                                        'What just happened?',
                                        'Coping is complete and tile has started.',
                                    ],
                                    [
                                        'What happens next?',
                                        'Decking approval keeps the finish stage on schedule.',
                                    ],
                                    [
                                        'Do I need to approve anything?',
                                        'One selection is waiting for your response.',
                                    ],
                                    [
                                        'Where are documents?',
                                        'Contracts, selections, and handoff files live here.',
                                    ],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="rounded-lg border border-white/10 bg-white/[0.04] p-4"
                                    >
                                        <div className="text-xs tracking-[0.18em] text-white/38 uppercase">
                                            {label}
                                        </div>
                                        <p className="mt-2 text-sm leading-6 text-white/72">
                                            {value}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    id="experience"
                    className="border-y border-white/10 bg-white/[0.03]"
                >
                    <div className="mx-auto grid max-w-7xl gap-5 px-5 py-10 md:grid-cols-4 md:px-8">
                        <Feature
                            icon={<Home className="h-5 w-5" />}
                            title="Project visibility"
                            text="Homeowners know the current phase, percent complete, and next step."
                        />
                        <Feature
                            icon={<Sparkles className="h-5 w-5" />}
                            title="Selections"
                            text="Material decisions become polished cards instead of scattered texts."
                        />
                        <Feature
                            icon={<MessageSquare className="h-5 w-5" />}
                            title="Messages"
                            text="Client updates stay calm, clear, and tied to the project."
                        />
                        <Feature
                            icon={<FileText className="h-5 w-5" />}
                            title="Documents"
                            text="Contracts, approvals, and handoff files are easy to find."
                        />
                    </div>
                </section>

                <section className="mx-auto grid max-w-7xl gap-10 px-5 py-16 md:px-8 lg:grid-cols-[0.8fr_1.2fr]">
                    <div>
                        <div className="text-xs tracking-[0.3em] text-[#d6b36a] uppercase">
                            Built for trust
                        </div>
                        <h2 className="mt-4 text-3xl font-semibold">
                            The experience layer for high-value residential
                            work.
                        </h2>
                        <p className="mt-4 leading-7 text-white/60">
                            Start with pool builders, then expand naturally to
                            landscapers, outdoor living teams, remodelers, and
                            custom home builders.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        {[
                            [
                                'For homeowners',
                                'What changed, what is next, and what needs approval.',
                            ],
                            [
                                'For managers',
                                'Which clients are blocking progress and what to do next.',
                            ],
                            [
                                'For builders',
                                'A reusable client standard across every premium project.',
                            ],
                        ].map(([title, text]) => (
                            <div
                                key={title}
                                className="rounded-lg border border-white/10 bg-white/[0.04] p-5"
                            >
                                <CheckCircle2 className="h-5 w-5 text-[#d6b36a]" />
                                <h3 className="mt-4 font-semibold">{title}</h3>
                                <p className="mt-2 text-sm leading-6 text-white/58">
                                    {text}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-5 pb-16 md:px-8">
                    <div className="rounded-lg border border-[#d6b36a]/25 bg-[#d6b36a]/10 p-6 md:flex md:items-center md:justify-between">
                        <div>
                            <div className="text-sm font-semibold text-[#f5dfa6]">
                                Demo-ready account access
                            </div>
                            <p className="mt-2 text-sm text-white/60">
                                Use the seeded accounts to see the manager,
                                customer, admin, and subcontractor experience.
                            </p>
                        </div>
                        <Link
                            href={route('login')}
                            className="mt-5 inline-flex items-center gap-2 rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black md:mt-0"
                        >
                            Log in
                            <CreditCard className="h-4 w-4" />
                        </Link>
                    </div>
                </section>
            </main>
        </>
    );
}

function Feature({
    icon,
    title,
    text,
}: {
    icon: ReactNode;
    title: string;
    text: string;
}) {
    return (
        <div className="rounded-lg border border-white/10 bg-black/20 p-5">
            <div className="grid h-10 w-10 place-items-center rounded-md bg-[#d6b36a]/15 text-[#d6b36a]">
                {icon}
            </div>
            <h3 className="mt-4 font-semibold">{title}</h3>
            <p className="mt-2 text-sm leading-6 text-white/55">{text}</p>
        </div>
    );
}
