import { Head, router } from '@inertiajs/react';
import { Plus, Bot, MoreHorizontal } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';

interface Bot {
    id: number;
    name: string;
    channel_scope: string;
    status: string;
    version: number;
}

const tone: Record<string, 'success' | 'warning' | 'neutral'> = {
    live: 'success',
    paused: 'warning',
    draft: 'neutral',
};

export default function ChatbotsIndex({ bots }: { bots: Bot[] }) {
    return (
        <AppShell title="Chatbots">
            <Head title="Chatbots" />
            <Page title="Chatbots" description="Automate FAQs, lead capture and guided selling across channels." actions={<Button><Plus className="size-4" /> New chatbot</Button>}>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {bots.map((b) => (
                        <Card key={b.id} className="p-4">
                            <div className="flex items-start justify-between">
                                <div className="flex size-10 items-center justify-center rounded-[var(--radius-control)] bg-accent-subtle">
                                    <Bot className="size-5 text-accent" />
                                </div>
                                <button className="text-tertiary hover:text-primary"><MoreHorizontal className="size-4" /></button>
                            </div>
                            <p className="mt-3 text-sm font-medium">{b.name}</p>
                            <p className="text-[12px] capitalize text-tertiary">All channels · v{b.version}</p>
                            <div className="mt-3 flex items-center justify-between">
                                <Badge tone={tone[b.status] ?? 'neutral'}>{b.status}</Badge>
                                <Button size="sm" variant="secondary" onClick={() => router.visit(`/chatbots/${b.id}/edit`)}>Edit</Button>
                            </div>
                        </Card>
                    ))}

                    <button className="flex min-h-[160px] flex-col items-center justify-center gap-2 rounded-[var(--radius-card)] border border-dashed border-strong text-secondary transition-colors hover:border-accent hover:text-accent">
                        <Plus className="size-5" />
                        <span className="text-[13px] font-medium">New from template</span>
                    </button>
                </div>
            </Page>
        </AppShell>
    );
}
