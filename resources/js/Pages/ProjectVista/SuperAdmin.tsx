import { MetricCard } from '@/Components/ProjectVista/MetricCard';
import { ProjectCard } from '@/Components/ProjectVista/ProjectCard';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import {
    CompanyPayload,
    ProjectCardPayload,
    ProjectPayload,
} from '@/types/projectvista';
import { Head, Link } from '@inertiajs/react';

interface SuperAdminProps {
    companies: CompanyPayload[];
    projects: ProjectCardPayload[];
    primaryProject: ProjectPayload | null;
    platform: {
        companies_count: number;
        projects_count: number;
        active_trials: number;
    };
}

export default function SuperAdmin({
    companies,
    projects,
    primaryProject,
    platform,
}: SuperAdminProps) {
    return (
        <ProjectVistaShell
            title="Super Admin Command Center"
            role="super_admin"
            project={primaryProject}
        >
            <Head title="Command Center" />
            <div className="grid gap-4 md:grid-cols-3">
                <MetricCard
                    label="Companies"
                    value={platform.companies_count}
                    detail="Licensed demo tenants"
                />
                <MetricCard
                    label="Projects"
                    value={platform.projects_count}
                    detail="Across all companies"
                />
                <MetricCard
                    label="Trial accounts"
                    value={platform.active_trials}
                    detail="Subscription status"
                />
            </div>

            <section className="mt-8 grid gap-5 lg:grid-cols-[420px_1fr]">
                <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                    <h2 className="text-xl font-semibold">Companies</h2>
                    <div className="mt-5 space-y-3">
                        {companies.map((company) => (
                            <Link
                                key={company.id}
                                href={route('companies.admin', company.slug)}
                                className="block rounded-md bg-black/25 p-4 transition hover:bg-black/40"
                            >
                                <div className="font-medium">
                                    {company.name}
                                </div>
                                <div className="mt-1 text-sm text-white/50">
                                    {company.plan} ·{' '}
                                    {company.subscription_status} ·{' '}
                                    {company.projects_count ?? 0} projects
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
                <div>
                    <h2 className="mb-5 text-xl font-semibold">
                        Support Visibility
                    </h2>
                    <div className="grid gap-5 md:grid-cols-2">
                        {projects.map((project) => (
                            <ProjectCard key={project.id} project={project} />
                        ))}
                    </div>
                </div>
            </section>
        </ProjectVistaShell>
    );
}
