const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const HOUR_TICKS = [0, 3, 6, 9, 12, 15, 18, 21];

/** Weekday × hour conversation-volume heatmap (staffing signal). */
export function Heatmap({ grid, max }: { grid: number[][]; max: number }) {
    const intensity = (v: number) => (max <= 0 ? 0 : Math.max(v > 0 ? 0.12 : 0, v / max));

    return (
        <div className="overflow-x-auto">
            <div className="min-w-[560px]">
                {/* hour ticks */}
                <div className="mb-1 flex pl-9 text-[10px] text-tertiary">
                    {Array.from({ length: 24 }).map((_, h) => (
                        <div key={h} className="flex-1 text-center">
                            {HOUR_TICKS.includes(h) ? h : ''}
                        </div>
                    ))}
                </div>
                {grid.map((row, d) => (
                    <div key={d} className="flex items-center">
                        <div className="w-9 shrink-0 pr-1 text-end text-[10px] font-medium text-tertiary">{DAYS[d]}</div>
                        <div className="flex flex-1 gap-[2px]">
                            {row.map((v, h) => (
                                <div
                                    key={h}
                                    title={`${DAYS[d]} ${h}:00 — ${v} conversation${v === 1 ? '' : 's'}`}
                                    className="aspect-square flex-1 rounded-[2px]"
                                    style={{
                                        backgroundColor:
                                            v > 0
                                                ? `color-mix(in srgb, var(--accent) ${Math.round(intensity(v) * 100)}%, var(--surface-2))`
                                                : 'var(--surface-2)',
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                ))}
                {/* legend */}
                <div className="mt-2 flex items-center justify-end gap-1.5 pr-1 text-[10px] text-tertiary">
                    <span>Less</span>
                    {[0.15, 0.4, 0.65, 0.9].map((o) => (
                        <span
                            key={o}
                            className="size-2.5 rounded-[2px]"
                            style={{ backgroundColor: `color-mix(in srgb, var(--accent) ${o * 100}%, var(--surface-2))` }}
                        />
                    ))}
                    <span>More</span>
                </div>
            </div>
        </div>
    );
}
