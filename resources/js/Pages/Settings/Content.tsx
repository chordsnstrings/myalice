import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Tabs } from '@/components/ui/Tabs';

interface Props {
    quick_replies: { id: number; shortcut: string; body: string }[];
    tags: { id: number; name: string; color: string }[];
}

const toneMap: Record<string, 'neutral' | 'accent' | 'info' | 'warning' | 'success' | 'danger'> = {
    neutral: 'neutral', accent: 'accent', info: 'info', warning: 'warning', success: 'success', danger: 'danger',
};

export default function Content({ quick_replies, tags }: Props) {
    const [tab, setTab] = useState('replies');

    return (
        <SettingsLayout title="Quick replies & tags">
            <Head title="Quick replies & tags" />
            <Tabs
                tabs={[
                    { id: 'replies', label: 'Quick replies', count: quick_replies.length },
                    { id: 'tags', label: 'Tags', count: tags.length },
                ]}
                active={tab}
                onChange={setTab}
            />
            <div className="mt-4">
                {tab === 'replies' ? (
                    <>
                        <div className="mb-3 flex justify-end">
                            <Button size="sm"><Plus className="size-4" /> New reply</Button>
                        </div>
                        <Card className="divide-y divide-[var(--border)]">
                            {quick_replies.map((q) => (
                                <div key={q.id} className="px-4 py-3">
                                    <code className="rounded bg-surface-2 px-1.5 py-0.5 text-[12px] font-medium text-accent">{q.shortcut}</code>
                                    <p className="mt-1 text-[13px] text-secondary">{q.body}</p>
                                </div>
                            ))}
                        </Card>
                    </>
                ) : (
                    <>
                        <div className="mb-3 flex justify-end">
                            <Button size="sm"><Plus className="size-4" /> New tag</Button>
                        </div>
                        <Card className="p-4">
                            <div className="flex flex-wrap gap-2">
                                {tags.map((t) => (
                                    <Badge key={t.id} tone={toneMap[t.color] ?? 'neutral'}>{t.name}</Badge>
                                ))}
                            </div>
                        </Card>
                    </>
                )}
            </div>
        </SettingsLayout>
    );
}
