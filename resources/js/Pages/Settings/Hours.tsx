import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Switch } from '@/components/ui/Switch';
import { Button } from '@/components/ui/Button';

const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

export default function Hours({ timezone }: { timezone: string }) {
    const [state, setState] = useState(() =>
        days.map((d, i) => ({ day: d, enabled: i >= 1 && i <= 5, open: '09:00', close: '17:00' })),
    );

    return (
        <SettingsLayout title="Business hours">
            <Head title="Business hours" />
            <p className="mb-4 text-[13px] text-secondary">Times are in <span className="font-medium text-primary">{timezone}</span>. Outside hours, conversations route to your bot or an away message.</p>
            <Card className="divide-y divide-[var(--border)]">
                {state.map((row, i) => (
                    <div key={row.day} className="flex items-center gap-4 px-4 py-3">
                        <Switch
                            checked={row.enabled}
                            onChange={(v) => setState((s) => s.map((r, j) => (j === i ? { ...r, enabled: v } : r)))}
                            label={row.day}
                        />
                        <span className="w-24 text-sm font-medium">{row.day}</span>
                        {row.enabled ? (
                            <div className="flex items-center gap-2 text-sm">
                                <input type="time" defaultValue={row.open} className="h-8 rounded-[var(--radius-control)] border border-strong bg-canvas px-2 outline-none focus:border-accent" />
                                <span className="text-tertiary">to</span>
                                <input type="time" defaultValue={row.close} className="h-8 rounded-[var(--radius-control)] border border-strong bg-canvas px-2 outline-none focus:border-accent" />
                            </div>
                        ) : (
                            <span className="text-[13px] text-tertiary">Closed</span>
                        )}
                    </div>
                ))}
            </Card>
            <div className="mt-4 flex justify-end">
                <Button>Save hours</Button>
            </div>
        </SettingsLayout>
    );
}
