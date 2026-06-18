import { DeleteIconButton } from '@/Components/ProjectVista/DeleteIconButton';
import { DataTable } from '@/Components/ProjectVista/DataTable';
import { ProjectVistaModal } from '@/Components/ProjectVista/ProjectVistaModal';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { useConfirm } from '@/Context/ConfirmContext';
import { useDirtySaveToast } from '@/hooks/useDirtySaveToast';
import { cn } from '@/lib/utils';
import {
    ProjectPayload,
    TimelineConflictPayload,
    TimelineTaskPayload,
    TimelineWorkspacePayload,
} from '@/types/projectvista';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { CalendarDays, Plus } from 'lucide-react';
import {
    FormEvent,
    MouseEvent,
    ReactNode,
    useCallback,
    useMemo,
    useState,
} from 'react';

interface TimelineProps {
    project: ProjectPayload;
    timeline: TimelineWorkspacePayload;
}

type TimelineTaskForm = {
    project_id: string;
    predecessor_task_id: string;
    title: string;
    phase: string;
    description: string;
    status: string;
    duration_days: string;
    priority: string;
    customer_urgency: string;
    is_schedule_locked: boolean;
    schedule_locked_reason: string;
    assigned_subcontractor_id: string;
    subcontractor_type_id: string;
    internal_only: boolean;
    requires_acknowledgement: boolean;
    is_job_site_ready: boolean;
    are_materials_ready: boolean;
    is_customer_approval_required: boolean;
    is_customer_approval_received: boolean;
    internal_notes: string;
    customer_notes: string;
    acknowledge_conflicts: boolean;
};

type FlashProps = {
    flash?: {
        timeline_conflicts?: TimelineConflictPayload[] | null;
        selected_timeline_task_id?: number | null;
    };
};

export default function Timeline({ project, timeline }: TimelineProps) {
    if (!timeline.can_edit) {
        return <ReadOnlyTimeline project={project} timeline={timeline} />;
    }

    return <InternalTimeline project={project} timeline={timeline} />;
}

