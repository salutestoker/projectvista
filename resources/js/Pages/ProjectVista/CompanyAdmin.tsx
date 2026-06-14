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
import { CompanyPayload, ProjectCardPayload } from '@/types/projectvista';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';

interface CompanyAdminProps {
    company: CompanyPayload;
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
        subcontractor_type?: string | null;
        status: string;
        expires_at?: string | null;
    }[];
    subcontractor_types: {
        id: number;
        name: string;
    }[];
}

const invitationRoles = [
    { value: 'client', label: 'Client/Homeowner' },
    { value: 'company_manager', label: 'Company Manager' },
    { value: 'subcontractor', label: 'Sub-Contractor' },
];

export default function CompanyAdmin({
    company,
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

    return (
        <ProjectVistaShell
            title="Users & Standards"
            eyebrow={company.name}
            role="company_admin"
        >
            <Head title={`${company.name} Admin`} />
            <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
                <section className="space-y-6">
                    <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                        <h2 className="text-xl font-semibold">Team Access</h2>
                        <div className="mt-5 overflow-hidden rounded-lg border border-white/10">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-white/5 text-white/55">
                                    <tr>
                                        <th className="px-4 py-3">Name</th>
                                        <th className="px-4 py-3">Role</th>
                                        <th className="px-4 py-3">Email</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-white/10">
                                    {users.map((user) => (
                                        <tr key={user.id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {user.name}
                                                </div>
                                                <div className="text-xs text-white/45">
                                                    {user.title}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusPill
                                                    status={user.role}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-white/60">
                                                {user.email}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-4 text-xl font-semibold">
                            Company Projects
                        </h2>
                        <div className="grid gap-5 md:grid-cols-2">
                            {projects.map((project) => (
                                <ProjectCard
                                    key={project.id}
                                    project={project}
                                />
                            ))}
                        </div>
                    </div>
                </section>

                <aside className="space-y-5">
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
                                            {selectedSubcontractorTypeLabel}
                                        </SelectedSelectLabel>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {subcontractor_types.map((type) => (
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
                            {processing ? 'Creating...' : 'Create invite'}
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
                                        · expires {invitation.expires_at}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </aside>
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
