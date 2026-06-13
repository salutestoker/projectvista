import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { ApprovalPayload, ProjectPayload } from '@/types/projectvista';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface ApprovalsProps {
    project: ProjectPayload;
}

export default function Approvals({ project }: ApprovalsProps) {
    return (
        <ProjectVistaShell
            title="Approvals Queue"
            eyebrow={project.name}
            role={project.role}
            project={project}
        >
            <Head title={`${project.name} Approvals`} />
            <div className="space-y-4">
                {project.approvals.map((approval) => (
                    <ApprovalCard
                        key={approval.id}
                        project={project}
                        approval={approval}
                    />
                ))}
            </div>
        </ProjectVistaShell>
    );
}

function ApprovalCard({
    project,
    approval,
}: {
    project: ProjectPayload;
    approval: ApprovalPayload;
}) {
    const [status, setStatus] = useState<'approved' | 'changes_requested'>(
        'approved',
    );
    const { data, setData, patch, processing, errors } = useForm({
        status,
        response_note: '',
    });

    const canRespond =
        project.role === 'client' && approval.status === 'pending';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('approvals.response', approval.id), {
            preserveScroll: true,
        });
    };

    return (
        <article className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="text-xs tracking-[0.25em] text-[#d6b36a] uppercase">
                        {approval.selection ?? 'Project approval'}
                    </div>
                    <h2 className="mt-2 text-xl font-semibold">
                        {approval.title}
                    </h2>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-white/65">
                        {approval.body}
                    </p>
                </div>
                <StatusPill status={approval.status} />
            </div>
            <div className="mt-4 text-sm text-white/45">
                Due {approval.due_on ?? 'TBD'}
            </div>

            {canRespond && (
                <form onSubmit={submit} className="mt-5 max-w-2xl space-y-3">
                    <div className="grid gap-2 sm:grid-cols-2">
                        {(['approved', 'changes_requested'] as const).map(
                            (nextStatus) => (
                                <button
                                    key={nextStatus}
                                    type="button"
                                    onClick={() => {
                                        setStatus(nextStatus);
                                        setData('status', nextStatus);
                                    }}
                                    className={`rounded-md border px-3 py-2 text-sm capitalize ${
                                        data.status === nextStatus
                                            ? 'border-[#d6b36a] bg-[#d6b36a]/15 text-[#f5dfa6]'
                                            : 'border-white/10 bg-black/20 text-white/65'
                                    }`}
                                >
                                    {nextStatus.replace('_', ' ')}
                                </button>
                            ),
                        )}
                    </div>
                    <textarea
                        value={data.response_note}
                        onChange={(event) =>
                            setData('response_note', event.target.value)
                        }
                        placeholder="Optional response note"
                        className="min-h-24 w-full rounded-md border border-white/10 bg-black/30 px-3 py-2 text-white"
                    />
                    {errors.response_note && (
                        <p className="text-sm text-rose-300">
                            {errors.response_note}
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black"
                    >
                        Submit response
                    </button>
                </form>
            )}
        </article>
    );
}