function InternalTimeline({ project, timeline }: TimelineProps) {
    const page = usePage();
    const props = page.props as unknown as FlashProps;
    const flashConflicts = props.flash?.timeline_conflicts ?? [];
    const flashSelectedTaskId = props.flash?.selected_timeline_task_id;
    const initialSelectedTask =
        timeline.tasks.find((task) => task.id === flashSelectedTaskId) ??
        timeline.tasks[0] ??
        null;

    const [selectedTaskId, setSelectedTaskId] = useState<number | null>(
        initialSelectedTask?.id ?? null,
    );
    const [localConflicts, setLocalConflicts] =
        useState<TimelineConflictPayload[]>(flashConflicts);
    const [scopeFilter, setScopeFilter] = useState('open');
    const [projectFilter, setProjectFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [taskModalOpen, setTaskModalOpen] = useState(false);
    const confirm = useConfirm();

    const selectedTask = useMemo(
        () =>
            selectedTaskId === null
                ? null
                : (timeline.tasks.find((task) => task.id === selectedTaskId) ??
                  null),
        [selectedTaskId, timeline.tasks],
    );
    const form = useForm<TimelineTaskForm>(
        taskFormDefaults(selectedTask, project, timeline),
    );

    const filteredTasks = useMemo(
        () =>
            timeline.tasks.filter((task) => {
                const openMatch =
                    scopeFilter !== 'open' ||
                    statusFilter !== 'all' ||
                    task.status !== 'complete';
                const projectMatch =
                    projectFilter === 'all' ||
                    String(task.project_id) === projectFilter;
                const statusMatch =
                    statusFilter === 'all' || task.status === statusFilter;

                return openMatch && projectMatch && statusMatch;
            }),
        [projectFilter, scopeFilter, statusFilter, timeline.tasks],
    );

    const visibleConflicts = localConflicts.length
        ? localConflicts
        : timeline.conflicts;

    const setTaskFormData = (task: TimelineTaskPayload | null) => {
        const defaults = taskFormDefaults(task, project, timeline);

        form.setDefaults(defaults);
        form.setData(defaults);
        form.clearErrors();
    };

    const selectTask = (task: TimelineTaskPayload) => {
        setSelectedTaskId(task.id);
        setLocalConflicts([]);
        setTaskFormData(task);
        setTaskModalOpen(true);
    };

    const startNewTask = () => {
        setSelectedTaskId(null);
        setLocalConflicts([]);
        setTaskFormData(null);
        setTaskModalOpen(true);
    };

    const closeTaskModal = () => {
        setTaskModalOpen(false);
        setLocalConflicts([]);
        form.clearErrors();

        if (selectedTask) {
            setTaskFormData(selectedTask);

            return;
        }

        setSelectedTaskId(initialSelectedTask?.id ?? null);
        setTaskFormData(initialSelectedTask);
    };

    const saveTask = (acknowledgeConflicts = false) => {
        form.transform((data) => ({
            ...data,
            duration_days: Math.max(1, Number(data.duration_days || 1)),
            priority: Math.max(1, Number(data.priority || 2)),
            customer_urgency: Math.max(0, Number(data.customer_urgency || 1)),
            acknowledge_conflicts: acknowledgeConflicts,
        }));

        if (selectedTask) {
            form.patch(
                route('projects.timeline.tasks.update', [
                    project.slug,
                    selectedTask.id,
                ]),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setTaskModalOpen(false);
                        setLocalConflicts([]);
                    },
                },
            );

            return;
        }

        form.post(route('projects.timeline.tasks.store', project.slug), {
            preserveScroll: true,
            onSuccess: () => {
                setTaskModalOpen(false);
                setLocalConflicts([]);
            },
        });
    };

    const submitTask = (
        event: FormEvent | MouseEvent<HTMLButtonElement>,
        acknowledgeConflicts = false,
    ) => {
        event.preventDefault();
        saveTask(acknowledgeConflicts);
    };

    const deleteTask = useCallback(async (task: TimelineTaskPayload) => {
        if (task.is_system) {
            return;
        }

        const confirmed = await confirm({
            title: 'Delete timeline task?',
            message: (
                <>
                    This will remove{' '}
                    <span className="text-foreground font-semibold">
                        {task.title}
                    </span>{' '}
                    from the timeline.
                </>
            ),
            confirmText: 'Delete Task',
            danger: true,
            requireExplicitAction: true,
        });

        if (!confirmed) {
            return;
        }

        router.delete(
            route('projects.timeline.tasks.destroy', [project.slug, task.id]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLocalConflicts([]);

                    if (selectedTaskId === task.id) {
                        setSelectedTaskId(null);
                    }
                },
            },
        );
    }, [confirm, project.slug, selectedTaskId]);

    const taskColumns = useMemo<ColumnDef<TimelineTaskPayload>[]>(
        () => [
            {
                accessorKey: 'project_name',
                header: 'Project',
                cell: ({ row }) => (
                    <div className="font-semibold">
                        {row.original.project_name}
                    </div>
                ),
            },
            {
                accessorKey: 'title',
                header: 'Task',
                cell: ({ row }) => (
                    <div>
                        <div className="font-semibold">{row.original.title}</div>
                        <div className="text-muted-foreground text-xs">
                            {row.original.phase ?? 'Construction'}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <StatusPill
                        status={row.original.status}
                        label={row.original.status_label}
                    />
                ),
            },
            {
                accessorKey: 'assigned_subcontractor_title',
                header: 'Sub-Contractor',
                cell: ({ row }) =>
                    row.original.assigned_subcontractor_title ??
                    row.original.assigned_subcontractor_name ??
                    'Unassigned',
            },
            {
                accessorKey: 'readiness_status',
                header: 'Readiness',
                cell: ({ row }) => (
                    <div className="flex flex-col gap-1">
                        <Badge variant="secondary">
                            {humanizeSelectValue(
                                row.original.readiness_status ?? 'not_ready',
                            )}
                        </Badge>
                        {row.original.block_summary ? (
                            <span className="text-muted-foreground text-xs">
                                {row.original.block_summary}
                            </span>
                        ) : null}
                    </div>
                ),
            },
            {
                accessorKey: 'schedule_score',
                header: 'Score',
                cell: ({ row }) => row.original.schedule_score ?? 0,
            },
            {
                accessorKey: 'starts_on',
                header: 'Start Date',
                cell: ({ row }) => row.original.starts_on ?? 'TBD',
            },
            {
                accessorKey: 'due_on',
                header: 'End Date',
                cell: ({ row }) => row.original.due_on ?? 'TBD',
            },
            {
                id: 'duration',
                header: 'Duration',
                cell: ({ row }) => durationLabel(row.original),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                size: 48,
                cell: ({ row }) => (
                    <div className="text-right">
                        {row.original.is_system ? null : (
                            <DeleteIconButton
                                label={`Delete ${row.original.title}`}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    void deleteTask(row.original);
                                }}
                            />
                        )}
                    </div>
                ),
            },
        ],
        [deleteTask],
    );

    return (
        <ProjectVistaShell
            title="Timeline"
            eyebrow={roleEyebrow(timeline.role)}
            role={project.role}
            project={project}
        >
            <Head title={`${project.name} Timeline`} />
            <div className="flex flex-col gap-6">
                <header className="flex flex-col gap-4 border-b border-white/10 pb-6 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-4xl font-semibold md:text-5xl">
                            Timeline
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            Open timeline tasks across projects. Dates are
                            calculated from each project schedule.
                        </p>
                    </div>
                    <Button
                        type="button"
                        onClick={() =>
                            router.post(
                                route(
                                    'companies.schedule.recalculate',
                                    project.company.slug,
                                ),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <CalendarDays data-icon="inline-start" />
                        Recalculate
                    </Button>
                </header>

                <section className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <Metric
                        label="Open Tasks"
                        value={timeline.metrics.open_tasks}
                        detail="Complete tasks hidden"
                    />
                    <Metric
                        label="Conflicts"
                        value={visibleConflicts.length}
                        detail="After reschedule"
                        destructive
                    />
                    <Metric
                        label="Ready"
                        value={timeline.metrics.ready_tasks}
                        detail="Eligible work"
                    />
                    <Metric
                        label="Blocked"
                        value={timeline.metrics.blocked_tasks}
                        detail="Needs action"
                        destructive={timeline.metrics.blocked_tasks > 0}
                    />
                    <Metric
                        label="Due This Week"
                        value={timeline.metrics.due_this_week}
                        detail="Open tasks"
                    />
                    <Metric
                        label="Sub Types"
                        value={timeline.metrics.sub_types}
                        detail="Trade categories"
                    />
                </section>

                <section className="flex flex-col gap-5">
                    <Card className="pv-card">
                        <CardContent className="flex flex-wrap items-center gap-3 py-4">
                            <div className="mr-2 text-xl font-semibold">
                                Filters
                            </div>
                            <FilterSelect
                                value={scopeFilter}
                                onChange={setScopeFilter}
                                options={[
                                    {
                                        value: 'open',
                                        label: 'All Open Tasks',
                                    },
                                    { value: 'all', label: 'All Tasks' },
                                ]}
                            />
                            <FilterSelect
                                value={projectFilter}
                                onChange={setProjectFilter}
                                options={[
                                    { value: 'all', label: 'All Projects' },
                                    ...timeline.filters.projects.map(
                                        (option) => ({
                                            value: option.id.toString(),
                                            label: option.name,
                                        }),
                                    ),
                                ]}
                            />
                            <FilterSelect
                                value={statusFilter}
                                onChange={setStatusFilter}
                                options={[
                                    { value: 'all', label: 'All Statuses' },
                                    ...timeline.filters.statuses,
                                ]}
                            />
                            <Button
                                type="button"
                                onClick={startNewTask}
                                className="ml-auto"
                            >
                                <Plus data-icon="inline-start" />
                                Add Task
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="pv-card">
                        <CardHeader className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <CardTitle className="text-2xl">
                                    Timeline Tasks
                                </CardTitle>
                                <CardDescription>
                                    All open tasks are selected by default.
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                columns={taskColumns}
                                data={filteredTasks}
                                emptyMessage="No timeline tasks match these filters."
                                getRowId={(task) => task.id.toString()}
                                rowClassName={(row) =>
                                    cn(
                                        row.original.is_system &&
                                            'cursor-default',
                                        selectedTask?.id === row.original.id &&
                                            'bg-primary/15 outline-primary/40 outline',
                                    )
                                }
                                onRowClick={(task) => {
                                    if (!task.is_system) {
                                        selectTask(task);
                                    }
                                }}
                            />
                        </CardContent>
                        <CardFooter className="text-muted-foreground text-sm">
                            {selectedTask
                                ? `Selected task: ${selectedTask.project_name} · ${selectedTask.title}`
                                : 'Open tasks only · complete tasks are hidden unless the filter is changed.'}
                        </CardFooter>
                    </Card>
                </section>

                <ProjectVistaModal
                    show={taskModalOpen}
                    maxWidth="3xl"
                    onClose={closeTaskModal}
                >
                    <TimelineTaskFormPanel
                        timeline={timeline}
                        selectedTask={selectedTask}
                        form={form}
                        conflicts={localConflicts}
                        onSubmit={submitTask}
                        onSave={saveTask}
                        onCancel={closeTaskModal}
                    />
                </ProjectVistaModal>

                <ConflictPreviewCard conflicts={visibleConflicts} />
            </div>
        </ProjectVistaShell>
    );
}

function TimelineTaskFormPanel({
    timeline,
    selectedTask,
    form,
    conflicts,
    onSubmit,
    onSave,
    onCancel,
}: {
    timeline: TimelineWorkspacePayload;
    selectedTask: TimelineTaskPayload | null;
    form: ReturnType<typeof useForm<TimelineTaskForm>>;
    conflicts: TimelineConflictPayload[];
    onSubmit: (
        event: FormEvent | MouseEvent<HTMLButtonElement>,
        acknowledgeConflicts?: boolean,
    ) => void;
    onSave: (acknowledgeConflicts?: boolean) => void;
    onCancel: () => void;
}) {
    const editingExisting = selectedTask !== null;
    const selectedProjectName =
        timeline.filters.projects.find(
            (option) => option.id.toString() === form.data.project_id,
        )?.name ?? 'Select project';
    const selectedStatusLabel =
        timeline.filters.statuses.find(
            (option) => option.value === form.data.status,
        )?.label ?? humanizeSelectValue(form.data.status);
    const selectedSubcontractorLabel =
        form.data.assigned_subcontractor_id === ''
            ? 'Unassigned'
            : (timeline.subcontractors.find(
                  (option) =>
                      option.id.toString() ===
                      form.data.assigned_subcontractor_id,
              )?.name ?? 'Unassigned');
    const selectedSubcontractorTypeLabel =
        form.data.subcontractor_type_id === ''
            ? 'Unassigned'
            : (timeline.filters.subcontractor_types.find(
                  (option) =>
                      option.id.toString() === form.data.subcontractor_type_id,
              )?.name ?? 'Unassigned');
    const projectSubcontractors =
        timeline.filters.project_subcontractors[form.data.project_id] ?? [];
    const quickEditSubcontractorLabel =
        form.data.assigned_subcontractor_id === ''
            ? 'Unassigned'
            : (projectSubcontractors.find(
                  (option) =>
                      option.id.toString() ===
                      form.data.assigned_subcontractor_id,
              )?.name ?? 'Unassigned');
    const selectedProjectTasks = timeline.tasks
        .filter((task) => task.project_id?.toString() === form.data.project_id)
        .sort(
            (first, second) =>
                (first.sequence_order ?? first.sort_order) -
                (second.sequence_order ?? second.sort_order),
        );
    const selectedPredecessorLabel =
        selectedProjectTasks.find(
            (task) => task.id.toString() === form.data.predecessor_task_id,
        )?.title ?? 'Select predecessor';

    const quickEditTask = selectedTask;

    useDirtySaveToast({
        id: quickEditTask ? `timeline-task-${quickEditTask.id}` : 'timeline-task',
        isDirty: editingExisting && form.isDirty,
        isProcessing: form.processing,
        message: 'Timeline task changes need to be saved.',
        onSave: () => onSave(false),
        onCancel,
    });

    if (editingExisting && quickEditTask) {
        return (
            <div className="flex max-h-[calc(100vh-3rem)] flex-col">
                <div className="border-border border-b px-6 py-5">
                    <h2 className="text-2xl font-semibold">
                        Quick Edit Task
                    </h2>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {quickEditTask.project_name} · {quickEditTask.title}
                    </p>
                </div>
                <div className="flex flex-col gap-6 overflow-y-auto px-6 py-5">
                    {conflicts.length > 0 ? (
                        <Alert variant="destructive">
                            <AlertTitle>
                                {conflicts.length} Conflict
                                {conflicts.length === 1 ? '' : 's'} Detected
                            </AlertTitle>
                            <AlertDescription>
                                Saving will recalculate the schedule from these
                                values.
                            </AlertDescription>
                        </Alert>
                    ) : null}
                    <form
                        id="timeline-task-form"
                        onSubmit={(event) => onSubmit(event, false)}
                        className="flex flex-col gap-5"
                    >
                        <FieldGroup>
                            <Field data-invalid={!!form.errors.status}>
                                <FieldLabel>Status</FieldLabel>
                                <Select
                                    value={form.data.status}
                                    onValueChange={(value) =>
                                        form.setData('status', String(value))
                                    }
                                >
                                    <SelectTrigger className="w-full data-[size=default]:h-11">
                                        <SelectedSelectLabel>
                                            {selectedStatusLabel}
                                        </SelectedSelectLabel>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {timeline.filters.statuses.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldError>{form.errors.status}</FieldError>
                            </Field>

                            <TextField
                                label="Duration (Days)"
                                type="number"
                                value={form.data.duration_days}
                                error={form.errors.duration_days}
                                onChange={(value) =>
                                    form.setData('duration_days', value)
                                }
                            />

                            <TextField
                                label="Priority"
                                type="number"
                                value={form.data.priority}
                                error={form.errors.priority}
                                onChange={(value) =>
                                    form.setData('priority', value)
                                }
                            />

                            <TextField
                                label="Customer Urgency"
                                type="number"
                                value={form.data.customer_urgency}
                                error={form.errors.customer_urgency}
                                onChange={(value) =>
                                    form.setData('customer_urgency', value)
                                }
                            />

                            <Field
                                data-invalid={
                                    !!form.errors.assigned_subcontractor_id
                                }
                            >
                                <FieldLabel>Sub-Contractor</FieldLabel>
                                <Select
                                    value={
                                        form.data.assigned_subcontractor_id ||
                                        'unassigned'
                                    }
                                    onValueChange={(value) => {
                                        const nextValue =
                                            String(value) === 'unassigned'
                                                ? ''
                                                : String(value);
                                        const subcontractor =
                                            projectSubcontractors.find(
                                                (option) =>
                                                    option.id.toString() ===
                                                    nextValue,
                                            );

                                        form.setData({
                                            ...form.data,
                                            assigned_subcontractor_id:
                                                nextValue,
                                            subcontractor_type_id:
                                                subcontractor?.subcontractor_type_id?.toString() ||
                                                '',
                                        });
                                    }}
                                >
                                    <SelectTrigger className="w-full data-[size=default]:h-11">
                                        <SelectedSelectLabel>
                                            {quickEditSubcontractorLabel}
                                        </SelectedSelectLabel>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="unassigned">
                                                Unassigned
                                            </SelectItem>
                                            {projectSubcontractors.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.id}
                                                        value={option.id.toString()}
                                                    >
                                                        {option.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldError>
                                    {form.errors.assigned_subcontractor_id}
                                </FieldError>
                                {projectSubcontractors.length === 0 ? (
                                    <FieldDescription>
                                        No subcontractors are assigned to this
                                        project yet.
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <BooleanField
                                label="Lock schedule"
                                checked={form.data.is_schedule_locked}
                                onChange={(checked) =>
                                    form.setData('is_schedule_locked', checked)
                                }
                            />
                        </FieldGroup>
                    </form>
                </div>
            </div>
        );
    }

    return (
        <div className="flex max-h-[calc(100vh-3rem)] flex-col">
            <div className="border-border border-b px-6 py-5">
                <h2 className="text-2xl font-semibold">
                    Add Timeline Task
                </h2>
                <p className="text-muted-foreground mt-2 text-sm">
                    Create a new open task for a project.
                </p>
            </div>
            <div className="flex flex-col gap-6 overflow-y-auto px-6 py-5">
                {conflicts.length > 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>
                            {conflicts.length} Conflict
                            {conflicts.length === 1 ? '' : 's'} Detected
                        </AlertTitle>
                        <AlertDescription>
                            Saving will use the first available dates after the
                            selected predecessor.
                        </AlertDescription>
                    </Alert>
                ) : null}
                <form
                    id="timeline-task-form"
                    onSubmit={(event) => onSubmit(event, false)}
                    className="flex flex-col gap-5"
                >
                    <FieldGroup>
                        {!editingExisting ? (
                            <Field data-invalid={!!form.errors.project_id}>
                                <FieldLabel>Project</FieldLabel>
                                <Select
                                    value={form.data.project_id}
                                    onValueChange={(value) => {
                                        const nextProjectId = String(value);
                                        const nextPredecessor =
                                            timeline.tasks.find(
                                                (task) =>
                                                    task.project_id?.toString() ===
                                                    nextProjectId,
                                            );

                                        form.setData({
                                            ...form.data,
                                            project_id: nextProjectId,
                                            predecessor_task_id:
                                                nextPredecessor?.id.toString() ??
                                                '',
                                            assigned_subcontractor_id: '',
                                            subcontractor_type_id: '',
                                        });
                                    }}
                                >
                                    <SelectTrigger className="w-full data-[size=default]:h-11">
                                        <SelectedSelectLabel>
                                            {selectedProjectName}
                                        </SelectedSelectLabel>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {timeline.filters.projects.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.id}
                                                        value={option.id.toString()}
                                                    >
                                                        {option.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldError>
                                    {form.errors.project_id}
                                </FieldError>
                            </Field>
                        ) : null}

                        <Field
                            data-invalid={!!form.errors.predecessor_task_id}
                        >
                            <FieldLabel>Predecessor Task</FieldLabel>
                            <Select
                                value={form.data.predecessor_task_id}
                                onValueChange={(value) =>
                                    form.setData(
                                        'predecessor_task_id',
                                        String(value),
                                    )
                                }
                            >
                                <SelectTrigger className="w-full data-[size=default]:h-11">
                                    <SelectedSelectLabel>
                                        {selectedPredecessorLabel}
                                    </SelectedSelectLabel>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {selectedProjectTasks.map((task) => (
                                            <SelectItem
                                                key={task.id}
                                                value={task.id.toString()}
                                            >
                                                {task.title}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError>
                                {form.errors.predecessor_task_id}
                            </FieldError>
                            <FieldDescription>
                                The new task will start on the first available
                                date after this task.
                            </FieldDescription>
                        </Field>

                        <TextField
                            label="Task"
                            value={form.data.title}
                            error={form.errors.title}
                            onChange={(value) => form.setData('title', value)}
                        />
                        <TextField
                            label="Phase"
                            value={form.data.phase}
                            error={form.errors.phase}
                            onChange={(value) => form.setData('phase', value)}
                        />
                        <Field data-invalid={!!form.errors.status}>
                            <FieldLabel>Task Status</FieldLabel>
                            <Select
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData('status', String(value))
                                }
                            >
                                <SelectTrigger className="w-full data-[size=default]:h-11">
                                    <SelectedSelectLabel>
                                        {selectedStatusLabel}
                                    </SelectedSelectLabel>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {timeline.filters.statuses.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError>{form.errors.status}</FieldError>
                        </Field>

                        <TextField
                            label="Duration (Days)"
                            type="number"
                            value={form.data.duration_days}
                            error={form.errors.duration_days}
                            onChange={(value) =>
                                form.setData('duration_days', value)
                            }
                        />

                        <TextField
                            label="Priority"
                            type="number"
                            value={form.data.priority}
                            error={form.errors.priority}
                            onChange={(value) => form.setData('priority', value)}
                        />

                        <TextField
                            label="Customer Urgency"
                            type="number"
                            value={form.data.customer_urgency}
                            error={form.errors.customer_urgency}
                            onChange={(value) =>
                                form.setData('customer_urgency', value)
                            }
                        />

                        <Field
                            data-invalid={
                                !!form.errors.assigned_subcontractor_id
                            }
                        >
                            <FieldLabel>Assigned Sub-Contractor</FieldLabel>
                            <Select
                                value={
                                    form.data.assigned_subcontractor_id ||
                                    'unassigned'
                                }
                                onValueChange={(value) => {
                                    const nextValue =
                                        String(value) === 'unassigned'
                                            ? ''
                                            : String(value);
                                    const subcontractor =
                                        timeline.subcontractors.find(
                                            (option) =>
                                                option.id.toString() ===
                                                nextValue,
                                        );

                                    form.setData({
                                        ...form.data,
                                        assigned_subcontractor_id: nextValue,
                                        subcontractor_type_id:
                                            subcontractor?.subcontractor_type_id?.toString() ||
                                            form.data.subcontractor_type_id,
                                    });
                                }}
                            >
                                <SelectTrigger className="w-full data-[size=default]:h-11">
                                    <SelectedSelectLabel>
                                        {selectedSubcontractorLabel}
                                    </SelectedSelectLabel>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="unassigned">
                                            Unassigned
                                        </SelectItem>
                                        {timeline.subcontractors.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={option.id.toString()}
                                                >
                                                    {option.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError>
                                {form.errors.assigned_subcontractor_id}
                            </FieldError>
                        </Field>

                        <Field
                            data-invalid={!!form.errors.subcontractor_type_id}
                        >
                            <FieldLabel>Sub-Contractor Type</FieldLabel>
                            <Select
                                value={
                                    form.data.subcontractor_type_id ||
                                    'unassigned'
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'subcontractor_type_id',
                                        String(value) === 'unassigned'
                                            ? ''
                                            : String(value),
                                    )
                                }
                            >
                                <SelectTrigger className="w-full data-[size=default]:h-11">
                                    <SelectedSelectLabel>
                                        {selectedSubcontractorTypeLabel}
                                    </SelectedSelectLabel>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="unassigned">
                                            Unassigned
                                        </SelectItem>
                                        {timeline.filters.subcontractor_types.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={option.id.toString()}
                                                >
                                                    {option.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError>
                                {form.errors.subcontractor_type_id}
                            </FieldError>
                        </Field>

                        <FieldDescription>
                            Start and end dates are calculated automatically
                            from the predecessor and duration.
                        </FieldDescription>
                        <FieldGroup>
                            <FieldLabel>Readiness</FieldLabel>
                            <BooleanField
                                label="Internal Only"
                                checked={form.data.internal_only}
                                onChange={(checked) =>
                                    form.setData('internal_only', checked)
                                }
                            />
                            <BooleanField
                                label="Lock schedule"
                                checked={form.data.is_schedule_locked}
                                onChange={(checked) =>
                                    form.setData('is_schedule_locked', checked)
                                }
                            />
                            <BooleanField
                                label="Job site ready"
                                checked={form.data.is_job_site_ready}
                                onChange={(checked) =>
                                    form.setData('is_job_site_ready', checked)
                                }
                            />
                            <BooleanField
                                label="Materials ready"
                                checked={form.data.are_materials_ready}
                                onChange={(checked) =>
                                    form.setData('are_materials_ready', checked)
                                }
                            />
                            <BooleanField
                                label="Customer approval required"
                                checked={
                                    form.data.is_customer_approval_required
                                }
                                onChange={(checked) =>
                                    form.setData(
                                        'is_customer_approval_required',
                                        checked,
                                    )
                                }
                            />
                            <BooleanField
                                label="Customer approval received"
                                checked={
                                    form.data.is_customer_approval_received
                                }
                                onChange={(checked) =>
                                    form.setData(
                                        'is_customer_approval_received',
                                        checked,
                                    )
                                }
                            />
                        </FieldGroup>
                        <TextAreaField
                            label="Internal Notes"
                            value={form.data.internal_notes}
                            error={form.errors.internal_notes}
                            onChange={(value) =>
                                form.setData('internal_notes', value)
                            }
                        />
                        <TextAreaField
                            label="Customer Notes"
                            value={form.data.customer_notes}
                            error={form.errors.customer_notes}
                            onChange={(value) =>
                                form.setData('customer_notes', value)
                            }
                        />
                    </FieldGroup>
                </form>
            </div>
            <div className="border-border grid gap-3 border-t px-6 py-4 md:grid-cols-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button
                    type="submit"
                    form="timeline-task-form"
                    disabled={form.processing}
                >
                    {editingExisting ? 'Save Task' : 'Add Task'}
                </Button>
            </div>
        </div>
    );
}

function ConflictPreviewCard({
    conflicts,
}: {
    conflicts: TimelineConflictPayload[];
}) {
    return (
        <Card className="pv-card">
            <CardHeader>
                <CardTitle className="text-2xl">Conflict Preview</CardTitle>
                <CardDescription>
                    Schedule conflicts appear when the current calculated
                    schedule needs attention.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ConflictList conflicts={conflicts} />
            </CardContent>
        </Card>
    );
}

function ConflictList({ conflicts }: { conflicts: TimelineConflictPayload[] }) {
    if (conflicts.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                No schedule conflicts are currently detected.
            </p>
        );
    }

    return (
        <div
            data-conflict-grid="true"
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            {conflicts.map((conflict, index) => (
                <div
                    key={`${conflict.type}-${conflict.task_title}-${index}`}
                    className="border-destructive/40 bg-destructive/10 rounded-lg border p-4"
                >
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="destructive">{conflict.label}</Badge>
                        {conflict.severity ? (
                            <Badge variant="secondary">
                                {humanizeSelectValue(conflict.severity)}
                            </Badge>
                        ) : null}
                    </div>
                    <div className="mt-4 font-semibold">
                        Project conflict:{' '}
                        {conflict.project_conflict ??
                            conflict.conflicting_project_name ??
                            conflict.project_name}
                    </div>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Task: {conflict.task_title} · {conflict.date_range}
                    </p>
                    {conflict.conflict_date ? (
                        <p className="text-muted-foreground mt-1 text-sm">
                            Date: {conflict.conflict_date}
                        </p>
                    ) : null}
                    <p className="text-muted-foreground mt-1 text-sm">
                        Subcontractor:{' '}
                        {conflict.subcontractor_name ?? 'Unassigned'}
                    </p>
                    {conflict.subcontractor_type_name ? (
                        <p className="text-muted-foreground mt-1 text-sm">
                            Type: {conflict.subcontractor_type_name}
                        </p>
                    ) : null}
                    {conflict.reason ? (
                        <p className="mt-3 text-sm">{conflict.reason}</p>
                    ) : null}
                    {conflict.suggested_resolution ? (
                        <p className="text-muted-foreground mt-2 text-sm">
                            Suggested resolution:{' '}
                            {conflict.suggested_resolution}
                        </p>
                    ) : null}
                </div>
            ))}
        </div>
    );
}

function ReadOnlyTimeline({ project, timeline }: TimelineProps) {
    return (
        <ProjectVistaShell
            title="Timeline"
            eyebrow={roleEyebrow(timeline.role)}
            role={project.role}
            project={project}
        >
            <Head title={`${project.name} Timeline`} />
            <div className="flex flex-col gap-6">
                <header className="border-b border-white/10 pb-6">
                    <h1 className="text-4xl font-semibold md:text-5xl">
                        Timeline
                    </h1>
                    <p className="text-muted-foreground mt-2">
                        Current visible schedule for {project.name}.
                    </p>
                </header>
                <Card className="pv-card">
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Visible Timeline Tasks
                        </CardTitle>
                        <CardDescription>
                            Your role only shows approved and relevant schedule
                            details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {timeline.tasks.map((task) => (
                            <div
                                key={task.id}
                                className="border-border rounded-lg border p-4"
                            >
                                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <h2 className="text-xl font-semibold">
                                            {task.title}
                                        </h2>
                                        <p className="text-muted-foreground mt-2 text-sm">
                                            {task.description}
                                        </p>
                                    </div>
                                    <StatusPill
                                        status={task.status}
                                        label={task.status_label}
                                    />
                                </div>
                                <div className="text-muted-foreground mt-4 flex flex-wrap gap-3 text-sm">
                                    <span className="flex items-center gap-2">
                                        <CalendarDays data-icon="inline-start" />
                                        {task.date_range ?? 'TBD'}
                                    </span>
                                    {timeline.role === 'subcontractor' &&
                                    task.assigned_subcontractor_name ? (
                                        <span>
                                            {task.assigned_subcontractor_name}
                                        </span>
                                    ) : null}
                                </div>
                                {timeline.role === 'subcontractor' ? (
                                    <div className="text-muted-foreground mt-3 flex flex-wrap gap-2 text-xs">
                                        <Badge variant="secondary">
                                            Site{' '}
                                            {task.is_job_site_ready
                                                ? 'ready'
                                                : 'not ready'}
                                        </Badge>
                                        <Badge variant="secondary">
                                            Materials{' '}
                                            {task.are_materials_ready
                                                ? 'ready'
                                                : 'pending'}
                                        </Badge>
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </ProjectVistaShell>
    );
}

function Metric({
    label,
    value,
    detail,
    destructive = false,
}: {
    label: string;
    value: number;
    detail: string;
    destructive?: boolean;
}) {
    return (
        <Card className="pv-card">
            <CardContent className="py-5">
                <div className="text-muted-foreground text-sm font-semibold tracking-[0.14em] uppercase">
                    {label}
                </div>
                <div
                    className={cn(
                        'mt-3 text-4xl font-semibold',
                        destructive && value > 0 && 'text-destructive',
                    )}
                >
                    {value}
                </div>
                <div className="text-muted-foreground mt-1 text-sm">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function FilterSelect({
    value,
    onChange,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
}) {
    const selectedLabel =
        options.find((option) => option.value === value)?.label ??
        options[0]?.label ??
        value;

    return (
        <Select value={value} onValueChange={(next) => onChange(String(next))}>
            <SelectTrigger className="h-11 min-w-44 px-4 data-[size=default]:h-11">
                <SelectedSelectLabel>{selectedLabel}</SelectedSelectLabel>
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}

function SelectedSelectLabel({ children }: { children: ReactNode }) {
    return (
        <span
            data-slot="select-value"
            className="flex flex-1 items-center text-left"
        >
            {children}
        </span>
    );
}

function humanizeSelectValue(value: string) {
    return value
        .split('_')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function TextField({
    label,
    value,
    error,
    type = 'text',
    onChange,
}: {
    label: string;
    value: string;
    error?: string;
    type?: string;
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel>{label}</FieldLabel>
            <Input
                type={type}
                value={value}
                aria-invalid={!!error}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function TextAreaField({
    label,
    value,
    error,
    onChange,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel>{label}</FieldLabel>
            <Textarea
                value={value}
                aria-invalid={!!error}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function BooleanField({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <Field orientation="horizontal">
            <Checkbox
                checked={checked}
                onCheckedChange={(nextChecked) =>
                    onChange(nextChecked === true)
                }
            />
            <FieldLabel>{label}</FieldLabel>
        </Field>
    );
}

function taskFormDefaults(
    task: TimelineTaskPayload | null,
    project: ProjectPayload,
    timeline: TimelineWorkspacePayload,
): TimelineTaskForm {
    const projectId =
        task?.project_id?.toString() ??
        timeline.filters.projects[0]?.id?.toString() ??
        project.id.toString();
    const projectTasks = timeline.tasks
        .filter((timelineTask) => timelineTask.project_id?.toString() === projectId)
        .sort(
            (first, second) =>
                (first.sequence_order ?? first.sort_order) -
                (second.sequence_order ?? second.sort_order),
        );

    return {
        project_id: projectId,
        predecessor_task_id: projectTasks[0]?.id.toString() ?? '',
        title: task?.title ?? '',
        phase: task?.phase ?? 'Construction',
        description: task?.description ?? '',
        status: task?.status ?? 'scheduled',
        duration_days: task ? taskDurationInputDays(task).toString() : '1',
        priority: task?.priority?.toString() ?? '2',
        customer_urgency: task?.customer_urgency?.toString() ?? '1',
        is_schedule_locked: task?.is_schedule_locked ?? false,
        schedule_locked_reason: task?.schedule_locked_reason ?? '',
        assigned_subcontractor_id:
            task?.assigned_subcontractor_id?.toString() ?? '',
        subcontractor_type_id: task?.subcontractor_type_id?.toString() ?? '',
        internal_only: task?.internal_only ?? false,
        requires_acknowledgement: task?.requires_acknowledgement ?? false,
        is_job_site_ready: task?.is_job_site_ready ?? true,
        are_materials_ready: task?.are_materials_ready ?? true,
        is_customer_approval_required:
            task?.is_customer_approval_required ?? false,
        is_customer_approval_received:
            task?.is_customer_approval_received ?? false,
        internal_notes: task?.internal_notes ?? '',
        customer_notes: task?.customer_notes ?? '',
        acknowledge_conflicts: false,
    };
}

function durationLabel(task: TimelineTaskPayload) {
    const days = taskDurationInputDays(task);

    return `${days} ${days === 1 ? 'day' : 'days'}`;
}

function taskDurationInputDays(task: TimelineTaskPayload) {
    if (task.default_duration_working_days && task.default_duration_working_days > 0) {
        return task.default_duration_working_days;
    }

    return taskDurationDays(task) + 1;
}

function taskDurationDays(task: TimelineTaskPayload) {
    if (!task.starts_on_input || !task.due_on_input) {
        return 0;
    }

    const start = new Date(`${task.starts_on_input}T00:00:00`);
    const due = new Date(`${task.due_on_input}T00:00:00`);

    return Math.max(
        0,
        Math.round((due.getTime() - start.getTime()) / 86_400_000),
    );
}


function roleEyebrow(role: TimelineWorkspacePayload['role']) {
    if (role === 'company_admin' || role === 'super_admin') {
        return 'Company Admin';
    }

    if (role === 'company_manager') {
        return 'Company Manager';
    }

    if (role === 'subcontractor') {
        return 'Subcontractor';
    }

    return 'Homeowner';
}
