import { Head } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
}
interface Props {
    members: Member[];
    subscription: { seats: number };
}

export default function Team({ members, subscription }: Props) {
    return (
        <SettingsLayout title="Team & roles">
            <Head title="Team" />
            <div className="mb-4 flex items-center justify-between">
                <p className="text-[13px] text-secondary">
                    <span className="font-medium text-primary">{members.length}</span> of {subscription.seats} seats used
                </p>
                <Button size="sm"><UserPlus className="size-4" /> Invite member</Button>
            </div>
            <Card className="divide-y divide-[var(--border)]">
                {members.map((m) => (
                    <div key={m.id} className="flex items-center gap-3 px-4 py-3">
                        <Avatar name={m.name} size="sm" />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium">{m.name}</p>
                            <p className="text-[12px] text-tertiary">{m.email}</p>
                        </div>
                        <Badge tone={m.role === 'owner' ? 'accent' : 'neutral'}>{m.role}</Badge>
                        <Badge tone="success">{m.status}</Badge>
                    </div>
                ))}
            </Card>
        </SettingsLayout>
    );
}
