import { Head } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Plus } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Switch } from '@/components/ui/Switch';
import { useState } from 'react';
import { cn, money, relativeTime } from '@/lib/utils';

interface Tx {
    id: number;
    type: string;
    amount: number;
    balance_after: number;
    description: string;
    created_at: string;
}
interface Props {
    balance: number;
    currency: string;
    ledger: Tx[];
}

export default function Wallet({ balance, currency, ledger }: Props) {
    const [auto, setAuto] = useState(true);
    const low = balance < 25;

    return (
        <SettingsLayout title="Wallet">
            <Head title="Wallet" />
            <Card className={cn('p-5', low && 'ring-1 ring-warning/40')}>
                <p className="text-[13px] text-secondary">Prepaid balance</p>
                <p className="mt-1 text-3xl font-semibold tracking-tight tnum">{money(balance, currency)}</p>
                {low && <p className="mt-1 text-[13px] text-warning">Balance is low — broadcasts are blocked below your messaging cost.</p>}
                <div className="mt-4 flex flex-wrap gap-2">
                    {[25, 50, 100, 250].map((amt) => (
                        <Button key={amt} variant="secondary" size="sm">{money(amt, currency)}</Button>
                    ))}
                    <Button size="sm"><Plus className="size-4" /> Custom top-up</Button>
                </div>
                <div className="mt-4 flex items-center gap-3 border-t border-default pt-4">
                    <Switch checked={auto} onChange={setAuto} label="Auto-recharge" />
                    <div>
                        <p className="text-[13px] font-medium">Auto-recharge</p>
                        <p className="text-[12px] text-tertiary">Top up {money(50, currency)} when balance falls below {money(20, currency)}.</p>
                    </div>
                </div>
            </Card>

            <h3 className="mb-2.5 mt-6 text-[13px] font-semibold text-secondary">Transaction ledger</h3>
            <Card className="divide-y divide-[var(--border)]">
                {ledger.map((t) => (
                    <div key={t.id} className="flex items-center gap-3 px-4 py-3">
                        <span className={cn('flex size-8 items-center justify-center rounded-full', t.type === 'credit' ? 'bg-success-subtle text-success' : 'bg-surface-2 text-secondary')}>
                            {t.type === 'credit' ? <ArrowDownLeft className="size-4" /> : <ArrowUpRight className="size-4" />}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-[13px] font-medium">{t.description}</p>
                            <p className="text-[12px] text-tertiary">{relativeTime(t.created_at)}</p>
                        </div>
                        <span className={cn('text-sm font-medium tnum', t.type === 'credit' ? 'text-success' : 'text-primary')}>
                            {t.type === 'credit' ? '+' : '−'}{money(t.amount, currency)}
                        </span>
                        <span className="w-20 text-end text-[12px] tnum text-tertiary">{money(t.balance_after, currency)}</span>
                    </div>
                ))}
            </Card>
        </SettingsLayout>
    );
}
