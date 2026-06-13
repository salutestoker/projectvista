interface MetricCardProps {
    label: string;
    value: number | string;
    detail: string;
}

export function MetricCard({ label, value, detail }: MetricCardProps) {
    return (
        <div className="rounded-lg border border-white/10 bg-white/[0.04] p-5">
            <div className="text-sm text-white/55">{label}</div>
            <div className="mt-3 text-3xl font-semibold text-white">
                {value}
            </div>
            <div className="mt-2 text-sm text-white/50">{detail}</div>
        </div>
    );
}
