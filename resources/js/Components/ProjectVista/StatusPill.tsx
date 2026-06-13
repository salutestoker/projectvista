interface StatusPillProps {
    status: string;
}

const styles: Record<string, string> = {
    active: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-200',
    approved: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-200',
    paid: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-200',
    completed: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-200',
    in_progress: 'border-sky-400/40 bg-sky-400/10 text-sky-200',
    due: 'border-amber-400/50 bg-amber-400/10 text-amber-100',
    pending: 'border-amber-400/50 bg-amber-400/10 text-amber-100',
    waiting_client: 'border-amber-400/50 bg-amber-400/10 text-amber-100',
    blocked: 'border-rose-400/40 bg-rose-400/10 text-rose-200',
    changes_requested: 'border-rose-400/40 bg-rose-400/10 text-rose-200',
    scheduled: 'border-white/15 bg-white/5 text-white/70',
    upcoming: 'border-white/15 bg-white/5 text-white/70',
};

export function StatusPill({ status }: StatusPillProps) {
    const label = status.replaceAll('_', ' ');

    return (
        <span
            className={`inline-flex rounded-full border px-3 py-1 text-xs font-medium tracking-wide capitalize ${styles[status] ?? 'border-white/15 bg-white/5 text-white/70'}`}
        >
            {label}
        </span>
    );
}
