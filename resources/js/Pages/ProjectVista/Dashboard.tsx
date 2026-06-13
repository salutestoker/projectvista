import { AnimatedProgress } from '@/Components/ProjectVista/AnimatedProgress';
import { ApprovalDonut } from '@/Components/ProjectVista/ApprovalDonut';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    BusinessHomePayload,
    ClientHomePayload,
    CompanyPayload,
    DashboardHomePayload,
    DashboardMetricPayload,
    DashboardProjectRowPayload,
    ProjectCardPayload,
    ProjectPayload,
    ProjectVistaRole,
    SubcontractorHomePayload,
} from '@/types/projectvista';
import { useGSAP } from '@gsap/react';
import { Head, Link } from '@inertiajs/react';
import gsap from 'gsap';
import { Circle } from 'lucide-react';
import { ReactNode, useRef } from 'react';

gsap.registerPlugin(useGSAP);

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
        unread_messages: number;
    };
    home: DashboardHomePayload;
    demoAccounts: { label: string; email: string }[];
}

export default function Dashboard({
    role,
    companies,
    projects,
    primaryProject,
    stats,
    home,
    demoAccounts,
}: DashboardProps) {
    const scope = useRef<HTMLDivElement>(null);

    useGSAP(
        () => {
            gsap.from('.dashboard-card', {
                opacity: 0,
                y: 10,
                duration: 0.45,
                ease: 'power2.out',
                stagger: 0.04,
            });
        },
        { scope },
    );

    const badges = {
        messaging: stats.unread_messages,
        approvals: stats.pending_approvals,
    };

    const primaryAction =
        role === 'subcontractor'
            ? { label: 'My Schedule' }
            : role === 'client'
              ? { label: 'Share Updates' }
              : role === 'super_admin'
                ? {
                      label: 'Components',
                      href: route('super-admin.components'),
                  }
                : { label: '+ New Project' };

    return (
        <ProjectVistaShell
            title={home.type === 'client' ? 'Smith Residence' : home.title}
            eyebrow={
                home.type === 'client' ? home.project?.location : undefined
            }
            role={role}
            project={primaryProject}
            navBadges={badges}
            primaryAction={primaryAction}
        >
            <Head title="Dashboard" />
            <div ref={scope}>
                {home.type === 'owner' || home.type === 'manager' ? (
                    <BusinessDashboard home={home} />
                ) : home.type === 'subcontractor' ? (
                    <SubcontractorDashboard home={home} />
                ) : home.type === 'client' ? (
                    <ClientDashboard home={home} />
                ) : (
                    <SuperAdminDashboard
                        companies={companies}
                        projects={projects}
                        demoAccounts={demoAccounts}
                    />
                )}
            </div>
        </ProjectVistaShell>
    );
}

