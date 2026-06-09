import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Switch } from '@/components/ui/Switch';
import { Avatar } from '@/components/ui/Avatar';
import { useTheme } from '@/hooks/useTheme';
import type { PageProps } from '@/types';

export default function Profile() {
    const { props } = usePage<PageProps>();
    const user = props.auth.user;
    const { theme, toggle } = useTheme();
    const [compact, setCompact] = useState(false);
    const [notif, setNotif] = useState({ assigned: true, mentions: true, sla: true, sound: false });

    return (
        <SettingsLayout title="Profile & notifications">
            <Head title="Profile" />
            <Card className="p-5">
                <div className="flex items-center gap-4">
                    <Avatar name={user?.name ?? 'You'} size="lg" />
                    <Button variant="secondary" size="sm">Change photo</Button>
                </div>
                <div className="mt-5 space-y-4">
                    <Input label="Name" defaultValue={user?.name} />
                    <Input label="Email" type="email" defaultValue={user?.email} />
                </div>
                <div className="mt-5 flex justify-end">
                    <Button>Save</Button>
                </div>
            </Card>

            <h3 className="mb-2.5 mt-6 text-[13px] font-semibold text-secondary">Preferences</h3>
            <Card className="divide-y divide-[var(--border)]">
                <PrefRow label="Dark mode" desc="Match your environment." checked={theme === 'dark'} onChange={toggle} />
                <PrefRow label="Compact density" desc="Tighter rows for high-volume work." checked={compact} onChange={setCompact} />
            </Card>

            <h3 className="mb-2.5 mt-6 text-[13px] font-semibold text-secondary">Notifications</h3>
            <Card className="divide-y divide-[var(--border)]">
                <PrefRow label="Assigned to me" desc="When a conversation is assigned to you." checked={notif.assigned} onChange={(v) => setNotif({ ...notif, assigned: v })} />
                <PrefRow label="Mentions" desc="When a teammate @mentions you." checked={notif.mentions} onChange={(v) => setNotif({ ...notif, mentions: v })} />
                <PrefRow label="SLA & wallet alerts" desc="Breaches and low balance." checked={notif.sla} onChange={(v) => setNotif({ ...notif, sla: v })} />
                <PrefRow label="New-message sound" desc="A soft tone, rate-limited." checked={notif.sound} onChange={(v) => setNotif({ ...notif, sound: v })} />
            </Card>
        </SettingsLayout>
    );
}

function PrefRow({ label, desc, checked, onChange }: { label: string; desc: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <div className="flex items-center gap-3 px-4 py-3">
            <div className="flex-1">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-[12px] text-tertiary">{desc}</p>
            </div>
            <Switch checked={checked} onChange={onChange} label={label} />
        </div>
    );
}
