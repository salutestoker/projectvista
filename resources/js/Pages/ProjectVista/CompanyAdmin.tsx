import { ProjectCard } from '@/Components/ProjectVista/ProjectCard';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { CompanyPayload, ProjectCardPayload } from '@/types/projectvista';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

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
        status: string;
        expires_at?: string | null;
    }[];
}

export default function CompanyAdmin({
    company,
    users,
    projects,
    invitations,
}: CompanyAdminProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'client',
        project_id: projects[0]?.id?.toString() ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('companies.invitations.store', company.slug), {
            onSuccess: () => reset('email'),
        });
    };

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
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                            className="mt-2 w-full rounded-md border border-white/10 bg-black/30 px-3 py-2 text-white"
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
                        <select
                            id="role"
                            value={data.role}
                            onChange={(event) =>
                                setData('role', event.target.value)
                            }
                            className="mt-2 w-full rounded-md border border-white/10 bg-black/30 px-3 py-2 text-white"
                        >
                            <option value="client">Client/Homeowner</option>
                            <option value="company_manager">
                                Company Manager
                            </option>
                            <option value="subcontractor">
                                Sub-Contractor
                            </option>
                        </select>

                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-5 w-full rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black transition hover:bg-[#f0d58c] disabled:opacity-50"
                        >
                            {processing ? 'Creating...' : 'Create invite'}
                        </button>
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
                                        {invitation.role} · expires{' '}
                                        {invitation.expires_at}
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
