import { ProjectCardPayload } from '@/types/projectvista';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { StatusPill } from './StatusPill';

interface ProjectCardProps {
    project: ProjectCardPayload;
}

export function ProjectCard({ project }: ProjectCardProps) {
    return (
        <Link
            href={route('projects.show', project.slug)}
            className="group overflow-hidden rounded-lg border border-white/10 bg-white/[0.04] transition hover:border-[#d6b36a]/60"
        >
            <div
                className="h-40 bg-cover bg-center"
                style={{
                    backgroundImage: project.hero_image_url
                        ? `linear-gradient(180deg, rgba(9,11,15,0.1), rgba(9,11,15,0.82)), url(${project.hero_image_url})`
                        : 'linear-gradient(135deg, #171b24, #2c2417)',
                }}
            />
            <div className="p-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <div className="text-xs tracking-[0.25em] text-[#d6b36a] uppercase">
                            {project.company ?? 'ProjectVista'}
                        </div>
                        <h3 className="mt-2 text-xl font-semibold text-white">
                            {project.name}
                        </h3>
                        <p className="mt-1 text-sm text-white/55">
                            {project.current_task} · {project.percent_complete}%
                            complete
                        </p>
                    </div>
                    <ArrowRight className="h-5 w-5 text-white/40 transition group-hover:text-[#d6b36a]" />
                </div>
                <div className="mt-4 flex items-center gap-2">
                    <StatusPill status={project.health_status} />
                </div>
                <div className="mt-5 grid grid-cols-3 gap-2 text-center text-xs text-white/60">
                    <div className="rounded-md bg-black/25 p-3">
                        <div className="text-lg font-semibold text-white">
                            {project.pending_approvals}
                        </div>
                        approvals
                    </div>
                    <div className="rounded-md bg-black/25 p-3">
                        <div className="text-lg font-semibold text-white">
                            {project.pending_selections}
                        </div>
                        selections
                    </div>
                    <div className="rounded-md bg-black/25 p-3">
                        <div className="text-lg font-semibold text-white">
                            {project.blocked_tasks}
                        </div>
                        blockers
                    </div>
                </div>
            </div>
        </Link>
    );
}
