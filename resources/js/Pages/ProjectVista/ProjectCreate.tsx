import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { Badge } from '@/Components/ui/badge';
import { Button, buttonVariants } from '@/Components/ui/button';
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
    FieldLegend,
    FieldSet,
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
import { cn } from '@/lib/utils';
import {
    CompanyPayload,
    ProjectCreateCompanyPayload,
    ProjectVistaRole,
} from '@/types/projectvista';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FolderPlus } from 'lucide-react';
import { FormEvent, ReactNode, useMemo } from 'react';

interface ProjectCreateProps {
    role: ProjectVistaRole;
    company: CompanyPayload | null;
    companies: ProjectCreateCompanyPayload[];
}

type ProjectCreateForm = {
    company_id: string;
    timeline_template_id: string;
    name: string;
    client_name: string;
    client_email: string;
    address_line: string;
    city: string;
    state: string;
    postal_code: string;
    contract_amount: string;
    contract_signed_on: string;
    client_summary: string;
    latest_update: string;
    next_step: string;
    subcontractor_ids: number[];
};

export default function ProjectCreate({
    role,
    company,
    companies,
}: ProjectCreateProps) {
    const initialCompany = companies[0] ?? null;
    const form = useForm<ProjectCreateForm>({
        company_id: initialCompany?.id.toString() ?? '',
        timeline_template_id:
            initialCompany?.timeline_templates[0]?.id?.toString() ?? '',
        name: '',
        client_name: '',
        client_email: '',
        address_line: '',
        city: '',
        state: '',
        postal_code: '',
        contract_amount: '',
        contract_signed_on: '',
        client_summary: '',
        latest_update: '',
        next_step: '',
        subcontractor_ids: [],
    });

    const selectedCompany = useMemo(
        () =>
            companies.find(
                (candidate) => candidate.id.toString() === form.data.company_id,
            ) ?? companies[0],
        [companies, form.data.company_id],
    );
    const selectedTemplate = selectedCompany?.timeline_templates.find(
        (template) =>
            template.id?.toString() === form.data.timeline_template_id,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(route('projects.store'), {
            preserveScroll: true,
        });
    };

    const changeCompany = (companyId: string) => {
        const nextCompany =
            companies.find(
                (candidate) => candidate.id.toString() === companyId,
            ) ?? companies[0];

        form.setData({
            ...form.data,
            company_id: companyId,
            timeline_template_id:
                nextCompany?.timeline_templates[0]?.id?.toString() ?? '',
            subcontractor_ids: [],
        });
    };

    const toggleSubcontractor = (id: number, checked: boolean) => {
        const selected = new Set(form.data.subcontractor_ids);

        if (checked) {
            selected.add(id);
        } else {
            selected.delete(id);
        }

        form.setData('subcontractor_ids', Array.from(selected));
    };

    return (
        <ProjectVistaShell
            title="New Project"
            eyebrow={selectedCompany?.name ?? company?.name ?? 'ProjectVista'}
            role={role}
            company={selectedCompany ?? company}
        >
            <Head title="New Project" />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <header className="flex flex-col gap-4 border-b border-white/10 pb-6 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex flex-col gap-3">
                        <Link
                            href={route('projects.index')}
                            className="text-primary inline-flex items-center gap-2 text-sm font-semibold tracking-[0.18em] uppercase"
                        >
                            <ArrowLeft data-icon="inline-start" />
                            Projects
                        </Link>
                        <div>
                            <h1 className="text-4xl font-semibold md:text-5xl">
                                Create Project
                            </h1>
                            <p className="text-muted-foreground mt-2">
                                Add project details, homeowner access, and the
                                starting timeline template.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Link
                            href={route('projects.index')}
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            Cancel
                        </Link>
                        <Button type="submit" disabled={form.processing}>
                            <FolderPlus data-icon="inline-start" />
                            {form.processing ? 'Creating...' : 'Create Project'}
                        </Button>
                    </div>
                </header>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_440px]">
                    <div className="flex flex-col gap-6">
                        <Card className="pv-card">
                            <CardHeader>
                                <CardTitle className="text-2xl">
                                    Project Information
                                </CardTitle>
                                <CardDescription>
                                    Core project and homeowner details.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    {companies.length > 1 ? (
                                        <SelectField
                                            label="Company"
                                            value={form.data.company_id}
                                            error={form.errors.company_id}
                                            selectedLabel={
                                                selectedCompany?.name ??
                                                'Select company'
                                            }
                                            onChange={changeCompany}
                                            options={companies.map(
                                                (candidate) => ({
                                                    value: candidate.id.toString(),
                                                    label: candidate.name,
                                                }),
                                            )}
                                        />
                                    ) : null}

                                    <TextField
                                        label="Project Name"
                                        name="name"
                                        value={form.data.name}
                                        error={form.errors.name}
                                        onChange={(value) =>
                                            form.setData('name', value)
                                        }
                                    />

                                    <div className="grid gap-5 md:grid-cols-2">
                                        <TextField
                                            label="Homeowner Name"
                                            name="client_name"
                                            value={form.data.client_name}
                                            error={form.errors.client_name}
                                            onChange={(value) =>
                                                form.setData(
                                                    'client_name',
                                                    value,
                                                )
                                            }
                                        />
                                        <TextField
                                            label="Homeowner Email"
                                            name="client_email"
                                            type="email"
                                            value={form.data.client_email}
                                            error={form.errors.client_email}
                                            onChange={(value) =>
                                                form.setData(
                                                    'client_email',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>

                                    <TextField
                                        label="Customer Address"
                                        name="address_line"
                                        value={form.data.address_line}
                                        error={form.errors.address_line}
                                        onChange={(value) =>
                                            form.setData('address_line', value)
                                        }
                                    />
                                    <div className="grid gap-5 md:grid-cols-3">
                                        <TextField
                                            label="City"
                                            name="city"
                                            value={form.data.city}
                                            error={form.errors.city}
                                            onChange={(value) =>
                                                form.setData('city', value)
                                            }
                                        />
                                        <TextField
                                            label="State"
                                            name="state"
                                            value={form.data.state}
                                            error={form.errors.state}
                                            onChange={(value) =>
                                                form.setData('state', value)
                                            }
                                        />
                                        <TextField
                                            label="Postal Code"
                                            name="postal_code"
                                            value={form.data.postal_code}
                                            error={form.errors.postal_code}
                                            onChange={(value) =>
                                                form.setData(
                                                    'postal_code',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        <Card className="pv-card">
                            <CardHeader>
                                <CardTitle className="text-2xl">
                                    Contract & Schedule
                                </CardTitle>
                                <CardDescription>
                                    Contract date drives automatic timeline
                                    scheduling.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <TextField
                                            label="Contract Value"
                                            name="contract_amount"
                                            type="number"
                                            value={form.data.contract_amount}
                                            error={form.errors.contract_amount}
                                            onChange={(value) =>
                                                form.setData(
                                                    'contract_amount',
                                                    value,
                                                )
                                            }
                                        />
                                        <TextField
                                            label="Contract Signed Date"
                                            name="contract_signed_on"
                                            type="date"
                                            required
                                            value={form.data.contract_signed_on}
                                            error={
                                                form.errors.contract_signed_on
                                            }
                                            onChange={(value) =>
                                                form.setData(
                                                    'contract_signed_on',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        <Card className="pv-card">
                            <CardHeader>
                                <CardTitle className="text-2xl">
                                    Client Experience Copy
                                </CardTitle>
                                <CardDescription>
                                    Optional summary content shown in the client
                                    portal.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <TextAreaField
                                        label="Client Summary"
                                        name="client_summary"
                                        value={form.data.client_summary}
                                        error={form.errors.client_summary}
                                        onChange={(value) =>
                                            form.setData(
                                                'client_summary',
                                                value,
                                            )
                                        }
                                    />
                                    <TextAreaField
                                        label="Latest Update"
                                        name="latest_update"
                                        value={form.data.latest_update}
                                        error={form.errors.latest_update}
                                        onChange={(value) =>
                                            form.setData(
                                                'latest_update',
                                                value,
                                            )
                                        }
                                    />
                                    <TextAreaField
                                        label="Next Step"
                                        name="next_step"
                                        value={form.data.next_step}
                                        error={form.errors.next_step}
                                        onChange={(value) =>
                                            form.setData('next_step', value)
                                        }
                                    />
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="flex flex-col gap-6">
                        <Card className="pv-card">
                            <CardHeader>
                                <CardTitle className="text-2xl">
                                    Timeline Template
                                </CardTitle>
                                <CardDescription>
                                    Select the starting schedule structure.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <SelectField
                                        label="Template"
                                        value={form.data.timeline_template_id}
                                        error={
                                            form.errors.timeline_template_id
                                        }
                                        selectedLabel={
                                            selectedTemplate?.name ??
                                            'Select template'
                                        }
                                        onChange={(value) =>
                                            form.setData(
                                                'timeline_template_id',
                                                value,
                                            )
                                        }
                                        options={
                                            selectedCompany?.timeline_templates.map(
                                                (template) => ({
                                                    value:
                                                        template.id?.toString() ??
                                                        '',
                                                    label: template.name,
                                                }),
                                            ) ?? []
                                        }
                                    />
                                    {selectedTemplate ? (
                                        <div className="border-border bg-background/40 flex flex-col gap-3 rounded-lg border p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-semibold">
                                                        {selectedTemplate.name}
                                                    </div>
                                                    <div className="text-muted-foreground text-sm">
                                                        {selectedTemplate.description ??
                                                            'No description'}
                                                    </div>
                                                </div>
                                                {selectedTemplate.is_default ? (
                                                    <Badge variant="secondary">
                                                        Default
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <div className="text-muted-foreground text-sm">
                                                {selectedTemplate.tasks.length}{' '}
                                                tasks ·{' '}
                                                {selectedTemplate.tasks.reduce(
                                                    (total, task) =>
                                                        total +
                                                        task.default_duration_working_days,
                                                    0,
                                                )}{' '}
                                                working days
                                            </div>
                                        </div>
                                    ) : null}
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        <Card className="pv-card">
                            <CardHeader>
                                <CardTitle className="text-2xl">
                                    Assign Sub-Contractors
                                </CardTitle>
                                <CardDescription>
                                    Optional initial project access.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldSet>
                                    <FieldLegend variant="label">
                                        Trade Partners
                                    </FieldLegend>
                                    <FieldDescription>
                                        Selected subcontractors receive scoped
                                        project access.
                                    </FieldDescription>
                                    <FieldGroup className="gap-3">
                                        {selectedCompany?.subcontractors.map(
                                            (subcontractor) => {
                                                const checked =
                                                    form.data.subcontractor_ids.includes(
                                                        subcontractor.id,
                                                    );

                                                return (
                                                    <Field
                                                        key={subcontractor.id}
                                                        orientation="horizontal"
                                                    >
                                                        <Checkbox
                                                            id={`subcontractor-${subcontractor.id}`}
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
                                                        <FieldLabel
                                                            htmlFor={`subcontractor-${subcontractor.id}`}
                                                            className="font-normal"
                                                        >
                                                            <span className="flex flex-col">
                                                                <span>
                                                                    {
                                                                        subcontractor.name
                                                                    }
                                                                </span>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {subcontractor.title ??
                                                                        subcontractor.email}
                                                                </span>
                                                            </span>
                                                        </FieldLabel>
                                                    </Field>
                                                );
                                            },
                                        )}
                                        {selectedCompany?.subcontractors
                                            .length === 0 ? (
                                            <div className="border-border text-muted-foreground rounded-lg border p-4 text-sm">
                                                No subcontractors are available
                                                for this company.
                                            </div>
                                        ) : null}
                                    </FieldGroup>
                                </FieldSet>
                            </CardContent>
                            <CardFooter>
                                <Badge variant="secondary">
                                    {form.data.subcontractor_ids.length}{' '}
                                    selected
                                </Badge>
                            </CardFooter>
                        </Card>
                    </aside>
                </section>
            </form>
        </ProjectVistaShell>
    );
}

function TextField({
    label,
    name,
    value,
    error,
    type = 'text',
    required = false,
    onChange,
}: {
    label: string;
    name: string;
    value: string;
    error?: string;
    type?: string;
    required?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input
                id={name}
                type={type}
                value={value}
                required={required}
                aria-invalid={!!error}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function TextAreaField({
    label,
    name,
    value,
    error,
    onChange,
}: {
    label: string;
    name: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Textarea
                id={name}
                value={value}
                rows={4}
                aria-invalid={!!error}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function SelectField({
    label,
    value,
    selectedLabel,
    options,
    error,
    onChange,
}: {
    label: string;
    value: string;
    selectedLabel: string;
    options: { value: string; label: string }[];
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel>{label}</FieldLabel>
            <Select value={value} onValueChange={(next) => onChange(String(next))}>
                <SelectTrigger
                    className="w-full data-[size=default]:h-11"
                    aria-invalid={!!error}
                >
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
            <FieldError>{error}</FieldError>
        </Field>
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
