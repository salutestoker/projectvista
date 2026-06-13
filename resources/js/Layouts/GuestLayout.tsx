import { ProjectVistaLogo } from '@/Components/ProjectVista/ProjectVistaLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="relative flex min-h-screen flex-col bg-[#090b0f] text-white">
            <div
                className="absolute inset-0 bg-cover bg-center opacity-35"
                style={{
                    backgroundImage:
                        "linear-gradient(90deg, rgba(9,11,15,0.98), rgba(9,11,15,0.84)), url('/storage/demo/smith-residence-hero.png')",
                }}
            />
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(214,179,106,0.22),transparent_36%)]" />

            <header className="relative z-20 mx-auto w-full max-w-7xl px-5 py-6 md:px-8">
                <div className="flex justify-center px-10 lg:block">
                    <Link href="/" className="inline-flex items-center gap-3">
                        <ProjectVistaLogo className="h-14" />
                    </Link>
                </div>
            </header>

            <div className="relative z-10 mx-auto grid min-h-screen w-full max-w-7xl lg:grid-cols-[1fr_500px] lg:px-8">
                <section className="hidden flex-col px-10 py-8 lg:flex">
                    <div className="max-w-xl pb-12">
                        <div className="text-xs tracking-[0.3em] text-[#d6b36a] uppercase">
                            Customer experience portal
                        </div>
                        <h1 className="mt-5 text-5xl leading-tight font-semibold">
                            Calm, clear project confidence for every homeowner.
                        </h1>
                        <p className="mt-5 text-lg leading-8 text-white/62">
                            Progress, approvals, selections, documents,
                            payments, and messages in one polished place.
                        </p>
                    </div>
                </section>

                <section className="flex min-h-screen items-start justify-center px-5 py-10 lg:px-0 lg:py-8">
                    <div className="w-full max-w-md">
                        <div className="rounded-lg border border-white/10 bg-black/55 p-6 shadow-2xl shadow-black/40 backdrop-blur">
                            {children}
                        </div>
                    </div>
                </section>
            </div>
        </div>
    );
}