function MetricGrid({ metrics }: { metrics: DashboardMetricPayload[] }) {
    return (
        <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-5">
            {metrics.map((metric) => (
                <Card key={metric.label} className="dashboard-card pv-card">
                    <CardContent className="flex min-h-32 flex-col justify-center">
                        <div className="text-muted-foreground text-xs tracking-[0.18em] uppercase">
                            {metric.label}
                        </div>
                        <div
                            className={
                                metric.tone === 'gold'
                                    ? 'text-primary mt-3 text-4xl font-medium'
                                    : 'mt-3 text-4xl font-medium'
                            }
                        >
                            {metric.value}
                        </div>
                        <div className="text-muted-foreground mt-2 text-sm">
                            {metric.detail}
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function BusinessDashboard({ home }: { home: BusinessHomePayload }) {
    const totalApprovals = home.approvals_overview.reduce(
        (sum, segment) => sum + segment.value,
        0,
    );

    return (
        <div className="flex flex-col gap-8">
            <div>
                <p className="text-muted-foreground text-lg">{home.subtitle}</p>
            </div>

            <MetricGrid metrics={home.metrics} />

            <section className="dashboard-card">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-2xl font-semibold">
                        {home.type === 'owner'
                            ? 'All Open Projects'
                            : 'My Open Projects'}
                    </h2>
                    <Link
                        href={route('dashboard')}
                        className="text-primary text-sm font-medium underline underline-offset-4"
                    >
                        View All Projects
                    </Link>
                </div>
                <ProjectTable rows={home.project_rows} />
            </section>

            <div className="grid gap-5 xl:grid-cols-4">
                <Card className="dashboard-card pv-card">
                    <CardHeader>
                        <CardTitle>Recent Messages</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {home.messages.map((message) => (
                            <div key={`${message.author}-${message.body}`}>
                                <div className="font-semibold">
                                    {message.author}
                                </div>
                                <div className="text-muted-foreground text-sm">
                                    {message.body}
                                </div>
                            </div>
                        ))}
                        <Link
                            href={route('dashboard')}
                            className="text-primary text-sm underline underline-offset-4"
                        >
                            {home.metrics[4]?.value} Unread Messages
                        </Link>
                    </CardContent>
                </Card>

                <Card className="dashboard-card pv-card">
                    <CardHeader>
                        <CardTitle>Approvals Overview</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ApprovalDonut
                            data={home.approvals_overview}
                            total={totalApprovals}
                        />
                    </CardContent>
                </Card>

                <Card className="dashboard-card pv-card">
                    <CardHeader>
                        <CardTitle>Timeline Progress</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="text-2xl">{home.timeline.percent}%</div>
                        <AnimatedProgress value={home.timeline.percent} />
                        <div className="flex items-center gap-2 text-sm">
                            <Circle className="fill-pv-green text-pv-green size-3" />
                            {home.timeline.status}
                        </div>
                        <div>
                            <div className="font-semibold">Next Milestone</div>
                            <div className="text-muted-foreground text-sm">
                                {home.timeline.next_milestone}
                                {home.timeline.date_range
                                    ? ` · ${home.timeline.date_range}`
                                    : ''}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="dashboard-card pv-card">
                    <CardHeader>
                        <CardTitle>Payments Overview</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="text-primary text-2xl">
                            {home.payments.collected}
                        </div>
                        <div className="text-muted-foreground text-sm">
                            {home.payments.percent}% of {home.payments.total}
                        </div>
                        <AnimatedProgress value={home.payments.percent} />
                        <div className="border-pv-blue/30 bg-pv-blue/10 rounded-lg border p-3 text-sm">
                            {home.payments.upcoming} Upcoming Payments
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function ProjectTable({ rows }: { rows: DashboardProjectRowPayload[] }) {
    return (
        <div className="border-border bg-card/80 overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Project</TableHead>
                        <TableHead>Location</TableHead>
                        <TableHead>Progress</TableHead>
                        <TableHead>Next Step</TableHead>
                        <TableHead>Approvals</TableHead>
                        <TableHead>Payment Progress</TableHead>
                        <TableHead>Messages</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((project) => (
                        <TableRow key={project.id}>
                            <TableCell>
                                <Link
                                    href={route('projects.show', project.slug)}
                                    className="flex items-center gap-3"
                                >
                                    <div className="pv-thumb size-12 rounded-md" />
                                    <div>
                                        <div className="font-semibold">
                                            {project.name}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {project.code}
                                        </div>
                                    </div>
                                </Link>
                            </TableCell>
                            <TableCell>{project.location}</TableCell>
                            <TableCell>
                                <div className="flex min-w-32 flex-col gap-1">
                                    <span className="font-semibold">
                                        {project.progress}%
                                    </span>
                                    <AnimatedProgress
                                        value={project.progress}
                                    />
                                </div>
                            </TableCell>
                            <TableCell>
                                <div>{project.next_step}</div>
                                <div className="text-muted-foreground text-sm">
                                    {project.date_range}
                                </div>
                            </TableCell>
                            <TableCell>
                                <span className="inline-flex items-center gap-2">
                                    <Circle className="fill-pv-red text-pv-red size-2" />
                                    {project.approvals}
                                </span>
                            </TableCell>
                            <TableCell>
                                <div className="min-w-36">
                                    <div className="font-semibold">
                                        {project.payment_percent}%
                                    </div>
                                    <AnimatedProgress
                                        value={project.payment_percent}
                                    />
                                    <div className="text-muted-foreground text-xs">
                                        {project.payment_paid} of{' '}
                                        {project.payment_total}
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span className="inline-flex items-center gap-2">
                                    <Circle className="fill-pv-blue text-pv-blue size-2" />
                                    {project.messages}
                                </span>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function SubcontractorDashboard({ home }: { home: SubcontractorHomePayload }) {
    return (
        <div className="flex flex-col gap-8">
            <p className="text-muted-foreground text-lg">{home.subtitle}</p>
            <MetricGrid metrics={home.metrics} />
            <section className="dashboard-card">
                <h2 className="mb-4 text-2xl font-semibold">
                    My Assigned Projects
                </h2>
                <div className="border-border bg-card/80 overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Project</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead>My Role</TableHead>
                                <TableHead>Current Task</TableHead>
                                <TableHead>Due Date</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {home.project_rows.map((project) => (
                                <TableRow key={project.id}>
                                    <TableCell>
                                        <Link
                                            href={route(
                                                'projects.show',
                                                project.slug,
                                            )}
                                            className="flex items-center gap-3"
                                        >
                                            <div className="pv-thumb size-12 rounded-md" />
                                            <div>
                                                <div className="font-semibold">
                                                    {project.name}
                                                </div>
                                                <div className="text-muted-foreground text-xs">
                                                    {project.code}
                                                </div>
                                            </div>
                                        </Link>
                                    </TableCell>
                                    <TableCell>{project.location}</TableCell>
                                    <TableCell>{project.role_label}</TableCell>
                                    <TableCell>
                                        {project.current_task}
                                    </TableCell>
                                    <TableCell>{project.due_date}</TableCell>
                                    <TableCell>
                                        <Badge
                                            className={
                                                project.work_status ===
                                                'Upcoming'
                                                    ? 'bg-pv-blue/20 text-pv-blue'
                                                    : 'bg-pv-green/20 text-pv-green'
                                            }
                                        >
                                            {project.work_status}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </section>
            <div className="dashboard-card border-pv-blue/30 bg-pv-blue/10 rounded-xl border p-4 text-sm">
                Check the Timeline and Approvals sections for task details,
                required approvals, and upcoming on-site dates.
            </div>
            <div className="grid gap-5 xl:grid-cols-3">
                <InfoCard title="This Week">
                    {home.this_week.map((item) => (
                        <div key={`${item.title}-${item.project}`}>
                            <div className="font-semibold">{item.title}</div>
                            <div className="text-muted-foreground text-sm">
                                {item.project} · {item.date_range}
                            </div>
                        </div>
                    ))}
                </InfoCard>
                <InfoCard title="Waiting On">
                    {home.waiting_on.map((item, index) => (
                        <div key={item} className="flex items-center gap-2">
                            <Circle
                                className={
                                    index === 0
                                        ? 'fill-primary text-primary size-2'
                                        : 'fill-pv-red text-pv-red size-2'
                                }
                            />
                            {item}
                        </div>
                    ))}
                </InfoCard>
                <InfoCard title="Subcontractor Notes">
                    <p className="text-muted-foreground">
                        Only assigned projects and approved selections are
                        visible. Payment and client messages are hidden from
                        this role.
                    </p>
                </InfoCard>
            </div>
        </div>
    );
}

function ClientDashboard({ home }: { home: ClientHomePayload }) {
    if (!home.project) {
        return null;
    }

    return (
        <div className="flex flex-col gap-8">
            <div className="text-pv-green flex items-center gap-2 text-sm font-semibold uppercase">
                <Circle className="fill-pv-green text-pv-green size-3" />
                {home.project.status_label}
            </div>
            <div className="grid gap-5 lg:grid-cols-3">
                <MetricPanel
                    label="Next Step"
                    value={home.project.next_step}
                    detail={home.project.date_range ?? ''}
                />
                <MetricPanel
                    label="Approvals"
                    value={home.project.approvals_pending}
                    detail="Pending your review"
                />
                <MetricPanel
                    label="Payments"
                    value={home.project.payments_paid}
                    detail={`Paid of ${home.project.payments_total}`}
                    gold
                />
            </div>
            <section className="dashboard-card">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-2xl font-semibold">Recent Updates</h2>
                    <Link
                        href={route('dashboard')}
                        className="text-primary text-sm underline underline-offset-4"
                    >
                        View All
                    </Link>
                </div>
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-5">
                    {home.updates.map((update) => (
                        <Card
                            key={`${update.title}-${update.date}`}
                            className="pv-card"
                        >
                            <div
                                className="h-30 bg-cover bg-center"
                                style={{
                                    backgroundImage: update.image_url
                                        ? `linear-gradient(135deg, rgba(216,168,79,.28), rgba(8,13,15,.28)), url(${update.image_url})`
                                        : undefined,
                                }}
                            />
                            <CardContent className="pt-4">
                                <div className="text-muted-foreground text-sm">
                                    {update.date}
                                </div>
                                <div className="font-semibold">
                                    {update.title}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </section>
            <div className="grid gap-5 xl:grid-cols-3">
                <InfoCard title="Project Clarity">
                    <p className="text-muted-foreground">
                        Your current project phase, next step, and important
                        decisions are always visible in one calm dashboard.
                    </p>
                </InfoCard>
                <InfoCard title="Pending Decisions">
                    <div className="font-semibold">
                        {home.project.approvals_pending} approvals needed
                    </div>
                    <p className="text-muted-foreground">
                        {home.project.next_step_copy}
                    </p>
                </InfoCard>
                <InfoCard title="Payment Snapshot">
                    <div className="text-primary">
                        {home.project.payment_percent}%
                    </div>
                    <AnimatedProgress value={home.project.payment_percent} />
                    <p className="text-muted-foreground">
                        {home.project.payments_paid} paid of{' '}
                        {home.project.payments_total}
                    </p>
                </InfoCard>
            </div>
        </div>
    );
}

function SuperAdminDashboard({
    companies,
    projects,
    demoAccounts,
}: {
    companies: CompanyPayload[];
    projects: ProjectCardPayload[];
    demoAccounts: { label: string; email: string }[];
}) {
    return (
        <div className="grid gap-5 xl:grid-cols-3">
            <InfoCard title="Platform Overview">
                <div className="grid grid-cols-2 gap-4">
                    <MetricPanel
                        label="Companies"
                        value={companies.length}
                        detail="Demo tenants"
                    />
                    <MetricPanel
                        label="Projects"
                        value={projects.length}
                        detail="Visible projects"
                    />
                </div>
            </InfoCard>
            <InfoCard title="Component Arsenal">
                <p className="text-muted-foreground">
                    Review ProjectVista UI tokens, cards, form fields, tables,
                    charts, progress components, and sidebar patterns.
                </p>
                <Link
                    href={route('super-admin.components')}
                    className="text-primary mt-4 inline-flex text-sm underline underline-offset-4"
                >
                    Open component library
                </Link>
            </InfoCard>
            <InfoCard title="Demo Accounts">
                {demoAccounts.map((account) => (
                    <div key={account.email}>
                        <div className="font-semibold">{account.label}</div>
                        <div className="text-muted-foreground text-sm">
                            {account.email}
                        </div>
                    </div>
                ))}
            </InfoCard>
        </div>
    );
}

function MetricPanel({
    label,
    value,
    detail,
    gold = false,
}: {
    label: string;
    value: string | number;
    detail: string;
    gold?: boolean;
}) {
    return (
        <Card className="dashboard-card pv-card">
            <CardContent className="flex min-h-28 flex-col justify-center">
                <div className="text-muted-foreground text-xs tracking-[0.18em] uppercase">
                    {label}
                </div>
                <div
                    className={
                        gold ? 'text-primary mt-2 text-3xl' : 'mt-2 text-3xl'
                    }
                >
                    {value}
                </div>
                <div className="text-muted-foreground mt-2 text-sm">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function InfoCard({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card className="dashboard-card pv-card">
            <CardHeader>
                <CardTitle className="uppercase">{title}</CardTitle>
            </CardHeader>
            <CardContent className="flex min-h-28 flex-col gap-3">
                {children}
            </CardContent>
        </Card>
    );
}
