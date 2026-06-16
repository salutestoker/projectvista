import { DataTable } from '@/Components/ProjectVista/DataTable';
import { ProjectCard } from '@/Components/ProjectVista/ProjectCard';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
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
import {
    TableCell,
    TableRow,
} from '@/Components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Textarea } from '@/Components/ui/textarea';
import {
    CompanyPayload,
    ProjectCardPayload,
    ProjectVistaRole,
    TimelineTemplatePayload,
    TimelineTemplateTaskPayload,
} from '@/types/projectvista';
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
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { GripVertical, Plus, Trash2 } from 'lucide-react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';

interface CompanyAdminProps {
    company: CompanyPayload;
    role: ProjectVistaRole;
    permissions: {
        can_manage_users: boolean;
        can_manage_templates: boolean;
    };
    users: {
        id: number;
        name: string;
        email: string;
        role: string;
        title?: string | null;
    }[];
    projects: ProjectCardPayload[];
    invitations: {
        id: number;
        email: string;
        role: string;
        recipient_name?: string | null;
        subcontractor_type?: string | null;
        status: string;
        expires_at?: string | null;
    }[];
    subcontractor_types: {
        id: number;
        name: string;
    }[];
    timeline_templates: TimelineTemplatePayload[];
}

const invitationRoles = [
    { value: 'client', label: 'Client/Homeowner' },
    { value: 'company_manager', label: 'Company Manager' },
    { value: 'subcontractor', label: 'Sub-Contractor' },
];

let templateDraftCounter = 0;

type TimelineTemplateTaskDraft = TimelineTemplateTaskPayload & {
    draft_key: string;
};

type TimelineTemplateDraft = Omit<TimelineTemplatePayload, 'tasks'> & {
    draft_key: string;
    tasks: TimelineTemplateTaskDraft[];
};

