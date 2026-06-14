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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { ProjectPayload } from '@/types/projectvista';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Download,
    FileText,
    ImagePlus,
    MoreHorizontal,
    UploadCloud,
} from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

interface ProjectDetailProps {
    project: ProjectPayload;
}

type ProjectFormData = {
    customer_name: string;
    address_line: string;
    city: string;
    state: string;
    postal_code: string;
    contract_amount: string;
    starts_on: string;
    estimated_completion_on: string;
    project_type: string;
    status: string;
    phase: string;
};

export default function ProjectDetail({ project }: ProjectDetailProps) {
    const canUpdate = project.permissions.can_update_project;
    const projectForm = useForm<ProjectFormData>(projectFormDefaults(project));

    const saveProject = (event: FormEvent) => {
        event.preventDefault();
        projectForm.patch(route('projects.update', project.slug), {
            preserveScroll: true,
        });
    };

    const resetProjectForm = () => {
        projectForm.setData(projectFormDefaults(project));
        projectForm.clearErrors();
    };

    return (
        <ProjectVistaShell
            title={project.name}
            eyebrow={roleEyebrow(project)}
            role={project.role}
            project={project}
        >
            <Head title={project.name} />
            <div className="flex flex-col gap-6">
                <ProjectDetailHeader
                    project={project}
                    canUpdate={canUpdate}
                    processing={projectForm.processing}
                    onCancel={resetProjectForm}
                />

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_540px]">
                    <div className="flex flex-col gap-6">
                        <ProjectInformationCard
                            project={project}
                            form={projectForm}
                            canUpdate={canUpdate}
                            onSubmit={saveProject}
                        />

                        {project.permissions.can_manage_subcontractors ? (
                            <SubcontractorAssignmentCard project={project} />
                        ) : (
                            <RoleContextCard project={project} />
                        )}
                    </div>

                    <aside className="flex flex-col gap-6">
                        <ProjectDocumentsCard project={project} />
                        <ProjectPhotosCard project={project} />
                        <ProjectSummaryCard project={project} />
                    </aside>
                </section>
            </div>
        </ProjectVistaShell>
    );
}

function ProjectDetailHeader({
    project,
    canUpdate,
    processing,
    onCancel,
}: {
    project: ProjectPayload;
    canUpdate: boolean;
    processing: boolean;
    onCancel: () => void;
}) {
    return (
        <header className="flex flex-col gap-5 border-b border-white/10 pb-6 lg:flex-row lg:items-start lg:justify-between">
            <div className="flex flex-col gap-3">
                <div className="text-primary flex items-center gap-2 text-sm font-semibold tracking-[0.18em] uppercase">
                    <Link href={route('projects.index')}>Projects</Link>
                    <span>/</span>
                    <span>{project.name}</span>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-4xl font-semibold md:text-5xl">
                        {project.name}
                    </h1>
                    <StatusPill status={project.status} />
                </div>
                <div className="text-muted-foreground flex flex-wrap gap-4 text-sm">
                    <span>Project ID: {project.project_code}</span>
                    <span>|</span>
                    <span>Created: {project.created_at}</span>
                    <span>|</span>
                    <span>
                        Project Manager:{' '}
                        <span className="text-primary font-semibold">
                            {project.manager?.name ?? 'Unassigned'}
                        </span>
                    </span>
                </div>
            </div>

            {canUpdate ? (
                <div className="flex flex-wrap gap-3">
                    <Button type="button" variant="outline">
                        <MoreHorizontal data-icon="inline-start" />
                        More Actions
                    </Button>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="project-information-form"
                        disabled={processing}
                    >
                        {processing ? 'Saving...' : 'Save Changes'}
                    </Button>
                </div>
            ) : null}
        </header>
    );
}

