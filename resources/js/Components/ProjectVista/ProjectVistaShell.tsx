import { PageProps } from '@/types';
import { ProjectPayload, ProjectVistaRole } from '@/types/projectvista';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CreditCard,
    FileText,
    LayoutDashboard,
    LogOut,
    LucideIcon,
    MessageSquare,
    Shield,
    Sparkles,
    Timer,
} from 'lucide-react';
import { PropsWithChildren } from 'react';

interface ProjectVistaShellProps extends PropsWithChildren {
    title: string;
    eyebrow?: string;
    role: ProjectVistaRole;
    project?: ProjectPayload | null;
}

const roleLabels: Record<ProjectVistaRole, string> = {
    super_admin: 'Super Admin',
    company_admin: 'Company Admin',
    company_manager: 'Company Manager',
    subcontractor: 'Sub-Contractor',
    client: 'Client Portal',
    viewer: 'Viewer',
};

export function ProjectVistaShell({
    title,
    eyebrow = 'ProjectVista',
    role,
    project,
    children,
}: ProjectVistaShellProps) {
    const { auth, flash } = usePage<PageProps>().props;
    const nav: { label: string; href: string; Icon: LucideIcon }[] = project
        ? [
              {
                  label: 'Project',
                  href: route('projects.show', project.slug),
                  Icon: Building2,
              },
              {
                  label: 'Timeline',
                  href: route('projects.timeline', project.slug),
                  Icon: Timer,
              },
              {
                  label: 'Selections',
                  href: route('projects.selections', project.slug),
                  Icon: Sparkles,
              },
              {
                  label: 'Documents',
                  href: route('projects.documents', project.slug),
                  Icon: FileText,
              },
              ...(project.permissions.can_view_payments
                  ? [
                        {
                            label: 'Payments',
                            href: route('projects.payments', project.slug),
                            Icon: CreditCard,
                        },
                    ]
                  : []),
              ...(project.permissions.can_message
                  ? [
                        {
                            label: 'Messages',
                            href: route('projects.messages', project.slug),
                            Icon: MessageSquare,
                        },
                    ]
                  : []),
          ]
        : [];

    return (
        <div className="min-h-screen bg-[#090b0f] text-white">
            <aside className="fixed inset-y-0 left-0 hidden w-72 border-r border-white/10 bg-black/35 px-5 py-6 backdrop-blur xl:block">
                <Link
                    href={route('dashboard')}
                    className="flex items-center gap-3"
                >
                    <div className="grid h-10 w-10 place-items-center rounded-lg bg-[#d6b36a] text-black">
                        <Shield className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="font-semibold">ProjectVista</div>
                        <div className="text-xs text-white/45">
                            Luxury project clarity
                        </div>
                    </div>
                </Link>

                <nav className="mt-10 space-y-2">
                    <Link
                        href={route('dashboard')}
                        className="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                    >
                        <LayoutDashboard className="h-4 w-4" />
                        Dashboard
                    </Link>
                    {role === 'super_admin' && (
                        <Link
                            href={route('super-admin.dashboard')}
                            className="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                        >
                            <Shield className="h-4 w-4" />
                            Command Center
                        </Link>
                    )}
                    {project?.permissions.can_manage_standards && (
                        <Link
                            href={route(
                                'companies.admin',
                                project.company.slug,
                            )}
                            className="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                        >
                            <Building2 className="h-4 w-4" />
                            Company Admin
                        </Link>
                    )}
                    {nav.map(({ label, href, Icon }) => (
                        <Link
                            key={label}
                            href={href}
                            className="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                        >
                            <Icon className="h-4 w-4" />
                            {label}
                        </Link>
                    ))}
                </nav>
            </aside>

            <main className="xl:pl-72">
                <header className="border-b border-white/10 bg-[#090b0f]/85 px-5 py-5 backdrop-blur md:px-8">
                    <div className="mx-auto flex max-w-7xl items-center justify-between gap-5">
                        <div>
                            <div className="text-xs tracking-[0.3em] text-[#d6b36a] uppercase">
                                {eyebrow}
                            </div>
                            <h1 className="mt-2 text-2xl font-semibold text-white md:text-3xl">
                                {title}
                            </h1>
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="hidden text-right sm:block">
                                <div className="text-sm font-medium">
                                    {auth.user.name}
                                </div>
                                <div className="text-xs text-white/45">
                                    {roleLabels[role]}
                                </div>
                            </div>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="rounded-md border border-white/10 p-2 text-white/60 transition hover:border-[#d6b36a]/60 hover:text-[#d6b36a]"
                            >
                                <LogOut className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                    {(flash.success || flash.error) && (
                        <div className="mx-auto mt-4 max-w-7xl rounded-md border border-[#d6b36a]/30 bg-[#d6b36a]/10 px-4 py-3 text-sm text-[#f5dfa6]">
                            {flash.success ?? flash.error}
                        </div>
                    )}
                </header>

                <section className="mx-auto max-w-7xl px-5 py-8 md:px-8">
                    {children}
                </section>
            </main>
        </div>
    );
}
