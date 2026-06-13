import { ProjectVistaLogo } from '@/Components/ProjectVista/ProjectVistaLogo';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { ProjectPayload, ProjectVistaRole } from '@/types/projectvista';
import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    CalendarDays,
    Check,
    CreditCard,
    FileText,
    FolderKanban,
    Home,
    LayoutDashboard,
    LogOut,
    Menu,
    MessageSquare,
    Settings,
    Shield,
    Sparkles,
    Timer,
} from 'lucide-react';
import { PropsWithChildren, useState } from 'react';

interface ProjectVistaShellProps extends PropsWithChildren {
    title: string;
    eyebrow?: string;
    role: ProjectVistaRole;
    project?: ProjectPayload | null;
    navBadges?: Record<string, number>;
    primaryAction?: {
        label: string;
        href?: string;
    };
}

const roleLabels: Record<ProjectVistaRole, string> = {
    super_admin: 'Super Admin',
    company_admin: 'Owner',
    company_manager: 'Project Manager',
    subcontractor: 'Tile Contractor',
    client: 'Homeowner',
    viewer: 'Viewer',
};

export function ProjectVistaShell({
    title,
    eyebrow,
    role,
    project,
    navBadges = {},
    primaryAction,
    children,
}: ProjectVistaShellProps) {
    const page = usePage<PageProps>();
    const { auth, flash } = page.props;
    const url = page.url;
    const [mobileOpen, setMobileOpen] = useState(false);
    const userInitials = auth.user.name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

    const navItems = [
        { key: 'home', label: 'Home', href: route('dashboard'), Icon: Home },
        ...(role === 'super_admin'
            ? [
                  {
                      key: 'command',
                      label: 'Command Center',
                      href: route('super-admin.dashboard'),
                      Icon: Shield,
                  },
                  {
                      key: 'components',
                      label: 'Components',
                      href: route('super-admin.components'),
                      Icon: LayoutDashboard,
                  },
              ]
            : []),
        ...(project
            ? [
                  {
                      key: 'projects',
                      label: role === 'client' ? 'Project' : 'Projects',
                      href: route('projects.show', project.slug),
                      Icon: FolderKanban,
                  },
                  {
                      key: 'timeline',
                      label: 'Timeline',
                      href: route('projects.timeline', project.slug),
                      Icon: Timer,
                  },
                  ...(role !== 'subcontractor'
                      ? [
                            {
                                key: 'selections',
                                label: 'Selections',
                                href: route(
                                    'projects.selections',
                                    project.slug,
                                ),
                                Icon: Sparkles,
                            },
                        ]
                      : []),
                  ...(role !== 'subcontractor'
                      ? [
                            {
                                key: 'messaging',
                                label: 'Messaging',
                                href: route('projects.messages', project.slug),
                                Icon: MessageSquare,
                            },
                            {
                                key: 'approvals',
                                label: 'Approvals',
                                href: route('projects.approvals', project.slug),
                                Icon: Check,
                            },
                        ]
                      : [
                            {
                                key: 'approvals',
                                label: 'Approvals',
                                href: route('projects.timeline', project.slug),
                                Icon: Check,
                            },
                        ]),
                  ...(project.permissions.can_view_payments
                      ? [
                            {
                                key: 'payments',
                                label: 'Payments',
                                href: route('projects.payments', project.slug),
                                Icon: CreditCard,
                            },
                        ]
                      : []),
                  {
                      key: 'documents',
                      label: 'Documents',
                      href: route('projects.documents', project.slug),
                      Icon: FileText,
                  },
                  ...(role === 'company_admin' || role === 'company_manager'
                      ? [
                            {
                                key: 'reports',
                                label: 'Reports',
                                href: route('dashboard'),
                                Icon: CalendarDays,
                            },
                            {
                                key: 'settings',
                                label: 'Company Settings',
                                href: route(
                                    'companies.admin',
                                    project.company.slug,
                                ),
                                Icon: Settings,
                            },
                        ]
                      : []),
              ]
            : []),
    ];

    const sidebar = (
        <div className="bg-sidebar text-sidebar-foreground flex h-full flex-col">
            <div className="flex justify-center px-6 py-7">
                <Link href={route('dashboard')}>
                    <ProjectVistaLogo className="h-16" />
                </Link>
            </div>

            <nav className="flex flex-1 flex-col gap-2 px-6">
                {navItems.map(({ key, label, href, Icon }) => {
                    const hrefPath = new URL(href, window.location.origin)
                        .pathname;
                    const currentPath = url.split('?')[0];
                    const active =
                        currentPath === hrefPath ||
                        (hrefPath !== '/dashboard' &&
                            currentPath.startsWith(hrefPath));

                    return (
                        <Link
                            key={key}
                            href={href}
                            className={cn(
                                'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground flex h-11 items-center gap-3 rounded-lg px-4 text-sm transition',
                                active &&
                                    'bg-sidebar-accent text-sidebar-accent-foreground shadow-[inset_3px_0_0_var(--pv-gold)]',
                            )}
                        >
                            <Icon className="size-4" />
                            <span className="flex-1">{label}</span>
                            {navBadges[key] ? (
                                <Badge className="bg-primary text-primary-foreground">
                                    {navBadges[key]}
                                </Badge>
                            ) : null}
                        </Link>
                    );
                })}
            </nav>

            <div className="border-sidebar-border border-t p-6">
                <div className="flex items-center gap-3">
                    <Avatar className="size-12">
                        <AvatarFallback className="bg-primary text-primary-foreground">
                            {userInitials}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <div className="truncate text-sm font-semibold">
                            {auth.user.name}
                        </div>
                        <div className="text-muted-foreground truncate text-xs">
                            {roleLabels[role]}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return (
        <div className="pv-gradient text-foreground min-h-screen">
            <aside className="border-sidebar-border fixed inset-y-0 left-0 z-40 hidden w-[17.5rem] border-r xl:block">
                {sidebar}
            </aside>

            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                <SheetContent side="left" className="w-80 p-0">
                    <SheetHeader className="sr-only">
                        <SheetTitle>ProjectVista navigation</SheetTitle>
                    </SheetHeader>
                    {sidebar}
                </SheetContent>
            </Sheet>

            <main className="xl:pl-[17.5rem]">
                <header className="border-border bg-background/78 sticky top-0 z-30 border-b px-5 py-5 backdrop-blur md:px-8">
                    <div className="mx-auto flex max-w-[1560px] items-center justify-between gap-5">
                        <div className="flex min-w-0 items-center gap-4">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="xl:hidden"
                                onClick={() => setMobileOpen(true)}
                            >
                                <Menu />
                                <span className="sr-only">Open navigation</span>
                            </Button>
                            <div>
                                {eyebrow ? (
                                    <div className="text-primary text-xs tracking-[0.28em] uppercase">
                                        {eyebrow}
                                    </div>
                                ) : null}
                                <h1 className="text-2xl font-semibold md:text-4xl">
                                    {title}
                                </h1>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <Tooltip>
                                <TooltipTrigger>
                                    <Button variant="outline" size="icon">
                                        <Bell />
                                        <span className="sr-only">
                                            Notifications
                                        </span>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Notifications</TooltipContent>
                            </Tooltip>
                            {primaryAction?.href ? (
                                <Link
                                    href={primaryAction.href}
                                    className="bg-primary text-primary-foreground hidden rounded-lg px-4 py-2 text-sm font-semibold md:inline-flex"
                                >
                                    {primaryAction.label}
                                </Link>
                            ) : primaryAction ? (
                                <Button className="hidden md:inline-flex">
                                    {primaryAction.label}
                                </Button>
                            ) : null}
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="border-border text-muted-foreground hover:text-primary rounded-lg border p-2 transition"
                            >
                                <LogOut className="size-4" />
                                <span className="sr-only">Log out</span>
                            </Link>
                        </div>
                    </div>
                    {(flash.success || flash.error) && (
                        <div className="border-primary/30 bg-primary/10 text-primary mx-auto mt-4 max-w-[1560px] rounded-lg border px-4 py-3 text-sm">
                            {flash.success ?? flash.error}
                        </div>
                    )}
                </header>

                <section className="mx-auto max-w-[1560px] px-5 py-8 md:px-8">
                    {children}
                </section>
            </main>
        </div>
    );
}
