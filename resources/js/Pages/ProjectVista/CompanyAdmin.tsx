import { CompanySettingsNav } from '@/Components/ProjectVista/CompanySettingsNav';
import { DataTable } from '@/Components/ProjectVista/DataTable';
import { ProjectCard } from '@/Components/ProjectVista/ProjectCard';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
} from '@/Components/ui/select';
import {
    CompanyPayload,
    CompanySettingsNavPayload,
    CompanySettingsPermissionsPayload,
    ProjectCardPayload,
    ProjectVistaRole,
    SubcontractorTypePayload,
} from '@/types/projectvista';
import { Head, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { FormEvent, ReactNode, useMemo } from 'react';

interface CompanyAdminProps {
    company: CompanyPayload;
    role: ProjectVistaRole;
    settingsNav: CompanySettingsNavPayload;
    permissions: CompanySettingsPermissionsPayload;
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
    subcontractor_types: SubcontractorTypePayload[];
}

const invitationRoles = [
    { value: 'client', label: 'Client/Homeowner' },
    { value: 'company_manager', label: 'Company Manager' },
    { value: 'subcontractor', label: 'Sub-Contractor' },
];

export default function CompanyAdmin({
    company,
    role,
    settingsNav,
    permissions,
    users,
    projects,
    invitations,
    subcontractor_types,
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
            title="Company Settings"
            eyebrow={company.name}
            role={role}
            company={company}
        >
            <Head title={`${company.name} Settings`} />
            <div className="flex flex-col gap-6">
                <CompanySettingsNav nav={settingsNav} />

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
                                    className="text-muted-foreground mt-5 block text-sm"
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
                                    className="text-muted-foreground mt-4 block text-sm"
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
                                            className="text-muted-foreground mt-4 block text-sm"
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
                                <div className="mt-4 flex flex-col gap-3">
                                    {invitations.map((invitation) => (
                                        <div
                                            key={invitation.id}
                                            className="rounded-md bg-black/25 p-3"
                                        >
                                            <div className="font-medium">
                                                {invitation.email}
                                            </div>
                                            <div className="text-muted-foreground mt-1 text-xs">
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
