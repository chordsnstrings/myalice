import { Head } from '@inertiajs/react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ChannelDot } from '@/components/ui/Avatar';
import type { Channel as Ch } from '@/types';

interface Row {
    type: Ch;
    name: string | null;
    status: string;
}

const labels: Record<string, string> = {
    whatsapp: 'WhatsApp',
    instagram: 'Instagram',
    messenger: 'Messenger',
    telegram: 'Telegram',
    line: 'Line',
    viber: 'Viber',
    web: 'Web widget',
};

export default function Channels({ channels }: { channels: Row[] }) {
    return (
        <SettingsLayout title="Channels">
            <Head title="Channels" />
            <div className="grid gap-3 sm:grid-cols-2">
                {channels.map((c) => {
                    const connected = c.status === 'connected';
                    const action = c.status === 'action_needed';
                    return (
                        <Card key={c.type} className="p-4">
                            <div className="flex items-center gap-2.5">
                                <ChannelDot channel={c.type} className="size-3" />
                                <span className="text-sm font-medium">{labels[c.type]}</span>
                                {connected && <Badge tone="success" className="ms-auto">Connected</Badge>}
                                {action && <Badge tone="warning" className="ms-auto">Action needed</Badge>}
                                {!connected && !action && <Badge tone="neutral" className="ms-auto">Not connected</Badge>}
                            </div>
                            <p className="mt-2 text-[12px] text-tertiary">{c.name ?? 'Connect to start receiving messages.'}</p>
                            <Button size="sm" variant={connected ? 'secondary' : 'primary'} className="mt-3 w-full">
                                {connected ? 'Manage' : action ? 'Reconnect' : 'Connect'}
                            </Button>
                        </Card>
                    );
                })}
                <Card className="p-4 opacity-60">
                    <div className="flex items-center gap-2.5">
                        <span className="size-3 rounded-full bg-strong" />
                        <span className="text-sm font-medium">Email</span>
                        <Badge tone="neutral" className="ms-auto">Coming soon</Badge>
                    </div>
                    <p className="mt-2 text-[12px] text-tertiary">An email channel is on the roadmap.</p>
                </Card>
            </div>
        </SettingsLayout>
    );
}
