import { cn } from '@/lib/utils';

interface Point {
    day: string;
    value: number;
}

/** Dependency-free bar chart for daily series (e.g. revenue). */
export function BarChart({ data, format, className = 'h-32' }: { data: Point[]; format?: (v: number) => string; className?: string }) {
    const max = Math.max(...data.map((d) => d.value), 1);
    return (
        <div className={cn('flex items-end gap-1', className)}>
            {data.map((d) => (
                <div
                    key={d.day}
                    title={`${d.day}: ${format ? format(d.value) : d.value}`}
                    className="group/bar flex h-full flex-1 items-end"
                >
                    <div
                        className="w-full min-w-[3px] rounded-t-[5px] bg-linear-to-t from-accent/40 to-accent transition-[filter] duration-200 group-hover/bar:brightness-110"
                        style={{ height: `${Math.max((d.value / max) * 100, 1.5)}%` }}
                    />
                </div>
            ))}
        </div>
    );
}
