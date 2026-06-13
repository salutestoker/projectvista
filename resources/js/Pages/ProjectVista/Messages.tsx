import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { ProjectPayload } from '@/types/projectvista';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface MessagesProps {
    project: ProjectPayload;
}

export default function Messages({ project }: MessagesProps) {
    const thread = project.threads[0];
    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!thread) {
            return;
        }
        post(route('message-threads.messages.store', thread.id), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <ProjectVistaShell
            title="Messages"
            eyebrow={project.name}
            role={project.role}
            project={project}
        >
            <Head title={`${project.name} Messages`} />
            {!thread ? (
                <div className="rounded-lg border border-white/10 bg-white/[0.04] p-8 text-white/60">
                    No message thread has been started for this project.
                </div>
            ) : (
                <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <section className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
                        <div className="border-b border-white/10 pb-4">
                            <h2 className="text-xl font-semibold">
                                {thread.subject}
                            </h2>
                            <p className="mt-1 text-sm text-white/45">
                                Last update {thread.last_message_at}
                            </p>
                        </div>
                        <div className="mt-5 space-y-4">
                            {thread.messages.map((message) => (
                                <div
                                    key={message.id}
                                    className="rounded-lg bg-black/25 p-4"
                                >
                                    <div className="flex items-center justify-between gap-4 text-sm">
                                        <span className="font-medium">
                                            {message.author}
                                        </span>
                                        <span className="text-white/40">
                                            {message.created_at}
                                        </span>
                                    </div>
                                    <p className="mt-3 text-sm leading-6 text-white/70">
                                        {message.body}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <form
                        onSubmit={submit}
                        className="rounded-lg border border-white/10 bg-white/[0.04] p-5"
                    >
                        <h2 className="text-xl font-semibold">Send Update</h2>
                        <p className="mt-2 text-sm text-white/55">
                            Messages stay in ProjectVista for the demo MVP.
                        </p>
                        <textarea
                            value={data.body}
                            onChange={(event) =>
                                setData('body', event.target.value)
                            }
                            className="mt-5 min-h-40 w-full rounded-md border border-white/10 bg-black/30 px-3 py-2 text-white"
                            placeholder="Write a calm, homeowner-ready update..."
                        />
                        {errors.body && (
                            <p className="mt-2 text-sm text-rose-300">
                                {errors.body}
                            </p>
                        )}
                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-4 w-full rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black"
                        >
                            Send message
                        </button>
                    </form>
                </div>
            )}
        </ProjectVistaShell>
    );
}
