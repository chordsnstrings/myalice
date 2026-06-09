interface Point {
    day: string;
    value: number;
}

/** Dependency-free line chart for trends (e.g. CSAT score over time). */
export function LineChart({ data, max }: { data: Point[]; max?: number }) {
    if (data.length < 2) {
        return <div className="flex h-32 items-center justify-center text-[13px] text-tertiary">Not enough data yet</div>;
    }
    const top = max ?? Math.max(...data.map((d) => d.value), 1);
    const min = Math.min(...data.map((d) => d.value), 0);
    const range = top - min || 1;
    const pts = data
        .map((d, i) => `${(i / (data.length - 1)) * 100},${100 - ((d.value - min) / range) * 92 - 4}`)
        .join(' ');
    return (
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" className="h-32 w-full">
            <polyline
                points={pts}
                fill="none"
                stroke="var(--accent)"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}
