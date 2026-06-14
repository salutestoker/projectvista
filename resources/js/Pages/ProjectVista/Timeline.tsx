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
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Progress } from '@/Components/ui/progress';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    ProjectPayload,
    TimelineConflictPayload,
    TimelineTaskPayload,
    TimelineWorkspacePayload,
} from '@/types/projectvista';
import { cn } from '@/lib/utils';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, GripVertical, Plus } from 'lucide-react';
import { FormEvent, MouseEvent, ReactNode, useMemo, useState } from 'react';

interface TimelineProps {
    project: ProjectPayload;
    timeline: TimelineWorkspacePayload;
}

type TimelineTaskForm = {
    project_id: string;
    title: string;
    phase: string;
    description: string;
    status: string;
    starts_on: string;
    due_on: string;
    assigned_subcontractor_id: string;
    subcontractor_type_id: string;
    client_visible: boolean;
    subcontractor_visible: boolean;
    requires_acknowledgement: boolean;
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

    const [tasks, setTasks] = useState(timeline.tasks);
    const [selectedTask, setSelectedTask] =
        useState<TimelineTaskPayload | null>(initialSelectedTask);
    const [localConflicts, setLocalConflicts] =
        useState<TimelineConflictPayload[]>(flashConflicts);
    const [scopeFilter, setScopeFilter] = useState('open');
    const [projectFilter, setProjectFilter] = useState('all');
    const [typeFilter, setTypeFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');

    const form = useForm<TimelineTaskForm>(
        taskFormDefaults(selectedTask, project, timeline),
    );

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8,
            },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const filteredTasks = useMemo(
        () =>
            tasks.filter((task) => {
                const openMatch =
                    scopeFilter !== 'open' || task.status !== 'completed';
                const projectMatch =
                    projectFilter === 'all' ||
                    String(task.project_id) === projectFilter;
                const typeMatch =
                    typeFilter === 'all' ||
                    String(task.subcontractor_type_id ?? '') === typeFilter;
                const statusMatch =
                    statusFilter === 'all' || task.status === statusFilter;

                return openMatch && projectMatch && typeMatch && statusMatch;
            }),
        [projectFilter, scopeFilter, statusFilter, tasks, typeFilter],
    );

    const ids = useMemo(
        () => filteredTasks.map((task) => task.id),
        [filteredTasks],
    );

    const visibleConflicts = localConflicts.length
        ? localConflicts
        : timeline.conflicts;

    const selectTask = (task: TimelineTaskPayload) => {
        setSelectedTask(task);
        setLocalConflicts([]);
        form.setData(taskFormDefaults(task, project, timeline));
        form.clearErrors();
    };

    const startNewTask = () => {
        setSelectedTask(null);
        setLocalConflicts([]);
        form.setData(taskFormDefaults(null, project, timeline));
        form.clearErrors();
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const dragged = tasks.find((task) => task.id === active.id);
        const target = tasks.find((task) => task.id === over.id);

        if (!dragged || !target || !target.starts_on_input) {
            return;
        }

        const duration = taskDurationDays(dragged);
        const nextTask = {
            ...dragged,
            starts_on_input: target.starts_on_input,
            due_on_input: addDays(target.starts_on_input, duration),
            date_range: formatInputRange(
                target.starts_on_input,
                addDays(target.starts_on_input, duration),
            ),
        };
        const nextTasks = tasks.map((task) =>
            task.id === dragged.id ? nextTask : task,
        );

        setTasks(nextTasks);
        setSelectedTask(nextTask);
        form.setData(taskFormDefaults(nextTask, project, timeline));
        setLocalConflicts(detectConflicts(nextTask, nextTasks));
    };

    const submitTask = (
        event: FormEvent | MouseEvent<HTMLButtonElement>,
        acknowledgeConflicts = false,
    ) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            acknowledge_conflicts: acknowledgeConflicts,
        }));

        if (selectedTask) {
            form.patch(
                route('projects.timeline.tasks.update', [
                    project.slug,
                    selectedTask.id,
                ]),
                { preserveScroll: true },
            );

            return;
        }

        form.post(route('projects.timeline.tasks.store', project.slug), {
            preserveScroll: true,
        });
    };

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
                            Open timeline tasks across projects. Drag-and-drop
                            schedule changes trigger conflict detection.
                        </p>
                    </div>
                    <Button type="button">Save Template</Button>
                </header>

                <section className="grid gap-4 md:grid-cols-4">
                    <Metric label="Open Tasks" value={timeline.metrics.open_tasks} detail="Complete tasks hidden" />
                    <Metric label="Conflicts" value={visibleConflicts.length} detail="After reschedule" destructive />
                    <Metric label="Due This Week" value={timeline.metrics.due_this_week} detail="Open tasks" />
                    <Metric label="Sub Types" value={timeline.metrics.sub_types} detail="Trade categories" />
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_520px]">
                    <div className="flex flex-col gap-5">
                        <Card className="pv-card">
                            <CardContent className="flex flex-wrap items-center gap-3 py-4">
                                <div className="mr-2 text-xl font-semibold">
                                    Filters
                                </div>
                                <FilterSelect
                                    value={scopeFilter}
                                    onChange={setScopeFilter}
                                    options={[
                                        { value: 'open', label: 'All Open Tasks' },
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
                                    value={typeFilter}
                                    onChange={setTypeFilter}
                                    options={[
                                        { value: 'all', label: 'All Sub Types' },
                                        ...timeline.filters.subcontractor_types.map(
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
                                        Open Timeline Tasks
                                    </CardTitle>
                                    <CardDescription>
                                        Completed tasks are hidden by default.
                                    </CardDescription>
                                </div>
                                <div className="text-primary text-sm font-semibold">
                                    Drag tasks to reschedule
                                </div>
                            </CardHeader>
                            <CardContent>
                                <DndContext
                                    sensors={sensors}
                                    collisionDetection={closestCenter}
                                    onDragEnd={handleDragEnd}
                                >
                                    <SortableContext
                                        items={ids}
                                        strategy={verticalListSortingStrategy}
                                    >
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Project</TableHead>
                                                    <TableHead>Task</TableHead>
                                                    <TableHead>Status</TableHead>
                                                    <TableHead>Sub-Contractor</TableHead>
                                                    <TableHead>Type</TableHead>
                                                    <TableHead>Date</TableHead>
                                                    <TableHead>Progress</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {filteredTasks.map((task) => (
                                                    <SortableTaskRow
                                                        key={task.id}
                                                        task={task}
                                                        selected={
                                                            selectedTask?.id ===
                                                            task.id
                                                        }
                                                        onSelect={() =>
                                                            selectTask(task)
                                                        }
                                                    />
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </SortableContext>
                                </DndContext>
                            </CardContent>
                            <CardFooter className="text-muted-foreground text-sm">
                                {selectedTask
                                    ? `Selected task: ${selectedTask.project_name} · ${selectedTask.title}`
                                    : 'Open tasks only · completed tasks are hidden unless the filter is changed.'}
                            </CardFooter>
                        </Card>
                    </div>

                    <TimelineSidePanel
                        timeline={timeline}
                        selectedTask={selectedTask}
                        form={form}
                        conflicts={visibleConflicts}
                        onSubmit={submitTask}
                        onCancel={() => {
                            setSelectedTask(initialSelectedTask);
                            setLocalConflicts([]);
                            form.setData(
                                taskFormDefaults(
                                    initialSelectedTask,
                                    project,
                                    timeline,
                                ),
                            );
                        }}
                    />
                </section>

                <ConflictPreviewCard conflicts={visibleConflicts} />
            </div>
        </ProjectVistaShell>
    );
}

function SortableTaskRow({
    task,
    selected,
    onSelect,
}: {
    task: TimelineTaskPayload;
    selected: boolean;
    onSelect: () => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition } =
        useSortable({ id: task.id });

    return (
        <TableRow
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'cursor-grab touch-none select-none active:cursor-grabbing',
                selected && 'bg-primary/15 outline outline-primary/40',
            )}
            {...attributes}
            {...listeners}
            onClick={onSelect}
        >
            <TableCell className="font-semibold">
                <div className="flex items-center gap-2">
                    <GripVertical
                        data-icon="inline-start"
                        className="text-muted-foreground"
                    />
                    <span>{task.project_name}</span>
                </div>
            </TableCell>
            <TableCell className="font-semibold">{task.title}</TableCell>
            <TableCell>
                <StatusPill status={task.status} />
            </TableCell>
            <TableCell>{task.assigned_subcontractor_name ?? 'Unassigned'}</TableCell>
            <TableCell>{task.subcontractor_type_name ?? 'Unassigned'}</TableCell>
            <TableCell>{task.date_range ?? 'TBD'}</TableCell>
            <TableCell>
                <Progress value={task.progress ?? 0} className="min-w-24" />
            </TableCell>
        </TableRow>
    );
}

function TimelineSidePanel({
    timeline,
    selectedTask,
    form,
    conflicts,
    onSubmit,
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
            : timeline.subcontractors.find(
                  (option) =>
                      option.id.toString() ===
                      form.data.assigned_subcontractor_id,
              )?.name ?? 'Unassigned';
    const selectedSubcontractorTypeLabel =
        form.data.subcontractor_type_id === ''
            ? 'Unassigned'
            : timeline.filters.subcontractor_types.find(
                  (option) =>
                      option.id.toString() === form.data.subcontractor_type_id,
              )?.name ?? 'Unassigned';

    return (
        <Card className="pv-card">
            <CardHeader>
                <CardTitle className="text-2xl">
                    {editingExisting ? 'Edit Timeline Task' : 'Add Timeline Task'}
                </CardTitle>
                <CardDescription>
                    {editingExisting
                        ? `${selectedTask.project_name} · ${selectedTask.title}`
                        : 'Create a new open task for a project.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                {conflicts.length > 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>
                            {conflicts.length} Conflict
                            {conflicts.length === 1 ? '' : 's'} Detected
                        </AlertTitle>
                        <AlertDescription>
                            Review before saving schedule changes.
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
                                    onValueChange={(value) =>
                                        form.setData('project_id', String(value))
                                    }
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
                                <FieldError>{form.errors.project_id}</FieldError>
                            </Field>
                        ) : null}

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
                                        subcontractor_visible:
                                            nextValue !== '',
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
                                        {timeline.subcontractors.map((option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={option.id.toString()}
                                            >
                                                {option.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError>
                                {form.errors.assigned_subcontractor_id}
                            </FieldError>
                        </Field>

                        <Field data-invalid={!!form.errors.subcontractor_type_id}>
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

                        <div className="grid gap-4 md:grid-cols-2">
                            <TextField
                                label="Start Date"
                                type="date"
                                value={form.data.starts_on}
                                error={form.errors.starts_on}
                                onChange={(value) =>
                                    form.setData('starts_on', value)
                                }
                            />
                            <TextField
                                label="Due Date"
                                type="date"
                                value={form.data.due_on}
                                error={form.errors.due_on}
                                onChange={(value) =>
                                    form.setData('due_on', value)
                                }
                            />
                        </div>
                        <FieldDescription>
                            Saving re-checks conflicts before updating the
                            timeline and notifying assigned subcontractors.
                        </FieldDescription>
                    </FieldGroup>
                </form>
            </CardContent>
            <CardFooter className="grid gap-3 md:grid-cols-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                {conflicts.length > 0 ? (
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={(event) => onSubmit(event, true)}
                    >
                        Save With Reschedule Review
                    </Button>
                ) : (
                    <Button
                        type="submit"
                        form="timeline-task-form"
                        disabled={form.processing}
                    >
                        {editingExisting ? 'Save Task' : 'Add Task'}
                    </Button>
                )}
            </CardFooter>
        </Card>
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
                    Schedule conflicts appear after drag-and-drop rescheduling
                    or failed conflict checks.
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
                    className="border-destructive/40 rounded-lg border bg-destructive/10 p-4"
                >
                    <Badge variant="destructive">{conflict.label}</Badge>
                    <div className="mt-4 font-semibold">
                        Project conflict: {conflict.project_name}
                    </div>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Also: {conflict.conflicting_project_name} ·{' '}
                        {conflict.date_range}
                    </p>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Subcontractor:{' '}
                        {conflict.subcontractor_name ?? 'Unassigned'}
                    </p>
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
                                        <div className="text-primary text-xs font-semibold tracking-[0.2em] uppercase">
                                            {task.phase}
                                        </div>
                                        <h2 className="mt-1 text-xl font-semibold">
                                            {task.title}
                                        </h2>
                                        <p className="text-muted-foreground mt-2 text-sm">
                                            {task.description}
                                        </p>
                                    </div>
                                    <StatusPill status={task.status} />
                                </div>
                                <div className="text-muted-foreground mt-4 flex flex-wrap gap-3 text-sm">
                                    <span className="flex items-center gap-2">
                                        <CalendarDays data-icon="inline-start" />
                                        {task.date_range ?? 'TBD'}
                                    </span>
                                    {task.assigned_subcontractor_name ? (
                                        <span>
                                            {task.assigned_subcontractor_name}
                                        </span>
                                    ) : null}
                                </div>
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

function taskFormDefaults(
    task: TimelineTaskPayload | null,
    project: ProjectPayload,
    timeline: TimelineWorkspacePayload,
): TimelineTaskForm {
    return {
        project_id:
            task?.project_id?.toString() ??
            timeline.filters.projects[0]?.id?.toString() ??
            project.id.toString(),
        title: task?.title ?? '',
        phase: task?.phase ?? project.phase,
        description: task?.description ?? '',
        status: task?.status ?? 'upcoming',
        starts_on: task?.starts_on_input ?? '',
        due_on: task?.due_on_input ?? '',
        assigned_subcontractor_id:
            task?.assigned_subcontractor_id?.toString() ?? '',
        subcontractor_type_id: task?.subcontractor_type_id?.toString() ?? '',
        client_visible: task?.client_visible ?? true,
        subcontractor_visible: task?.subcontractor_visible ?? false,
        requires_acknowledgement: task?.requires_acknowledgement ?? false,
        acknowledge_conflicts: false,
    };
}

function detectConflicts(
    candidate: TimelineTaskPayload,
    tasks: TimelineTaskPayload[],
): TimelineConflictPayload[] {
    if (
        !candidate.starts_on_input ||
        !candidate.due_on_input ||
        !candidate.assigned_subcontractor_id
    ) {
        return [];
    }

    const candidateStart = candidate.starts_on_input;
    const candidateDue = candidate.due_on_input;

    return tasks
        .filter(
            (task) =>
                task.id !== candidate.id &&
                task.status !== 'completed' &&
                task.starts_on_input &&
                task.due_on_input &&
                overlaps(
                    candidateStart,
                    candidateDue,
                    task.starts_on_input,
                    task.due_on_input,
                ) &&
                (task.assigned_subcontractor_id ===
                    candidate.assigned_subcontractor_id ||
                    (task.project_id === candidate.project_id &&
                        task.assigned_subcontractor_id &&
                        task.assigned_subcontractor_id !==
                            candidate.assigned_subcontractor_id)),
        )
        .map((task) => {
            const doubleBooked =
                task.assigned_subcontractor_id ===
                candidate.assigned_subcontractor_id;

            return {
                type: doubleBooked
                    ? 'subcontractor_double_booked'
                    : 'same_day_project_conflict',
                label: doubleBooked
                    ? 'Subcontractor Double-Booked'
                    : 'Same-Day Project Conflict',
                project_name: candidate.project_name ?? 'Project',
                conflicting_project_name: task.project_name,
                task_title: task.title,
                date_range: task.date_range ?? 'TBD',
                subcontractor_name:
                    task.assigned_subcontractor_name ??
                    candidate.assigned_subcontractor_name,
            };
        });
}

function overlaps(startA: string, endA: string, startB: string, endB: string) {
    return startA <= endB && endA >= startB;
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

function addDays(date: string, days: number) {
    const next = new Date(`${date}T00:00:00`);
    next.setDate(next.getDate() + days);

    return next.toISOString().slice(0, 10);
}

function formatInputRange(start: string, due: string) {
    const formatter = new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
    });

    return `${formatter.format(new Date(`${start}T00:00:00`))} – ${formatter.format(new Date(`${due}T00:00:00`))}`;
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