export default function CompanyAdmin({
    company,
    role,
    permissions,
    users,
    projects,
    invitations,
    subcontractor_types,
    timeline_templates,
}: CompanyAdminProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'client',
        project_id: projects[0]?.id?.toString() ?? '',
        subcontractor_type_id: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('companies.invitations.store', company.slug), {
            onSuccess: () => reset('email'),
        });
    };
    const selectedRoleLabel =
        invitationRoles.find((role) => role.value === data.role)?.label ??
        'Client/Homeowner';
    const selectedSubcontractorTypeLabel =
        subcontractor_types.find(
            (type) => type.id.toString() === data.subcontractor_type_id,
        )?.name ?? 'Select Sub-Contractor Type';
    const userColumns = useMemo<ColumnDef<CompanyAdminProps['users'][number]>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Name',
                cell: ({ row }) => (
                    <div>
                        <div className="font-medium">{row.original.name}</div>
                        <div className="text-muted-foreground text-xs">
                            {row.original.title}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'role',
                header: 'Role',
                cell: ({ row }) => <StatusPill status={row.original.role} />,
            },
            {
                accessorKey: 'email',
                header: 'Email',
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.email}
                    </span>
                ),
            },
        ],
        [],
    );

    return (
        <ProjectVistaShell
            title="Users & Standards"
            eyebrow={company.name}
            role={role}
            company={company}
        >
            <Head title={`${company.name} Admin`} />
            <div className="flex flex-col gap-6">
                <div
                    className={
                        permissions.can_manage_users
                            ? 'grid gap-6 lg:grid-cols-[1fr_380px]'
                            : 'flex flex-col gap-6'
                    }
                >
                    <section>
                        <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                            <h2 className="text-xl font-semibold">
                                Team Access
                            </h2>
                            <div className="mt-5 overflow-hidden rounded-lg border border-white/10">
                                <DataTable
                                    columns={userColumns}
                                    data={users}
                                    getRowId={(user) => user.id.toString()}
                                />
                            </div>
                        </div>
                    </section>

                    {permissions.can_manage_users ? (
                        <aside className="flex flex-col gap-5">
                            <form
                                onSubmit={submit}
                                className="rounded-lg border border-white/10 bg-white/[0.04] p-5"
                            >
                                <h2 className="text-xl font-semibold">
                                    Create Invitation
                                </h2>
                                <label
                                    className="mt-5 block text-sm text-white/60"
                                    htmlFor="email"
                                >
                                    Email
                                </label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    className="mt-2"
                                />
                                {errors.email && (
                                    <p className="mt-2 text-sm text-rose-300">
                                        {errors.email}
                                    </p>
                                )}

                                <label
                                    className="mt-4 block text-sm text-white/60"
                                    htmlFor="role"
                                >
                                    Role
                                </label>
                                <Select
                                    value={data.role}
                                    onValueChange={(value) => {
                                        const role = String(value);
                                        setData({
                                            ...data,
                                            role,
                                            subcontractor_type_id:
                                                role === 'subcontractor'
                                                    ? data.subcontractor_type_id ||
                                                      subcontractor_types[0]?.id?.toString() ||
                                                      ''
                                                    : '',
                                        });
                                    }}
                                >
                                    <SelectTrigger className="mt-2 w-full data-[size=default]:h-11">
                                        <SelectedSelectLabel>
                                            {selectedRoleLabel}
                                        </SelectedSelectLabel>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {invitationRoles.map((role) => (
                                                <SelectItem
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>

                                {data.role === 'subcontractor' ? (
                                    <>
                                        <label
                                            className="mt-4 block text-sm text-white/60"
                                            htmlFor="subcontractor_type"
                                        >
                                            Sub-Contractor Type
                                        </label>
                                        <Select
                                            value={data.subcontractor_type_id}
                                            onValueChange={(value) =>
                                                setData(
                                                    'subcontractor_type_id',
                                                    String(value),
                                                )
                                            }
                                        >
                                            <SelectTrigger className="mt-2 w-full data-[size=default]:h-11">
                                                <SelectedSelectLabel>
                                                    {
                                                        selectedSubcontractorTypeLabel
                                                    }
                                                </SelectedSelectLabel>
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {subcontractor_types.map(
                                                        (type) => (
                                                            <SelectItem
                                                                key={type.id}
                                                                value={type.id.toString()}
                                                            >
                                                                {type.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        {errors.subcontractor_type_id && (
                                            <p className="mt-2 text-sm text-rose-300">
                                                {errors.subcontractor_type_id}
                                            </p>
                                        )}
                                    </>
                                ) : null}

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="mt-5 w-full"
                                >
                                    {processing
                                        ? 'Creating...'
                                        : 'Create invite'}
                                </Button>
                            </form>

                            <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                                <h2 className="text-xl font-semibold">
                                    Pending Invitations
                                </h2>
                                <div className="mt-4 space-y-3">
                                    {invitations.map((invitation) => (
                                        <div
                                            key={invitation.id}
                                            className="rounded-md bg-black/25 p-3"
                                        >
                                            <div className="font-medium">
                                                {invitation.email}
                                            </div>
                                            <div className="mt-1 text-xs text-white/50">
                                                {invitation.role}
                                                {invitation.subcontractor_type
                                                    ? ` · ${invitation.subcontractor_type}`
                                                    : ''}{' '}
                                                · expires{' '}
                                                {invitation.expires_at}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </aside>
                    ) : null}
                </div>

                <TimelineTemplateCard
                    company={company}
                    templates={timeline_templates}
                    subcontractorTypes={subcontractor_types}
                    canManage={permissions.can_manage_templates}
                />

                <section>
                    <h2 className="mb-4 text-xl font-semibold">
                        Company Projects
                    </h2>
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <ProjectCard key={project.id} project={project} />
                        ))}
                    </div>
                </section>
            </div>
        </ProjectVistaShell>
    );
}

function TimelineTemplateCard({
    company,
    templates,
    subcontractorTypes,
    canManage,
}: {
    company: CompanyPayload;
    templates: TimelineTemplatePayload[];
    subcontractorTypes: CompanyAdminProps['subcontractor_types'];
    canManage: boolean;
}) {
    const initialDrafts = useMemo(
        () => templates.map((template) => toTemplateDraft(template)),
        [templates],
    );
    const [drafts, setDrafts] =
        useState<TimelineTemplateDraft[]>(initialDrafts);
    const [activeKey, setActiveKey] = useState(
        initialDrafts[0]?.draft_key ?? '',
    );
    const {
        data,
        setData,
        post,
        patch,
        processing,
        errors,
        clearErrors,
        recentlySuccessful,
    } = useForm<TimelineTemplateDraft>(
        initialDrafts[0] ?? emptyTemplateDraft(),
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

    const updateDraft = (nextDraft: TimelineTemplateDraft) => {
        setData(nextDraft);
        setDrafts((currentDrafts) =>
            currentDrafts.map((draft) =>
                draft.draft_key === nextDraft.draft_key ? nextDraft : draft,
            ),
        );
    };

    const updateTask = (
        taskKey: string,
        changes: Partial<TimelineTemplateTaskDraft>,
    ) => {
        const existingTask = data.tasks.find(
            (task) => task.draft_key === taskKey,
        );

        if (existingTask?.is_system) {
            return;
        }

        updateDraft({
            ...data,
            tasks: data.tasks.map((task) =>
                task.draft_key === taskKey ? { ...task, ...changes } : task,
            ),
        });
    };

    const addTask = () => {
        updateDraft({
            ...data,
            tasks: [...data.tasks, emptyTaskDraft(data.tasks.length + 1)].map(
                (task, index) => ({
                    ...task,
                    sequence_order: index + 1,
                }),
            ),
        });
    };

    const deleteTask = (taskKey: string) => {
        const existingTask = data.tasks.find(
            (task) => task.draft_key === taskKey,
        );

        if (existingTask?.is_system) {
            return;
        }

        if (data.tasks.length === 1) {
            return;
        }

        updateDraft({
            ...data,
            tasks: data.tasks
                .filter((task) => task.draft_key !== taskKey)
                .map((task, index) => ({
                    ...task,
                    sequence_order: index + 1,
                })),
        });
    };

    const addTemplate = () => {
        const source = data.tasks.length > 0 ? data : emptyTemplateDraft();
        const nextDraft: TimelineTemplateDraft = {
            ...source,
            id: null,
            draft_key: nextDraftKey('template'),
            name: `${source.name || 'Timeline Template'} Copy`,
            is_default: false,
            tasks: source.tasks.map((task, index) => ({
                ...task,
                id: null,
                draft_key: nextDraftKey('task'),
                sequence_order: index + 1,
                is_system: task.is_system ?? (index === 0),
            })),
        };

        setDrafts((currentDrafts) => [...currentDrafts, nextDraft]);
        setActiveKey(nextDraft.draft_key);
        setData(nextDraft);
        clearErrors();
    };

    const changeActiveTemplate = (nextKey: string) => {
        const nextDraft = drafts.find((draft) => draft.draft_key === nextKey);

        if (!nextDraft) {
            return;
        }

        setActiveKey(nextKey);
        setData(nextDraft);
        clearErrors();
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const oldIndex = data.tasks.findIndex(
            (task) => task.draft_key === active.id,
        );
        const newIndex = data.tasks.findIndex(
            (task) => task.draft_key === over.id,
        );

        if (oldIndex === -1 || newIndex === -1) {
            return;
        }

        if (data.tasks[oldIndex]?.is_system || newIndex === 0) {
            return;
        }

        updateDraft({
            ...data,
            tasks: arrayMove(data.tasks, oldIndex, newIndex).map(
                (task, index) => ({
                    ...task,
                    sequence_order: index + 1,
                }),
            ),
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            preserveState: false,
        };

        if (data.id === null) {
            post(
                route('companies.timeline-templates.store', company.slug),
                options,
            );

            return;
        }

        patch(
            route('companies.timeline-templates.update', [
                company.slug,
                data.id,
            ]),
            options,
        );
    };

    const templateTaskColumns = useMemo<
        ColumnDef<TimelineTemplateTaskDraft>[]
    >(
        () => [
            { id: 'order', header: 'Order' },
            { accessorKey: 'name', header: 'Task' },
            {
                accessorKey: 'default_duration_working_days',
                header: 'Duration (Days)',
            },
            {
                accessorKey: 'default_subcontractor_type',
                header: 'Trade Type',
            },
            { accessorKey: 'internal_only', header: 'Internal Only' },
            { accessorKey: 'description', header: 'Notes' },
            ...(canManage
                ? ([
                      {
                          id: 'actions',
                          header: () => (
                              <span className="sr-only">Actions</span>
                          ),
                      },
                  ] satisfies ColumnDef<TimelineTemplateTaskDraft>[])
                : []),
        ],
        [canManage],
    );

    if (drafts.length === 0) {
        return (
            <Card className="pv-card">
                <CardHeader>
                    <CardTitle className="text-2xl">
                        Template Timeline
                    </CardTitle>
                    <CardDescription>
                        No default timeline template is configured yet.
                    </CardDescription>
                </CardHeader>
                {canManage ? (
                    <CardFooter>
                        <Button type="button" onClick={addTemplate}>
                            <Plus data-icon="inline-start" />
                            New Template
                        </Button>
                    </CardFooter>
                ) : null}
            </Card>
        );
    }

    return (
        <Card className="pv-card">
            <CardHeader className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <CardTitle className="text-2xl">
                        Timeline Templates
                    </CardTitle>
                    <CardDescription>
                        Create, edit, and reorder reusable project schedule
                        templates.
                    </CardDescription>
                </div>
                {canManage ? (
                    <Button type="button" onClick={addTemplate}>
                        <Plus data-icon="inline-start" />
                        New Template
                    </Button>
                ) : null}
            </CardHeader>
            <form onSubmit={submit}>
                <CardContent>
                    <Tabs
                        value={activeKey}
                        onValueChange={changeActiveTemplate}
                    >
                        <TabsList className="max-w-full flex-wrap justify-start">
                            {drafts.map((draft) => (
                                <TabsTrigger
                                    key={draft.draft_key}
                                    value={draft.draft_key}
                                >
                                    {draft.name}
                                    {draft.is_default ? (
                                        <Badge variant="secondary">
                                            Default
                                        </Badge>
                                    ) : null}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                        <TabsContent value={activeKey} className="pt-5">
                            <div className="flex flex-col gap-5 pb-6">
                                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_220px]">
                                    <FieldGroup>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <TemplateTextField
                                                label="Template Name"
                                                value={data.name}
                                                error={errors.name}
                                                disabled={!canManage}
                                                onChange={(value) =>
                                                    updateDraft({
                                                        ...data,
                                                        name: value,
                                                    })
                                                }
                                            />
                                            <TemplateTextField
                                                label="Description"
                                                value={data.description ?? ''}
                                                error={errors.description}
                                                disabled={!canManage}
                                                onChange={(value) =>
                                                    updateDraft({
                                                        ...data,
                                                        description: value,
                                                    })
                                                }
                                            />
                                        </div>
                                    </FieldGroup>
                                    <Field orientation="horizontal">
                                        <Checkbox
                                            id="is_default"
                                            checked={data.is_default}
                                            disabled={!canManage}
                                            onCheckedChange={(checked) =>
                                                updateDraft({
                                                    ...data,
                                                    is_default:
                                                        checked === true,
                                                })
                                            }
                                        />
                                        <FieldLabel
                                            htmlFor="is_default"
                                            className="font-normal"
                                        >
                                            Default template
                                        </FieldLabel>
                                    </Field>
                                </div>

                                <div className="rounded-lg border border-white/10">
                                    <DndContext
                                        sensors={sensors}
                                        collisionDetection={closestCenter}
                                        onDragEnd={handleDragEnd}
                                    >
                                        <SortableContext
                                            items={data.tasks.map(
                                                (task) => task.draft_key,
                                            )}
                                            strategy={
                                                verticalListSortingStrategy
                                            }
                                        >
                                            <DataTable
                                                columns={templateTaskColumns}
                                                data={data.tasks}
                                                getRowId={(task) =>
                                                    task.draft_key
                                                }
                                                renderRow={(row, index) => (
                                                    <SortableTemplateTaskRow
                                                        key={row.original.draft_key}
                                                        task={row.original}
                                                        index={index}
                                                        canManage={canManage}
                                                        errors={errors}
                                                        subcontractorTypes={
                                                            subcontractorTypes
                                                        }
                                                        onChange={(changes) =>
                                                            updateTask(
                                                                row.original
                                                                    .draft_key,
                                                                changes,
                                                            )
                                                        }
                                                        onDelete={() =>
                                                            deleteTask(
                                                                row.original
                                                                    .draft_key,
                                                            )
                                                        }
                                                    />
                                                )}
                                            />
                                        </SortableContext>
                                    </DndContext>
                                </div>

                                {canManage ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="self-start"
                                        onClick={addTask}
                                    >
                                        <Plus data-icon="inline-start" />
                                        Add Row
                                    </Button>
                                ) : null}
                            </div>
                        </TabsContent>
                    </Tabs>
                </CardContent>
                {canManage ? (
                    <CardFooter className="justify-between">
                        <div className="text-muted-foreground text-sm">
                            {recentlySuccessful
                                ? 'Template saved.'
                                : 'Template changes apply to future projects.'}
                        </div>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : 'Save Template'}
                        </Button>
                    </CardFooter>
                ) : null}
            </form>
        </Card>
    );
}

function SortableTemplateTaskRow({
    task,
    index,
    canManage,
    errors,
    subcontractorTypes,
    onChange,
    onDelete,
}: {
    task: TimelineTemplateTaskDraft;
    index: number;
    canManage: boolean;
    errors: Record<string, string | undefined>;
    subcontractorTypes: CompanyAdminProps['subcontractor_types'];
    onChange: (changes: Partial<TimelineTemplateTaskDraft>) => void;
    onDelete: () => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition } =
        useSortable({
            id: task.draft_key,
            disabled: !canManage || task.is_system,
        });
    const locked = task.is_system === true;
    const fieldPrefix = `tasks.${index}`;
    const selectedSubcontractorTypeLabel =
        subcontractorTypes.find(
            (type) => type.id === task.default_subcontractor_type_id,
        )?.name ?? 'Unassigned';

    return (
        <TableRow
            ref={setNodeRef}
            className={index % 2 === 0 ? 'bg-muted/20' : 'bg-background/40'}
            style={{ transform: CSS.Transform.toString(transform), transition }}
        >
            <TableCell>
                <div className="flex items-center gap-2">
                    {canManage && !locked ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            {...attributes}
                            {...listeners}
                        >
                            <GripVertical />
                            <span className="sr-only">Reorder task</span>
                        </Button>
                    ) : null}
                    <span className="text-muted-foreground">
                        {task.sequence_order}
                    </span>
                </div>
            </TableCell>
            <TableCell className="min-w-72">
                <InlineInput
                    label="Task name"
                    value={task.name}
                    error={errors[`${fieldPrefix}.name`]}
                    disabled={!canManage || locked}
                    onChange={(value) => onChange({ name: value })}
                />
            </TableCell>
            <TableCell className="min-w-28">
                <InlineInput
                    label="Working days"
                    type="number"
                    value={task.default_duration_working_days.toString()}
                    error={
                        errors[`${fieldPrefix}.default_duration_working_days`]
                    }
                    disabled={!canManage || locked}
                    onChange={(value) =>
                        onChange({
                            default_duration_working_days:
                                Number.parseInt(value, 10) || 1,
                        })
                    }
                />
            </TableCell>
            <TableCell className="min-w-48">
                <Select
                    value={
                        task.default_subcontractor_type_id?.toString() ??
                        'unassigned'
                    }
                    onValueChange={(value) =>
                        onChange({
                            default_subcontractor_type_id:
                                value === 'unassigned'
                                    ? null
                                    : Number.parseInt(String(value), 10),
                        })
                    }
                >
                    <SelectTrigger
                        disabled={!canManage || locked}
                        className="w-full data-[size=default]:h-9"
                    >
                        <SelectedSelectLabel>
                            {selectedSubcontractorTypeLabel}
                        </SelectedSelectLabel>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="unassigned">
                                Unassigned
                            </SelectItem>
                            {subcontractorTypes.map((type) => (
                                <SelectItem
                                    key={type.id}
                                    value={type.id.toString()}
                                >
                                    {type.name}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </TableCell>
            <TableCell className="min-w-36">
                <InlineCheckbox
                    label="Internal Only"
                    checked={task.internal_only}
                    disabled={!canManage || locked}
                    onChange={(checked) => onChange({ internal_only: checked })}
                />
            </TableCell>
            <TableCell className="min-w-72">
                <Field data-invalid={!!errors[`${fieldPrefix}.description`]}>
                    <FieldLabel className="sr-only">Description</FieldLabel>
                    <Textarea
                        value={task.description ?? ''}
                        rows={3}
                        disabled={!canManage || locked}
                        aria-invalid={!!errors[`${fieldPrefix}.description`]}
                        onChange={(event) =>
                            onChange({ description: event.target.value })
                        }
                    />
                    <FieldError>
                        {errors[`${fieldPrefix}.description`]}
                    </FieldError>
                </Field>
            </TableCell>
            {canManage ? (
                <TableCell>
                    {!locked ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={onDelete}
                        >
                            <Trash2 />
                            <span className="sr-only">Delete row</span>
                        </Button>
                    ) : null}
                </TableCell>
            ) : null}
        </TableRow>
    );
}

function TemplateTextField({
    label,
    value,
    error,
    disabled,
    onChange,
}: {
    label: string;
    value: string;
    error?: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel>{label}</FieldLabel>
            <Input
                value={value}
                disabled={disabled}
                aria-invalid={!!error}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function InlineInput({
    label,
    value,
    error,
    type = 'text',
    disabled,
    onChange,
}: {
    label: string;
    value: string;
    error?: string;
    type?: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel className="sr-only">{label}</FieldLabel>
            <Input
                type={type}
                value={value}
                disabled={disabled}
                aria-invalid={!!error}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function InlineCheckbox({
    label,
    checked,
    disabled,
    onChange,
}: {
    label: string;
    checked: boolean;
    disabled: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <Field orientation="horizontal">
            <Checkbox
                checked={checked}
                disabled={disabled}
                onCheckedChange={(value) => onChange(value === true)}
            />
            <FieldLabel className="font-normal">{label}</FieldLabel>
        </Field>
    );
}

function toTemplateDraft(
    template: TimelineTemplatePayload,
): TimelineTemplateDraft {
    return {
        ...template,
        draft_key: `template-${template.id ?? nextDraftKey('template')}`,
        tasks: template.tasks.map((task, index) => ({
            ...task,
            draft_key: `task-${task.id ?? nextDraftKey('task')}`,
            is_system:
                task.is_system ??
                (task.name.toLowerCase() === 'contract signed' ||
                    index === 0),
        })),
    };
}

function emptyTemplateDraft(): TimelineTemplateDraft {
    return {
        id: null,
        draft_key: nextDraftKey('template'),
        name: 'New Timeline Template',
        description: '',
        is_default: false,
        tasks: [contractSignedTaskDraft()],
    };
}

function contractSignedTaskDraft(): TimelineTemplateTaskDraft {
    return {
        id: null,
        draft_key: nextDraftKey('task'),
        name: 'Contract Signed',
        description: 'Project contract milestone tied to the project contract signed date.',
        sequence_order: 1,
        default_duration_working_days: 1,
        default_subcontractor_type_id: null,
        default_subcontractor_type: null,
        internal_only: false,
        is_system: true,
    };
}

function emptyTaskDraft(sequenceOrder: number): TimelineTemplateTaskDraft {
    return {
        id: null,
        draft_key: nextDraftKey('task'),
        name: 'New Task',
        description: '',
        sequence_order: sequenceOrder,
        default_duration_working_days: 1,
        default_subcontractor_type_id: null,
        default_subcontractor_type: null,
        internal_only: false,
        is_system: false,
    };
}

function nextDraftKey(prefix: string): string {
    templateDraftCounter += 1;

    return `${prefix}-${Date.now()}-${templateDraftCounter}`;
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
