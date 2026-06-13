import { MetricCard } from '@/Components/ProjectVista/MetricCard';
import { ProjectCard } from '@/Components/ProjectVista/ProjectCard';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import {
    CompanyPayload,
    ProjectCardPayload,
    ProjectPayload,
    ProjectVistaRole,
} from '@/types/projectvista';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Clock3,
    ShieldAlert,
    Sparkles,
} from 'lucide-react';

interface DashboardProps {
    role: ProjectVistaRole;
    companies: CompanyPayload[];
    projects: ProjectCardPayload[];
    primaryProject: ProjectPayload | null;
    stats: {
        active_projects: number;
        pending_approvals: number;
        pending_selections: number;
        blocked_tasks: number;
    };
    demoAccounts: { label: string; email: string }[];
}

export default function Dashboard({
    role,
    companies,
    projects,
    primaryProject,
    stats,
    demoAccounts,
}: DashboardProps) {
    const title =
        role === 'client'
            ? 'Smith Residence Portal'
            : role === 'subcontractor'
              ? 'Assigned Work'
              : 'Project Console';

    return (
        <ProjectVistaShell title={title} role={role} project={primaryProject}>
            <Head title="Dashboard" />

            {primaryProject && (
                <section
                    className="overflow-hidden rounded-lg border border-white/10 bg-cover bg-center"
                    style={{
                        backgroundImage: primaryProject.hero_image_url
                            ? `linear-gradient(90deg, rgba(9,11,15,0.94), rgba(9,11,15,0.58)), url(${primaryProject.hero_image_url})`
                            : 'linear-gradient(135deg, #111827, #2c2417)',
                    }}
                >
                    <div className="max-w-3xl p-7 md:p-10">
                        <div className="text-xs tracking-[0.3em] text-[#d6b36a] uppercase">
                            {primaryProject.company.name}
                        </div>
                        <h2 className="mt-4 text-4xl font-semibold text-white">
                            {primaryProject.name}
                        </h2>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-white/70">
                            {primaryProject.client_summary}
                        </p>
                        <div className="mt-8 grid gap-4 md:grid-cols-3">
                            <div className="rounded-lg bg-black/35 p-4">
                                <div className="text-sm text-white/50">
                                    Where are we?
                                </div>
                                <div className="mt-2 text-lg font-semibold">
                                    {primaryProject.phase}
                                </div>
                            </div>
                            <div className="rounded-lg bg-black/35 p-4">
                                <div className="text-sm text-white/50">
                                    Progress
                                </div>
                                <div className="mt-2 text-lg font-semibold">
                                    {primaryProject.percent_complete}% complete
                                </div>
                            </div>
                            <div className="rounded-lg bg-black/35 p-4">
                                <div className="text-sm text-white/50">
                                    Estimated completion
                                </div>
                                <div className="mt-2 text-lg font-semibold">
                                    {primaryProject.estimated_completion_on}
                                </div>
                            </div>
                        </div>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={route(
                                    'projects.show',
                                    primaryProject.slug,
                                )}
                                className="inline-flex items-center gap-2 rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black transition hover:bg-[#f0d58c]"
                            >
                                Open project <ArrowRight className="h-4 w-4" />
                            </Link>
                            {primaryProject.approvals.some(
                                (approval) => approval.status === 'pending',
                            ) && (
                                <Link
                                    href={route(
                                        'projects.approvals',
                                        primaryProject.slug,
                                    )}
                                    className="inline-flex items-center gap-2 rounded-md border border-white/15 px-4 py-2 text-sm font-semibold text-white transition hover:border-[#d6b36a]/70"
                                >
                                    Review approval
                                </Link>
                            )}
                        </div>
                    </div>
                </section>
            )}

            <section className="mt-8 grid gap-4 md:grid-cols-4">
                <MetricCard
                    label="Active projects"
                    value={stats.active_projects}
                    detail="Visible to this user"
                />
                <MetricCard
                    label="Pending approvals"
                    value={stats.pending_approvals}
                    detail="Client decisions needed"
                />
                <MetricCard
                    label="Pending selections"
                    value={stats.pending_selections}
                    detail="Selections in motion"
                />
                <MetricCard
                    label="Blocked tasks"
                    value={stats.blocked_tasks}
                    detail="Schedule attention required"
                />
            </section>

            {primaryProject && role === 'client' && (
                <section className="mt-8 grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                        <CheckCircle2 className="h-5 w-5 text-emerald-300" />
                        <h3 className="mt-4 font-semibold">
                            What just happened?
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-white/60">
                            {primaryProject.latest_update}
                        </p>
                    </div>
                    <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                        <Clock3 className="h-5 w-5 text-[#d6b36a]" />
                        <h3 className="mt-4 font-semibold">
                            What happens next?
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-white/60">
                            {primaryProject.next_step}
                        </p>
                    </div>
                    <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                        <ShieldAlert className="h-5 w-5 text-amber-300" />
                        <h3 className="mt-4 font-semibold">
                            Do I need to approve anything?
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-white/60">
                            {stats.pending_approvals > 0
                                ? `${stats.pending_approvals} approval is waiting for your response.`
                                : 'No approvals are waiting right now.'}
                        </p>
                    </div>
                </section>
            )}

            <section className="mt-8 grid gap-5 lg:grid-cols-[1fr_360px]">
                <div>
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-xl font-semibold">Projects</h2>
                        <Sparkles className="h-5 w-5 text-[#d6b36a]" />
                    </div>
                    <div className="grid gap-5 md:grid-cols-2">
                        {projects.map((project) => (
                            <ProjectCard key={project.id} project={project} />
                        ))}
                    </div>
                </div>

                <aside className="space-y-5">
                    {role === 'super_admin' && (
                        <Link
                            href={route('super-admin.dashboard')}
                            className="block rounded-lg border border-[#d6b36a]/30 bg-[#d6b36a]/10 p-5 text-[#f5dfa6]"
                        >
                            Open Super Admin Command Center
                        </Link>
                    )}
                    {companies[0] &&
                        ['company_admin', 'super_admin'].includes(role) && (
                            <Link
                                href={route(
                                    'companies.admin',
                                    companies[0].slug,
                                )}
                                className="block rounded-lg border border-white/10 bg-white/[0.04] p-5 text-white/80"
                            >
                                Manage users, standards, invitations, and
                                company settings.
                            </Link>
                        )}
                    <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                        <h3 className="font-semibold">Demo accounts</h3>
                        <p className="mt-2 text-sm text-white/55">
                            All seeded accounts use password `password`.
                        </p>
                        <div className="mt-4 space-y-3">
                            {demoAccounts.map((account) => (
                                <div
                                    key={account.email}
                                    className="rounded-md bg-black/25 p-3"
                                >
                                    <div className="text-sm font-medium">
                                        {account.label}
                                    </div>
                                    <div className="text-xs text-white/50">
                                        {account.email}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </aside>
            </section>
        </ProjectVistaShell>
    );
}