function ProjectInformationCard({
    project,
    form,
    canUpdate,
    onSubmit,
}: {
    project: ProjectPayload;
    form: ReturnType<typeof useForm<ProjectFormData>>;
    canUpdate: boolean;
    onSubmit: (event: FormEvent) => void;
}) {
    if (!canUpdate) {
        return <ReadOnlyProjectInformation project={project} />;
    }

    return (
        <Card className="pv-card">
            <CardHeader>
                <CardTitle className="text-2xl">
                    Project Information
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    id="project-information-form"
                    onSubmit={onSubmit}
                    className="flex flex-col gap-5"
                >
                    <FieldGroup>
                        <div className="grid gap-5 md:grid-cols-2">
                            <FormField
                                label="Customer Name"
                                name="customer_name"
                                value={form.data.customer_name}
                                error={form.errors.customer_name}
                                onChange={(value) =>
                                    form.setData('customer_name', value)
                                }
                            />
                            <FormField
                                label="Estimated Construction Start Date"
                                name="starts_on"
                                type="date"
                                value={form.data.starts_on}
                                error={form.errors.starts_on}
                                onChange={(value) =>
                                    form.setData('starts_on', value)
                                }
                            />
                        </div>
                        <FormField
                            label="Customer Address"
                            name="address_line"
                            value={form.data.address_line}
                            error={form.errors.address_line}
                            onChange={(value) =>
                                form.setData('address_line', value)
                            }
                        />
                        <div className="grid gap-5 md:grid-cols-3">
                            <FormField
                                label="City"
                                name="city"
                                value={form.data.city}
                                error={form.errors.city}
                                onChange={(value) => form.setData('city', value)}
                            />
                            <FormField
                                label="State"
                                name="state"
                                value={form.data.state}
                                error={form.errors.state}
                                onChange={(value) =>
                                    form.setData('state', value)
                                }
                            />
                            <FormField
                                label="Postal Code"
                                name="postal_code"
                                value={form.data.postal_code}
                                error={form.errors.postal_code}
                                onChange={(value) =>
                                    form.setData('postal_code', value)
                                }
                            />
                        </div>
                        <div className="grid gap-5 md:grid-cols-2">
                            <FormField
                                label="Contract Value"
                                name="contract_amount"
                                type="number"
                                value={form.data.contract_amount}
                                error={form.errors.contract_amount}
                                onChange={(value) =>
                                    form.setData('contract_amount', value)
                                }
                            />
                            <FormField
                                label="Projected Completion Date"
                                name="estimated_completion_on"
                                type="date"
                                value={form.data.estimated_completion_on}
                                error={form.errors.estimated_completion_on}
                                onChange={(value) =>
                                    form.setData(
                                        'estimated_completion_on',
                                        value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-5 md:grid-cols-3">
                            <FormField
                                label="Project Type"
                                name="project_type"
                                value={form.data.project_type}
                                error={form.errors.project_type}
                                onChange={(value) =>
                                    form.setData('project_type', value)
                                }
                            />
                            <FormField
                                label="Status"
                                name="status"
                                value={form.data.status}
                                error={form.errors.status}
                                onChange={(value) =>
                                    form.setData('status', value)
                                }
                            />
                            <FormField
                                label="Current Phase"
                                name="phase"
                                value={form.data.phase}
                                error={form.errors.phase}
                                onChange={(value) =>
                                    form.setData('phase', value)
                                }
                            />
                        </div>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

function ReadOnlyProjectInformation({ project }: { project: ProjectPayload }) {
    return (
        <Card className="pv-card">
            <CardHeader>
                <CardTitle className="text-2xl">
                    Project Information
                </CardTitle>
            </CardHeader>
            <CardContent>
                <FieldGroup>
                    <div className="grid gap-5 md:grid-cols-2">
                        <ReadOnlyField
                            label="Customer Name"
                            value={project.client?.name ?? 'Client'}
                        />
                        <ReadOnlyField
                            label="Estimated Construction Start Date"
                            value={project.starts_on ?? 'TBD'}
                        />
                    </div>
                    <ReadOnlyField
                        label="Customer Address"
                        value={project.address}
                    />
                    <div className="grid gap-5 md:grid-cols-2">
                        <ReadOnlyField
                            label="Contract Value"
                            value={formatMoney(project.contract_amount)}
                        />
                        <ReadOnlyField
                            label="Current Phase"
                            value={project.phase}
                            gold
                        />
                    </div>
                </FieldGroup>
            </CardContent>
        </Card>
    );
}

function ProjectDocumentsCard({ project }: { project: ProjectPayload }) {
    const [uploadKey, setUploadKey] = useState(0);
    const uploadForm = useForm<{
        title: string;
        category: string;
        document: object | null;
    }>({
        title: '',
        category: 'Uploads',
        document: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        uploadForm.post(route('projects.documents.store', project.slug), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                setUploadKey((key) => key + 1);
            },
        });
    };

    return (
        <Card className="pv-card">
            <CardHeader>
                <CardTitle className="text-2xl">Project Documents</CardTitle>
                <CardDescription>PDF, JPG, JPEG, or PNG files.</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                {project.permissions.can_upload_documents ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <FieldGroup>
                            <Field data-invalid={!!uploadForm.errors.document}>
                                <FieldLabel htmlFor="document">
                                    Upload Document
                                </FieldLabel>
                                <Input
                                    key={uploadKey}
                                    id="document"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    aria-invalid={!!uploadForm.errors.document}
                                    onChange={(event) =>
                                        uploadForm.setData(
                                            'document',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <FieldError>
                                    {uploadForm.errors.document}
                                </FieldError>
                            </Field>
                            <div className="grid gap-4 md:grid-cols-2">
                                <FormField
                                    label="Title"
                                    name="document-title"
                                    value={uploadForm.data.title}
                                    error={uploadForm.errors.title}
                                    onChange={(value) =>
                                        uploadForm.setData('title', value)
                                    }
                                />
                                <FormField
                                    label="Category"
                                    name="document-category"
                                    value={uploadForm.data.category}
                                    error={uploadForm.errors.category}
                                    onChange={(value) =>
                                        uploadForm.setData('category', value)
                                    }
                                />
                            </div>
                        </FieldGroup>
                        <Button
                            type="submit"
                            disabled={uploadForm.processing}
                            className="self-start"
                        >
                            <UploadCloud data-icon="inline-start" />
                            {uploadForm.processing
                                ? 'Uploading...'
                                : 'Upload Files'}
                        </Button>
                    </form>
                ) : null}

                <DocumentList project={project} />
            </CardContent>
        </Card>
    );
}

function DocumentList({ project }: { project: ProjectPayload }) {
    if (project.documents.length === 0) {
        return (
            <div className="border-border text-muted-foreground rounded-lg border p-5 text-sm">
                No visible documents have been uploaded yet.
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <h3 className="font-semibold">Uploaded Documents</h3>
            {project.documents.map((document) => (
                <a
                    key={document.id}
                    href={document.url}
                    target="_blank"
                    rel="noreferrer"
                    className="border-border hover:border-primary/50 flex items-center gap-4 rounded-lg border p-3 transition"
                >
                    <FileText className="text-primary size-4" />
                    <div className="min-w-0 flex-1">
                        <div className="truncate font-semibold">
                            {document.title}
                        </div>
                        <div className="text-muted-foreground text-xs">
                            {formatBytes(document.size)} ·{' '}
                            {document.updated_at}
                        </div>
                    </div>
                    <Download className="text-muted-foreground size-4" />
                </a>
            ))}
        </div>
    );
}

function ProjectPhotosCard({ project }: { project: ProjectPayload }) {
    const [uploadKey, setUploadKey] = useState(0);
    const uploadForm = useForm<{
        alt_text: string;
        photo: object | null;
    }>({
        alt_text: '',
        photo: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        uploadForm.post(route('projects.media.store', project.slug), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                setUploadKey((key) => key + 1);
            },
        });
    };

    return (
        <Card className="pv-card">
            <CardHeader>
                <CardTitle className="text-2xl">Project Photos</CardTitle>
                <CardDescription>JPG, PNG, HEIC, or WEBP files.</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                {project.permissions.can_upload_media ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <FieldGroup>
                            <Field data-invalid={!!uploadForm.errors.photo}>
                                <FieldLabel htmlFor="photo">
                                    Upload Photo
                                </FieldLabel>
                                <Input
                                    key={uploadKey}
                                    id="photo"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.heic,.webp,image/jpeg,image/png,image/heic,image/webp"
                                    aria-invalid={!!uploadForm.errors.photo}
                                    onChange={(event) =>
                                        uploadForm.setData(
                                            'photo',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <FieldError>
                                    {uploadForm.errors.photo}
                                </FieldError>
                            </Field>
                            <FormField
                                label="Photo Description"
                                name="alt_text"
                                value={uploadForm.data.alt_text}
                                error={uploadForm.errors.alt_text}
                                onChange={(value) =>
                                    uploadForm.setData('alt_text', value)
                                }
                            />
                        </FieldGroup>
                        <Button
                            type="submit"
                            disabled={uploadForm.processing}
                            className="self-start"
                        >
                            <ImagePlus data-icon="inline-start" />
                            {uploadForm.processing
                                ? 'Uploading...'
                                : 'Upload Photos'}
                        </Button>
                    </form>
                ) : null}

                <div className="flex flex-col gap-3">
                    <h3 className="font-semibold">Recent Photos</h3>
                    {project.media.length > 0 ? (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {project.media.slice(0, 8).map((photo) => (
                                <a
                                    key={photo.id}
                                    href={photo.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="border-border block overflow-hidden rounded-lg border"
                                >
                                    <img
                                        src={photo.url}
                                        alt={
                                            photo.alt_text ??
                                            `${project.name} photo`
                                        }
                                        className="aspect-video w-full object-cover"
                                    />
                                </a>
                            ))}
                        </div>
                    ) : (
                        <div className="border-border text-muted-foreground rounded-lg border p-5 text-sm">
                            No project photos have been uploaded yet.
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function SubcontractorAssignmentCard({
    project,
}: {
    project: ProjectPayload;
}) {
    const initialIds = useMemo(
        () =>
            project.available_subcontractors
                .filter((subcontractor) => subcontractor.selected)
                .map((subcontractor) => subcontractor.id),
        [project.available_subcontractors],
    );
    const form = useForm<{ subcontractor_ids: number[] }>({
        subcontractor_ids: initialIds,
    });

    const toggleSubcontractor = (id: number, checked: boolean) => {
        const selected = new Set(form.data.subcontractor_ids);

        if (checked) {
            selected.add(id);
        } else {
            selected.delete(id);
        }

        form.setData('subcontractor_ids', Array.from(selected));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(route('projects.subcontractors.update', project.slug), {
            preserveScroll: true,
        });
    };

    return (
        <Card className="pv-card">
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle className="text-2xl">
                            Assign Sub-Contractors
                        </CardTitle>
                        <CardDescription>
                            Select and assign subcontractors to this project.
                        </CardDescription>
                    </div>
                    <Badge variant="secondary">
                        {form.data.subcontractor_ids.length} Selected
                    </Badge>
                </div>
            </CardHeader>
            <form onSubmit={submit}>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead />
                                <TableHead>Sub-Contractor</TableHead>
                                <TableHead>Trade</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Email</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {project.available_subcontractors.map(
                                (subcontractor) => {
                                    const checked =
                                        form.data.subcontractor_ids.includes(
                                            subcontractor.id,
                                        );

                                    return (
                                        <TableRow key={subcontractor.id}>
                                            <TableCell>
                                                <Checkbox
                                                    checked={checked}
                                                    onCheckedChange={(
                                                        nextChecked,
                                                    ) =>
                                                        toggleSubcontractor(
                                                            subcontractor.id,
                                                            nextChecked ===
                                                                true,
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="font-semibold">
                                                {subcontractor.name}
                                            </TableCell>
                                            <TableCell>
                                                {subcontractor.assigned_scope ??
                                                    subcontractor.title ??
                                                    'Assigned Trade Partner'}
                                            </TableCell>
                                            <TableCell>
                                                {subcontractor.phone ?? 'N/A'}
                                            </TableCell>
                                            <TableCell>
                                                {subcontractor.email}
                                            </TableCell>
                                        </TableRow>
                                    );
                                },
                            )}
                        </TableBody>
                    </Table>
                    {project.available_subcontractors.length === 0 ? (
                        <div className="text-muted-foreground border-border mt-4 rounded-lg border p-5 text-sm">
                            No company subcontractors are available to assign.
                        </div>
                    ) : null}
                </CardContent>
                <CardFooter className="justify-between">
                    <p className="text-muted-foreground text-sm">
                        Selected subcontractors receive scoped project access.
                    </p>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving...' : 'Save Assignments'}
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

function RoleContextCard({ project }: { project: ProjectPayload }) {
    if (project.role !== 'subcontractor') {
        return null;
    }

    const assignment = project.team.find(
        (member) => member.role === 'subcontractor',
    );
    const task =
        project.timeline.find((item) => item.status === 'in_progress') ??
        project.timeline[0];

    return (
        <Card className="pv-card">
            <CardHeader>
                <CardTitle className="text-2xl">Assigned Work</CardTitle>
                <CardDescription>
                    Your project scope and next visible task.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-5 md:grid-cols-2">
                <ReadOnlyField
                    label="Assigned Scope"
                    value={assignment?.assigned_scope ?? 'Assigned Trade Partner'}
                    gold
                />
                <ReadOnlyField
                    label="Current Task"
                    value={task?.title ?? project.phase}
                />
            </CardContent>
        </Card>
    );
}

function ProjectSummaryCard({ project }: { project: ProjectPayload }) {
    return (
        <Card className="pv-card">
            <CardHeader>
                <CardTitle className="text-2xl">Project Summary</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="flex flex-col gap-4 text-sm">
                    <SummaryRow label="Project Type" value={project.project_type} />
                    <SummaryRow
                        label="Contract Value"
                        value={formatMoney(project.contract_amount)}
                    />
                    <SummaryRow
                        label="Start Date"
                        value={project.starts_on ?? 'TBD'}
                    />
                    <SummaryRow
                        label="Completion"
                        value={project.estimated_completion_on ?? 'TBD'}
                    />
                    <div className="grid grid-cols-[1fr_auto] items-center gap-4">
                        <dt className="text-muted-foreground">Status</dt>
                        <dd>
                            <StatusPill status={project.status} />
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[1fr_auto] gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-semibold">{value}</dd>
        </div>
    );
}

function FormField({
    label,
    name,
    value,
    error,
    type = 'text',
    onChange,
}: {
    label: string;
    name: string;
    value: string;
    error?: string;
    type?: string;
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input
                id={name}
                type={type}
                value={value}
                aria-invalid={!!error}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function ReadOnlyField({
    label,
    value,
    gold = false,
}: {
    label: string;
    value: string;
    gold?: boolean;
}) {
    return (
        <Field>
            <FieldLabel>{label}</FieldLabel>
            <div
                className={
                    gold
                        ? 'border-border bg-background/40 text-primary rounded-lg border px-4 py-3'
                        : 'border-border bg-background/40 rounded-lg border px-4 py-3'
                }
            >
                {value}
            </div>
        </Field>
    );
}

function projectFormDefaults(project: ProjectPayload): ProjectFormData {
    return {
        customer_name: project.client?.name ?? '',
        address_line: project.address_line,
        city: project.city,
        state: project.state,
        postal_code: project.postal_code ?? '',
        contract_amount: project.contract_amount ?? '',
        starts_on: project.starts_on_input ?? '',
        estimated_completion_on: project.estimated_completion_on_input ?? '',
        project_type: project.project_type,
        status: project.status,
        phase: project.phase,
    };
}

function roleEyebrow(project: ProjectPayload) {
    if (project.role === 'client') {
        return 'Homeowner';
    }

    if (project.role === 'subcontractor') {
        return 'Subcontractor';
    }

    if (project.role === 'company_manager') {
        return 'Company Manager';
    }

    if (project.role === 'company_admin') {
        return 'Company Admin';
    }

    return project.company.name;
}

function formatMoney(amount?: string | null) {
    const value = Number(amount);

    if (!Number.isFinite(value)) {
        return 'TBD';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatBytes(bytes?: number | null) {
    if (!bytes) {
        return 'Unknown size';
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
