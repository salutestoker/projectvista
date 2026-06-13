import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { ProjectPayload } from '@/types/projectvista';
import { Head, Link } from '@inertiajs/react';
import { FileText, MessageSquare, Sparkles, Timer } from 'lucide-react';
import { ReactNode } from 'react';

interface ProjectDetailProps {
    project: ProjectPayload;
}

export default function ProjectDetail({ project }: ProjectDetailProps) {
    return (
        <ProjectVistaShell
            title={project.name}
            eyebrow={project.company.name}
            role={project.role}
            project={project}
        >
            <Head title={project.name} />
            <section className="grid gap-6 lg:grid-cols-[1fr_380px]">
                <div className="overflow-hidden rounded-lg border border-white/10 bg-white/[0.04]">
                    <div
                        className="h-72 bg-cover bg-center"
                        style={{
                            backgroundImage: project.hero_image_url
                                ? `linear-gradient(180deg, rgba(9,11,15,0.05), rgba(9,11,15,0.9)), url(${project.hero_image_url})`
                                : 'linear-gradient(135deg, #111827, #2c2417)',
                        }}
                    />
                    <div className="p-6">
                        <div className="flex flex-wrap gap-2">
                            <StatusPill status={project.status} />
                            <StatusPill status={project.health_status} />
                        </div>
                        <h2 className="mt-5 text-2xl font-semibold">
                            {project.project_type}
                        </h2>
                        <p className="mt-3 text-white/60">
                            {project.client_summary}
                        </p>
                        <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                            <div
                                className="h-full rounded-full bg-[#d6b36a]"
                                style={{
                                    width: `${project.percent_complete}%`,
                                }}
                            />
                        </div>
                        <div className="mt-3 text-sm text-white/50">
                            {project.percent_complete}% complete
                        </div>
                    </div>
                </div>

                <aside className="space-y-4">
                    <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                        <h3 className="font-semibold">Project Identity</h3>
                        <dl className="mt-4 space-y-3 text-sm">
                            <div>
                                <dt className="text-white/45">Address</dt>
                                <dd>{project.address}</dd>
                            </div>
                            <div>
                                <dt className="text-white/45">Current phase</dt>
                                <dd>{project.phase}</dd>
                            </div>
                            <div>
                                <dt className="text-white/45">Manager</dt>
                                <dd>{project.manager?.name ?? 'Unassigned'}</dd>
                            </div>
                            <div>
                                <dt className="text-white/45">
                                    Estimated completion
                                </dt>
                                <dd>{project.estimated_completion_on}</dd>
                            </div>
                        </dl>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <ModuleLink
                            href={route('projects.timeline', project.slug)}
                            label="Timeline"
                            icon={<Timer />}
                        />
                        <ModuleLink
                            href={route('projects.selections', project.slug)}
                            label="Selections"
                            icon={<Sparkles />}
                        />
                        <ModuleLink
                            href={route('projects.documents', project.slug)}
                            label="Documents"
                            icon={<FileText />}
                        />
                        {project.permissions.can_message && (
                            <ModuleLink
                                href={route('projects.messages', project.slug)}
                                label="Messages"
                                icon={<MessageSquare />}
                            />
                        )}
                    </div>
                </aside>
            </section>
        </ProjectVistaShell>
    );
}

function ModuleLink({
    href,
    label,
    icon,
}: {
    href: string;
    label: string;
    icon: ReactNode;
}) {
    return (
        <Link
            href={href}
            className="rounded-lg border border-white/10 bg-white/[0.04] p-4 transition hover:border-[#d6b36a]/60"
        >
            <div className="h-5 w-5 text-[#d6b36a]">{icon}</div>
            <div className="mt-4 text-sm font-semibold">{label}</div>
        </Link>
    );
}
