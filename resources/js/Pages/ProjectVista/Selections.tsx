import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { ProjectPayload, SelectionPayload } from '@/types/projectvista';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface SelectionsProps {
    project: ProjectPayload;
}

export default function Selections({ project }: SelectionsProps) {
    return (
        <ProjectVistaShell
            title="Selections"
            eyebrow={project.name}
            role={project.role}
            project={project}
        >
            <Head title={`${project.name} Selections`} />
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                {project.selections.map((selection) => (
                    <SelectionCard
                        key={selection.id}
                        project={project}
                        selection={selection}
                    />
                ))}
            </div>
        </ProjectVistaShell>
    );
}

function SelectionCard({
    project,
    selection,
}: {
    project: ProjectPayload;
    selection: SelectionPayload;
}) {
    const [mode, setMode] = useState<'approved' | 'changes_requested'>(
        'approved',
    );
    const { data, setData, patch, processing, errors } = useForm({
        status: mode,
        client_response: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('selections.response', selection.id), {
            preserveScroll: true,
        });
    };

    const canRespond =
        project.role === 'client' && selection.status === 'waiting_client';

    return (
        <article className="overflow-hidden rounded-lg border border-white/10 bg-white/[0.04]">
            <div
                className="h-48 bg-cover bg-center"
                style={{
                    backgroundImage: selection.image_url
                        ? `linear-gradient(180deg, rgba(9,11,15,0.05), rgba(9,11,15,0.72)), url(${selection.image_url})`
                        : 'linear-gradient(135deg, #171b24, #2c2417)',
                }}
            />
            <div className="p-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <div className="text-xs tracking-[0.25em] text-[#d6b36a] uppercase">
                            {selection.category}
                        </div>
                        <h2 className="mt-2 text-xl font-semibold">
                            {selection.name}
                        </h2>
                    </div>
                    <StatusPill status={selection.status} />
                </div>
                <p className="mt-3 text-sm leading-6 text-white/60">
                    {selection.description}
                </p>
                {selection.manager_note && (
                    <div className="mt-4 rounded-md bg-black/25 p-3 text-sm text-white/65">
                        {selection.manager_note}
                    </div>
                )}
                {canRespond && (
                    <form onSubmit={submit} className="mt-5 space-y-3">
                        <div className="grid grid-cols-2 gap-2">
                            {(['approved', 'changes_requested'] as const).map(
                                (status) => (
                                    <button
                                        key={status}
                                        type="button"
                                        onClick={() => {
                                            setMode(status);
                                            setData('status', status);
                                        }}
                                        className={`rounded-md border px-3 py-2 text-sm capitalize ${
                                            data.status === status
                                                ? 'border-[#d6b36a] bg-[#d6b36a]/15 text-[#f5dfa6]'
                                                : 'border-white/10 bg-black/20 text-white/65'
                                        }`}
                                    >
                                        {status.replace('_', ' ')}
                                    </button>
                                ),
                            )}
                        </div>
                        <textarea
                            value={data.client_response}
                            onChange={(event) =>
                                setData('client_response', event.target.value)
                            }
                            placeholder="Optional note"
                            className="min-h-24 w-full rounded-md border border-white/10 bg-black/30 px-3 py-2 text-white"
                        />
                        {errors.client_response && (
                            <p className="text-sm text-rose-300">
                                {errors.client_response}
                            </p>
                        )}
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black"
                        >
                            Save response
                        </button>
                    </form>
                )}
            </div>
        </article>
    );
}
