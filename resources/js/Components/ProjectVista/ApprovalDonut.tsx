import { ChartContainer } from '@/Components/ui/chart';
import { cn } from '@/lib/utils';
import { Cell, Pie, PieChart } from 'recharts';

export interface DonutSegment {
    label: string;
    value: number;
    color: string;
}

interface ApprovalDonutProps {
    data: DonutSegment[];
    total: number;
    className?: string;
}

export function ApprovalDonut({ data, total, className }: ApprovalDonutProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-5 sm:flex-row sm:items-center',
                className,
            )}
        >
            <div
                className="relative size-36 shrink-0"
                data-testid="approval-donut"
            >
                <ChartContainer
                    config={{
                        approvals: {
                            label: 'Approvals',
                            color: 'var(--pv-gold)',
                        },
                    }}
                    className="!aspect-square size-full"
                >
                    <PieChart margin={{ top: 0, right: 0, bottom: 0, left: 0 }}>
                        <Pie
                            data={data}
                            dataKey="value"
                            innerRadius={42}
                            outerRadius={66}
                            paddingAngle={0}
                            stroke="none"
                            cx="50%"
                            cy="50%"
                        >
                            {data.map((segment) => (
                                <Cell
                                    key={segment.label}
                                    fill={segment.color}
                                />
                            ))}
                        </Pie>
                    </PieChart>
                </ChartContainer>
                <div
                    className="pointer-events-none absolute inset-0 grid place-items-center text-3xl font-semibold"
                    data-testid="approval-donut-total"
                >
                    {total}
                </div>
            </div>
            <div className="flex min-w-0 flex-col gap-3">
                {data.map((segment) => (
                    <div
                        key={segment.label}
                        className="flex items-center gap-3 text-base sm:text-sm lg:text-base"
                    >
                        <span
                            className="size-2.5 shrink-0 rounded-full"
                            style={{ backgroundColor: segment.color }}
                        />
                        <span>
                            {segment.value} {segment.label}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
