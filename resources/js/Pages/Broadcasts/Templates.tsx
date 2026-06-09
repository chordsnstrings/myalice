import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';

interface Template {
    id: number;
    name: string;
    category: string;
    language: string;
    approval_status: string;
    quality: string;
    rejection_reason: string | null;
    body: string;
}

const statusTone: Record<string, 'success' | 'warning' | 'danger'> = {
    approved: 'success',
    pending: 'warning',
    rejected: 'danger',
};

export default function Templates({ templates }: { templates: Template[] }) {
    return (
        <AppShell title="Templates">
            <Head title="Templates" />
            <Page
                title="Message templates"
                description="WhatsApp HSM templates — Meta-approved messages for broadcasts and automations."
                actions={<Button><Plus className="size-4" /> New template</Button>}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    {templates.map((t) => (
                        <Card key={t.id} className="p-4">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="text-sm font-medium">{t.name}</p>
                                    <div className="mt-1 flex items-center gap-1.5">
                                        <Badge tone="neutral">{t.category}</Badge>
                                        <span className="text-[12px] uppercase text-tertiary">{t.language}</span>
                                    </div>
                                </div>
                                <Badge tone={statusTone[t.approval_status] ?? 'neutral'}>{t.approval_status}</Badge>
                            </div>
                            <p className="mt-3 rounded-[var(--radius-control)] bg-surface-2 p-2.5 text-[13px] text-secondary">{t.body}</p>
                            {t.rejection_reason && (
                                <p className="mt-2 text-[12px] text-danger">Meta: {t.rejection_reason}</p>
                            )}
                            {t.approval_status === 'rejected' && (
                                <Button size="sm" variant="secondary" className="mt-3" onClick={() => router.reload()}>
                                    Edit &amp; resubmit
                                </Button>
                            )}
                        </Card>
                    ))}
                </div>
            </Page>
        </AppShell>
    );
}
