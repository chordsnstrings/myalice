import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { cn, money } from '@/lib/utils';

interface Props {
    subscription: { plan: string; billing_cycle: string; seats: number; status: string; renews_at: string | null };
}

const plans = [
    { id: 'premium', name: 'Premium', monthly: 60, annual: 51, features: ['Unlimited customers', 'Shared inbox', 'Chatbot builder', 'WhatsApp broadcasts'] },
    { id: 'business', name: 'Business', monthly: 180, annual: 153, popular: true, features: ['Everything in Premium', 'Marketing automation', 'WhatsApp catalog & commerce', 'NLP + API & webhooks'] },
    { id: 'enterprise', name: 'Enterprise', monthly: 0, annual: 0, features: ['Everything in Business', 'LLM (White Rabbit)', 'Dedicated success manager', 'Custom invoicing & branding'] },
];

export default function Billing({ subscription }: Props) {
    const [cycle, setCycle] = useState<'monthly' | 'annual'>(subscription.billing_cycle === 'annual' ? 'annual' : 'monthly');

    return (
        <SettingsLayout title="Billing & subscription">
            <Head title="Billing" />
            <Card className="mb-5 flex items-center justify-between p-5">
                <div>
                    <p className="text-[13px] text-secondary">Current plan</p>
                    <p className="text-lg font-semibold capitalize">{subscription.plan}</p>
                </div>
                <Badge tone={subscription.status === 'active' ? 'success' : 'warning'}>{subscription.status}</Badge>
            </Card>

            <div className="mb-4 flex items-center justify-center gap-2">
                <div className="inline-flex rounded-full border border-default bg-surface p-0.5">
                    {(['monthly', 'annual'] as const).map((c) => (
                        <button
                            key={c}
                            onClick={() => setCycle(c)}
                            className={cn('rounded-full px-3 py-1 text-[13px] font-medium capitalize', cycle === c ? 'bg-accent text-accent-contrast' : 'text-secondary')}
                        >
                            {c}{c === 'annual' && ' · save 15%'}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid gap-3 lg:grid-cols-3">
                {plans.map((p) => {
                    const current = p.id === subscription.plan;
                    return (
                        <Card key={p.id} className={cn('flex flex-col p-5', p.popular && 'ring-1 ring-accent')}>
                            <div className="flex items-center justify-between">
                                <p className="font-semibold">{p.name}</p>
                                {p.popular && <Badge tone="accent">Popular</Badge>}
                            </div>
                            <p className="mt-2 text-2xl font-semibold tracking-tight tnum">
                                {p.id === 'enterprise' ? 'Custom' : money(cycle === 'monthly' ? p.monthly : p.annual)}
                                {p.id !== 'enterprise' && <span className="text-[13px] font-normal text-tertiary">/mo</span>}
                            </p>
                            <ul className="mt-4 flex-1 space-y-2">
                                {p.features.map((f) => (
                                    <li key={f} className="flex items-start gap-2 text-[13px] text-secondary">
                                        <Check className="mt-0.5 size-3.5 shrink-0 text-success" /> {f}
                                    </li>
                                ))}
                            </ul>
                            <Button variant={current ? 'secondary' : 'primary'} className="mt-5 w-full" disabled={current}>
                                {current ? 'Current plan' : p.id === 'enterprise' ? 'Contact sales' : 'Upgrade'}
                            </Button>
                        </Card>
                    );
                })}
            </div>
        </SettingsLayout>
    );
}
