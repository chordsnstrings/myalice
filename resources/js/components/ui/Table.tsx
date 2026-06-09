import { type ReactNode } from 'react';
import { cn } from '@/lib/utils';

export interface Column<T> {
    key: string;
    header: string;
    align?: 'start' | 'end';
    render: (row: T) => ReactNode;
}

/** Minimal data table — sticky header, hover rows, tabular numerals (A9). */
export function Table<T extends { id: number | string }>({
    columns,
    rows,
    onRowClick,
    empty,
}: {
    columns: Column<T>[];
    rows: T[];
    onRowClick?: (row: T) => void;
    empty?: ReactNode;
}) {
    if (rows.length === 0 && empty) {
        return <div className="rounded-[var(--radius-card)] border border-default bg-surface">{empty}</div>;
    }

    return (
        <div className="overflow-x-auto rounded-[var(--radius-card)] border border-default bg-surface">
            <table className="w-full min-w-[560px] text-sm">
                <thead>
                    <tr className="border-b border-default">
                        {columns.map((c) => (
                            <th
                                key={c.key}
                                className={cn(
                                    'px-4 py-2.5 text-[12px] font-medium uppercase tracking-wide text-tertiary',
                                    c.align === 'end' ? 'text-end' : 'text-start',
                                )}
                            >
                                {c.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            onClick={() => onRowClick?.(row)}
                            className={cn(
                                'border-b border-default last:border-0 transition-colors',
                                onRowClick && 'cursor-pointer hover:bg-surface-hover',
                            )}
                        >
                            {columns.map((c) => (
                                <td
                                    key={c.key}
                                    className={cn('px-4 py-3', c.align === 'end' ? 'text-end tnum' : 'text-start')}
                                >
                                    {c.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
