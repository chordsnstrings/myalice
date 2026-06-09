/** Tiny inline SVG sparkline (no charting lib). */
export function Sparkline({ data, positive = true }: { data: number[]; positive?: boolean }) {
    if (data.length < 2) return <svg className="h-8 w-full" />;
    const max = Math.max(...data);
    const min = Math.min(...data);
    const range = max - min || 1;
    const pts = data
        .map((v, i) => `${(i / (data.length - 1)) * 100},${28 - ((v - min) / range) * 24 - 2}`)
        .join(' ');
    return (
        <svg viewBox="0 0 100 28" preserveAspectRatio="none" className="h-8 w-full">
            <polyline
                points={pts}
                fill="none"
                stroke={positive ? 'var(--accent)' : 'var(--danger)'}
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}
