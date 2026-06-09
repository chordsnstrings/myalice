import { Tooltip } from '@/components/ui/Tooltip';

interface Point {
    day: string;
    value: number;
}

/** Dependency-free bar chart for daily series (e.g. revenue). */
export function BarChart({ data, format }: { data: Point[]; format?: (v: number) => string }) {
    const max = Math.max(...data.map((d) => d.value), 1);
    return (
        <div className="flex h-32 items-end gap-1">
            {data.map((d) => (
                <Tooltip key={d.day} label={`${d.day}: ${format ? format(d.value) : d.value}`}>
                    <div
                        className="w-full min-w-[3px] flex-1 rounded-t bg-accent/80 transition-colors hover:bg-accent"
                        style={{ height: `${Math.max((d.value / max) * 100, 1)}%` }}
                    />
                </Tooltip>
            ))}
        </div>
    );
}
