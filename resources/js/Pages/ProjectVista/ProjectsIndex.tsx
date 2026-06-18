import { AnimatedProgress } from '@/Components/ProjectVista/AnimatedProgress';
import { DataTable } from '@/Components/ProjectVista/DataTable';
import { DeleteIconButton } from '@/Components/ProjectVista/DeleteIconButton';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
} from '@/Components/ui/select';
import { useConfirm } from '@/Context/ConfirmContext';
import { cn } from '@/lib/utils';
import {
    CompanyPayload,
    DashboardMetricPayload,
    ProjectIndexFiltersPayload,
    ProjectIndexRowPayload,
    ProjectPayload,
    ProjectVistaRole,
} from '@/types/projectvista';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Circle } from 'lucide-react';
import { useMemo, useState } from 'react';

interface ProjectsIndexProps {
    role: ProjectVistaRole;
    title: string;
    eyebrow: string;
    subtitle: string;
    company: CompanyPayload | null;
    primaryProject: ProjectPayload | null;
    metrics: DashboardMetricPayload[];
    rows: ProjectIndexRowPayload[];
    filters: ProjectIndexFiltersPayload;
    navBadges: Record<string, number>;
    totalCount: number;
}

export default function ProjectsIndex({
    role,
    title,
    eyebrow,
    subtitle,
    company,
    primaryProject,
    metrics,
    rows,
    filters,
    navBadges,
    totalCount,
}: ProjectsIndexProps) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [manager, setManager] = useState('all');
    const [client, setClient] = useState('all');
    const confirm = useConfirm();

    const filteredRows = useMemo(() => {
        const term = search.trim().toLowerCase();

        return rows.filter((row) => {
            const matchesSearch =
                term.length === 0 ||
                [
                    row.name,
                    row.code,
                    row.location,
                    row.manager,
                    row.client,
                    row.next_step,
                    row.current_task,
                    row.role_label,
                ]
                    .filter(Boolean)
                    .some((value) => value!.toLowerCase().includes(term));
            const matchesStatus =
                status === 'all' || row.status_label === status;
            const matchesManager =
                manager === 'all' || String(row.manager_id) === manager;
            const matchesClient =
                client === 'all' || String(row.client_id) === client;

            return (
                matchesSearch &&
                matchesStatus &&
                matchesManager &&
                matchesClient
            );
        });
    }, [client, manager, rows, search, status]);

    const isSubcontractor = role === 'subcontractor';
    const canCreateProject = [
        'super_admin',
        'company_admin',
        'company_manager',
    ].includes(role);
    const showMetricGrid =
        role !== 'company_admin' &&
        role !== 'company_manager' &&
        role !== 'subcontractor';
    const primaryAction = isSubcontractor
        ? { label: 'Schedule' }
        : canCreateProject
          ? { label: '+ New Project', href: route('projects.create') }
          : undefined;
    const deleteProject = async (project: ProjectIndexRowPayload) => {
        const confirmed = await confirm({
            title: 'Delete project?',
            message: (
                <>
                    This will remove{' '}
                    <span className="text-foreground font-semibold">
                        {project.name}
                    </span>{' '}
                    from the project list.
                </>
            ),
            confirmText: 'Delete Project',
            danger: true,
            requireExplicitAction: true,
        });

        if (!confirmed) {
            return;
        }

        router.delete(route('projects.destroy', project.slug), {
            preserveScroll: true,
        });
    };

    return (
        <ProjectVistaShell
            title={title}
            eyebrow={eyebrow}
            role={role}
            project={primaryProject}
            company={company}
            navBadges={navBadges}
            primaryAction={primaryAction}
        >
            <Head title="Projects" />
            <div className="flex flex-col gap-8">
                <p className="text-muted-foreground text-lg">{subtitle}</p>
                {showMetricGrid ? <MetricGrid metrics={metrics} /> : null}

                <section className="flex flex-col gap-5">
                    <div>
                        <h2 className="text-2xl font-semibold">
                            {isSubcontractor
                                ? 'My Assigned Projects'
                                : role === 'company_manager'
                                  ? 'My Open Projects'
                                  : 'All Open Projects'}
                        </h2>
                    </div>

                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center">
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={
                                    isSubcontractor
                                        ? 'Search projects...'
                                        : role === 'company_manager'
                                          ? 'Search assigned projects...'
                                          : 'Search projects...'
                                }
                                className="h-11 md:w-72"
                            />
                            <FilterSelect
                                value={status}
                                onChange={setStatus}
                                label="All Statuses"
                                options={filters.statuses}
                            />
                            {role === 'company_admin' ||
                            role === 'super_admin' ? (
                                <FilterSelect
                                    value={manager}
                                    onChange={setManager}
                                    label="All Managers"
                                    options={filters.managers.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    }))}
                                />
                            ) : null}
                            {role === 'company_manager' ? (
                                <FilterSelect
                                    value={client}
                                    onChange={setClient}
                                    label="All Clients"
                                    options={filters.clients.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    }))}
                                />
                            ) : null}
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11 px-4"
                        >
                            Filters
                        </Button>
                    </div>

                    {isSubcontractor ? (
                        <SubcontractorTable rows={filteredRows} />
                    ) : (
                        <BusinessTable
                            role={role}
                            rows={filteredRows}
                            onDeleteProject={deleteProject}
                        />
                    )}

                    <p className="text-muted-foreground text-sm">
                        Showing {filteredRows.length} of {totalCount} projects
                    </p>
                </section>
            </div>
        </ProjectVistaShell>
    );
}

