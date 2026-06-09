import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Plus, ShoppingCart, CheckCircle2, Truck, TrendingUp, MessageSquareHeart, RefreshCcw, Hand } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Switch } from '@/components/ui/Switch';
import { money } from '@/lib/utils';

interface Rule {
    id: number;
    name: string;
    trigger_type: string;
    status: string;
    sent: number;
    recovered_revenue: number;
}

const icons: Record<string, React.ComponentType<{ className?: string }>> = {
    abandoned_cart: ShoppingCart,
    order_confirmation: CheckCircle2,
    shipping: Truck,
    upsell: TrendingUp,
    feedback: MessageSquareHeart,
    re_engagement: RefreshCcw,
    welcome: Hand,
};

export default function AutomationsIndex({ rules }: { rules: Rule[] }) {
    const [state, setState] = useState<Record<number, boolean>>(
        Object.fromEntries(rules.map((r) => [r.id, r.status === 'active'])),
    );

    return (
        <AppShell title="Automations">
            <Head title="Automations" />
            <Page
                title="Automations"
                description="Event-driven messages fired at key moments in the customer journey."
            >
                <div className="space-y-2.5">
                    {rules.map((r) => {
                        const Icon = icons[r.trigger_type] ?? ShoppingCart;
                        return (
                            <Card key={r.id} className="flex items-center gap-4 p-4">
                                <div className="flex size-10 items-center justify-center rounded-[var(--radius-control)] bg-accent-subtle">
                                    <Icon className="size-5 text-accent" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">{r.name}</p>
                                    <p className="text-[12px] capitalize text-tertiary">{r.trigger_type.replace('_', ' ')}</p>
                                </div>
                                <div className="hidden text-end sm:block">
                                    <p className="text-[12px] text-tertiary">Sent</p>
                                    <p className="text-sm font-medium tnum">{r.sent.toLocaleString()}</p>
                                </div>
                                {r.recovered_revenue > 0 && (
                                    <div className="hidden text-end sm:block">
                                        <p className="text-[12px] text-tertiary">Recovered</p>
                                        <p className="text-sm font-medium text-success tnum">{money(r.recovered_revenue)}</p>
                                    </div>
                                )}
                                <Badge tone={state[r.id] ? 'success' : 'neutral'}>{state[r.id] ? 'Active' : 'Paused'}</Badge>
                                <Switch checked={state[r.id]} onChange={(v) => setState({ ...state, [r.id]: v })} label={`Toggle ${r.name}`} />
                            </Card>
                        );
                    })}
                </div>

                <div className="mt-6">
                    <h3 className="mb-2.5 text-[13px] font-semibold text-secondary">Add from a recipe</h3>
                    <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        {[
                            ['Upsell / re-order', 'upsell', TrendingUp],
                            ['Feedback collection', 'feedback', MessageSquareHeart],
                            ['Re-engagement', 're_engagement', RefreshCcw],
                            ['Welcome message', 'welcome', Hand],
                        ].map(([label, , Icon]) => {
                            const I = Icon as React.ComponentType<{ className?: string }>;
                            return (
                                <button
                                    key={label as string}
                                    className="flex items-center gap-3 rounded-[var(--radius-card)] border border-dashed border-strong bg-surface p-4 text-start transition-colors hover:border-accent"
                                >
                                    <I className="size-5 text-secondary" />
                                    <span className="text-[13px] font-medium">{label as string}</span>
                                    <Plus className="ms-auto size-4 text-tertiary" />
                                </button>
                            );
                        })}
                    </div>
                </div>
            </Page>
        </AppShell>
    );
}
