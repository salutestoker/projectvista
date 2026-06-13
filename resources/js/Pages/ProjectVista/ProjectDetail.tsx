import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { ProjectPayload } from '@/types/projectvista';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Download,
    FileText,
    MessageSquare,
    Sparkles,
    Timer,
    UploadCloud,
} from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

interface ProjectDetailProps {
    project: ProjectPayload;
}

export default function ProjectDetail({ project }: ProjectDetailProps) {
    if (project.role === 'client') {
        return <ClientProjectDetail project={project} />;
    }

    return <InternalProjectDetail project={project} />;
}

function ClientProjectDetail({ project }: ProjectDetailProps) {
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

    const handleUpload = (event: FormEvent) => {
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
        <ProjectVistaShell
            title="Project"
            eyebrow="Homeowner"
            role={project.role}
            project={project}
            primaryAction={{ label: 'Share' }}
        >
            <Head title={`${project.name} Project`} />
            <div className="flex flex-col gap-8">
                <section
                    className="border-border overflow-hidden rounded-xl border bg-cover bg-center"
                    style={{
                        backgroundImage: project.hero_image_url
                            ? `linear-gradient(90deg, rgba(8,13,15,0.86), rgba(8,13,15,0.35)), url(${project.hero_image_url})`
                            : 'linear-gradient(135deg, var(--pv-panel), var(--pv-background))',
                    }}
                >
                    <div className="min-h-60 p-8 md:p-12">
                        <h2 className="text-4xl font-semibold md:text-5xl">
                            {project.name}
                        </h2>
                        <p className="mt-3 text-lg">{project.address}</p>
                        <div className="mt-5 flex flex-wrap gap-2">
                            <StatusPill status={project.health_status} />
                            <StatusPill status={project.status} />
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-[1fr_600px]">
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
                                        value={formatMoney(
                                            project.contract_amount,
                                        )}
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

                    <div className="flex flex-col gap-6">
                        <Card className="pv-card">
                            <CardHeader>
                                <CardTitle className="text-2xl">
                                    Project Documents
                                </CardTitle>
                                <CardDescription>
                                    Upload PDF, JPG, JPEG, or PNG files.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {project.permissions.can_upload_documents ? (
                                    <form
                                        onSubmit={handleUpload}
                                        className="flex flex-col gap-5"
                                    >
                                        <FieldGroup>
                                            <Field
                                                data-invalid={
                                                    !!uploadForm.errors.document
                                                }
                                            >
                                                <FieldLabel htmlFor="document">
                                                    File
                                                </FieldLabel>
                                                <Input
                                                    key={uploadKey}
                                                    id="document"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                                    aria-invalid={
                                                        !!uploadForm.errors
                                                            .document
                                                    }
                                                    onChange={(event) =>
                                                        uploadForm.setData(
                                                            'document',
                                                            event.target
                                                                .files?.[0] ??
                                                                null,
                                                        )
                                                    }
                                                />
                                                <FieldError>
                                                    {uploadForm.errors.document}
                                                </FieldError>
                                            </Field>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <Field
                                                    data-invalid={
                                                        !!uploadForm.errors
                                                            .title
                                                    }
                                                >
                                                    <FieldLabel htmlFor="title">
                                                        Title
                                                    </FieldLabel>
                                                    <Input
                                                        id="title"
                                                        value={
                                                            uploadForm.data
                                                                .title
                                                        }
                                                        aria-invalid={
                                                            !!uploadForm.errors
                                                                .title
                                                        }
                                                        onChange={(event) =>
                                                            uploadForm.setData(
                                                                'title',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Optional document title"
                                                    />
                                                    <FieldError>
                                                        {
                                                            uploadForm.errors
                                                                .title
                                                        }
                                                    </FieldError>
                                                </Field>
                                                <Field
                                                    data-invalid={
                                                        !!uploadForm.errors
                                                            .category
                                                    }
                                                >
                                                    <FieldLabel htmlFor="category">
                                                        Category
                                                    </FieldLabel>
                                                    <Input
                                                        id="category"
                                                        value={
                                                            uploadForm.data
                                                                .category
                                                        }
                                                        aria-invalid={
                                                            !!uploadForm.errors
                                                                .category
                                                        }
                                                        onChange={(event) =>
                                                            uploadForm.setData(
                                                                'category',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                    <FieldError>
                                                        {
                                                            uploadForm.errors
                                                                .category
                                                        }
                                                    </FieldError>
                                                </Field>
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
                                ) : (
                                    <div className="border-border text-muted-foreground rounded-lg border p-6 text-sm">
                                        Document uploads are not available for
                                        this role.
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="pv-card">
                            <CardHeader>
                                <CardTitle className="text-2xl">
                                    Uploaded Documents
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                {project.documents.length > 0 ? (
                                    project.documents.map((document) => (
                                        <a
                                            key={document.id}
                                            href={document.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="border-border hover:border-primary/50 flex items-center gap-4 rounded-lg border p-4 transition"
                                        >
                                            <FileText className="text-primary size-5" />
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate font-semibold">
                                                    {document.title}
                                                </div>
                                                <div className="text-muted-foreground text-sm">
                                                    {formatBytes(document.size)}{' '}
                                                    · {document.updated_at}
                                                </div>
                                            </div>
                                            <Download className="text-muted-foreground size-4" />
                                        </a>
                                    ))
                                ) : (
                                    <div className="text-muted-foreground border-border rounded-lg border p-5 text-sm">
                                        No visible documents have been uploaded
                                        yet.
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </section>
            </div>
        </ProjectVistaShell>
    );
}

function InternalProjectDetail({ project }: ProjectDetailProps) {
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

                <aside className="flex flex-col gap-4">
                    <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                        <h3 className="font-semibold">Project Identity</h3>
                        <dl className="mt-4 flex flex-col gap-3 text-sm">
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

function formatMoney(amount?: string | null) {
    const value = Number(amount);

    if (!Number.isFinite(value)) {
        return 'TBD';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
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