function MetricGrid({ metrics }: { metrics: DashboardMetricPayload[] }) {
    return (
        <div
            className={cn(
                'grid gap-5 md:grid-cols-2',
                metrics.length === 4 ? 'xl:grid-cols-4' : 'xl:grid-cols-5',
            )}
        >
            {metrics.map((metric) => (
                <Card key={metric.label} className="pv-card">
                    <CardContent className="flex min-h-32 flex-col justify-center">
                        <div className="text-muted-foreground text-xs tracking-[0.18em] uppercase">
                            {metric.label}
                        </div>
                        <div
                            className={cn(
                                'mt-3 text-4xl font-medium',
                                metric.tone === 'gold' && 'text-primary',
                            )}
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

function FilterSelect({
    value,
    onChange,
    label,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    label: string;
    options: (string | { value: string; label: string })[];
}) {
    const selectedLabel =
        value === 'all'
            ? label
            : options
                  .map((option) =>
                      typeof option === 'string'
                          ? { value: option, label: option }
                          : option,
                  )
                  .find((option) => option.value === value)?.label;

    return (
        <Select
            value={value}
            onValueChange={(nextValue) => onChange(String(nextValue ?? 'all'))}
        >
            <SelectTrigger className="h-11 min-w-40 px-4 data-[size=default]:h-11">
                <span
                    data-slot="select-value"
                    className="flex flex-1 items-center text-left"
                >
                    {selectedLabel ?? label}
                </span>
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectItem value="all">{label}</SelectItem>
                    {options.map((option) => {
                        const optionValue =
                            typeof option === 'string' ? option : option.value;
                        const optionLabel =
                            typeof option === 'string' ? option : option.label;

                        return (
                            <SelectItem key={optionValue} value={optionValue}>
                                {optionLabel}
                            </SelectItem>
                        );
                    })}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}

function BusinessTable({
    role,
    rows,
    onDeleteProject,
}: {
    role: ProjectVistaRole;
    rows: ProjectIndexRowPayload[];
    onDeleteProject: (project: ProjectIndexRowPayload) => void;
}) {
    const showManager = role === 'company_admin' || role === 'super_admin';
    const showActions = [
        'super_admin',
        'company_admin',
        'company_manager',
    ].includes(role);
    const columns = useMemo<ColumnDef<ProjectIndexRowPayload>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Project',
                cell: ({ row }) => <ProjectCell project={row.original} />,
            },
            {
                accessorKey: 'location',
                header: 'Location',
            },
            ...(showManager
                ? ([
                      {
                          accessorKey: 'manager',
                          header: 'Manager',
                          cell: ({ row }) =>
                              row.original.manager ?? 'Unassigned',
                      },
                  ] satisfies ColumnDef<ProjectIndexRowPayload>[])
                : []),
            {
                accessorKey: 'progress',
                header: 'Progress',
                cell: ({ row }) => (
                    <div className="flex min-w-32 flex-col gap-1">
                        <span className="font-semibold">
                            {row.original.progress}%
                        </span>
                        <AnimatedProgress value={row.original.progress} />
                    </div>
                ),
            },
            {
                accessorKey: 'next_step',
                header: 'Next Step',
                cell: ({ row }) => (
                    <>
                        <div className="font-medium">
                            {row.original.next_step}
                        </div>
                        <div className="text-muted-foreground text-sm">
                            {row.original.date_range}
                        </div>
                    </>
                ),
            },
            {
                accessorKey: 'approvals',
                header: 'Approvals',
                cell: ({ row }) => (
                    <span className="inline-flex items-center gap-2">
                        <Circle className="fill-pv-red text-pv-red size-2" />
                        {row.original.approvals ?? 0}
                    </span>
                ),
            },
            {
                accessorKey: 'payment_percent',
                header: 'Payments',
                cell: ({ row }) => (
                    <div className="min-w-36">
                        <div className="font-semibold">
                            {row.original.payment_percent ?? 0}%
                        </div>
                        <AnimatedProgress
                            value={row.original.payment_percent ?? 0}
                        />
                        <div className="text-muted-foreground text-xs">
                            {row.original.payment_paid} of{' '}
                            {row.original.payment_total}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'status_label',
                header: 'Status',
                cell: ({ row }) => (
                    <ProjectStatusBadge label={row.original.status_label} />
                ),
            },
            {
                accessorKey: 'messages',
                header: 'Messages',
                cell: ({ row }) => (
                    <span className="inline-flex items-center gap-2">
                        <Circle className="fill-pv-blue text-pv-blue size-2" />
                        {row.original.messages ?? 0}
                    </span>
                ),
            },
            ...(showActions
                ? ([
                      {
                          id: 'actions',
                          header: () => (
                              <span className="sr-only">Actions</span>
                          ),
                          size: 48,
                          cell: ({ row }) => (
                              <div className="text-right">
                                  {row.original.can_delete_project ? (
                                      <DeleteIconButton
                                          label={`Delete ${row.original.name}`}
                                          onClick={() =>
                                              onDeleteProject(row.original)
                                          }
                                      />
                                  ) : null}
                              </div>
                          ),
                      },
                  ] satisfies ColumnDef<ProjectIndexRowPayload>[])
                : []),
        ],
        [onDeleteProject, showActions, showManager],
    );

    return (
        <div className="border-border bg-card/80 overflow-hidden rounded-xl border">
            <DataTable
                columns={columns}
                data={rows}
                getRowId={(project) => project.id.toString()}
            />
        </div>
    );
}

function SubcontractorTable({ rows }: { rows: ProjectIndexRowPayload[] }) {
    const columns = useMemo<ColumnDef<ProjectIndexRowPayload>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Project',
                cell: ({ row }) => <ProjectCell project={row.original} />,
            },
            { accessorKey: 'location', header: 'Location' },
            { accessorKey: 'role_label', header: 'Your Role' },
            { accessorKey: 'current_task', header: 'Current Task' },
            { accessorKey: 'start_date', header: 'Start' },
            { accessorKey: 'due_date', header: 'Due' },
            {
                accessorKey: 'status_label',
                header: 'Status',
                cell: ({ row }) => (
                    <ProjectStatusBadge label={row.original.status_label} />
                ),
            },
        ],
        [],
    );

    return (
        <div className="border-border bg-card/80 overflow-hidden rounded-xl border">
            <DataTable
                columns={columns}
                data={rows}
                getRowId={(project) => project.id.toString()}
            />
        </div>
    );
}

function ProjectCell({ project }: { project: ProjectIndexRowPayload }) {
    return (
        <Link
            href={route('projects.show', project.slug)}
            className="flex items-center gap-3"
        >
            <div className="pv-thumb size-12 rounded-md" />
            <div>
                <div className="font-semibold">{project.name}</div>
                <div className="text-muted-foreground text-xs">
                    {project.code}
                </div>
            </div>
        </Link>
    );
}

function ProjectStatusBadge({ label }: { label: string }) {
    return (
        <Badge
            className={cn(
                label === 'Needs Approval' && 'bg-primary/20 text-primary',
                label === 'On Schedule' && 'bg-pv-blue/20 text-pv-blue',
                label === 'Upcoming' && 'bg-pv-blue/20 text-pv-blue',
                (label === 'In Progress' || label === 'Active') &&
                    'bg-pv-green/20 text-pv-green',
            )}
        >
            {label}
        </Badge>
    );
}
