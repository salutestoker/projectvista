import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { PaymentPayload, ProjectPayload } from '@/types/projectvista';
import { Head, useForm } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';

interface PaymentsProps {
    project: ProjectPayload;
}

export default function Payments({ project }: PaymentsProps) {
    return (
        <ProjectVistaShell
            title="Payments"
            eyebrow={project.name}
            role={project.role}
            project={project}
        >
            <Head title={`${project.name} Payments`} />
            <div className="space-y-4">
                {project.payments.map((payment) => (
                    <PaymentRow
                        key={payment.id}
                        project={project}
                        payment={payment}
                    />
                ))}
            </div>
        </ProjectVistaShell>
    );
}

function PaymentRow({
    project,
    payment,
}: {
    project: ProjectPayload;
    payment: PaymentPayload;
}) {
    const { patch, processing } = useForm({});
    const amount = payment.amount
        ? new Intl.NumberFormat('en-US', {
              style: 'currency',
              currency: 'USD',
          }).format(Number(payment.amount))
        : 'Amount TBD';

    return (
        <article className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 className="text-xl font-semibold">{payment.label}</h2>
                    <p className="mt-2 text-sm text-white/60">
                        {payment.description}
                    </p>
                    <div className="mt-3 text-sm text-white/45">
                        Due {payment.due_on ?? 'TBD'}
                    </div>
                </div>
                <div className="text-right">
                    <div className="text-2xl font-semibold">{amount}</div>
                    <div className="mt-2 flex justify-end">
                        <StatusPill status={payment.status} />
                    </div>
                </div>
            </div>
            <div className="mt-5 flex flex-wrap gap-3">
                {payment.payment_url && (
                    <a
                        href={payment.payment_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-2 rounded-md bg-[#d6b36a] px-4 py-2 text-sm font-semibold text-black"
                    >
                        {payment.provider_label ?? 'Open payment link'}
                        <ExternalLink className="h-4 w-4" />
                    </a>
                )}
                {project.permissions.can_edit_project &&
                    payment.status !== 'paid' && (
                        <button
                            type="button"
                            disabled={processing}
                            onClick={() =>
                                patch(
                                    route(
                                        'payment-milestones.complete',
                                        payment.id,
                                    ),
                                    {
                                        preserveScroll: true,
                                    },
                                )
                            }
                            className="rounded-md border border-white/15 px-4 py-2 text-sm font-semibold text-white"
                        >
                            Mark paid
                        </button>
                    )}
            </div>
        </article>
    );
}
