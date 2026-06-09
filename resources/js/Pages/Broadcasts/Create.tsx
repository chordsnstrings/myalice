import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, AlertTriangle, Wallet, Lock } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';
import { cn, money } from '@/lib/utils';

interface Template {
    id: number;
    name: string;
    category: string;
    approval_status: string;
    quality: string;
    body: string;
}
interface Audience {
    id: number;
    name: string;
    size: number;
}
interface Props {
    templates: Template[];
    audiences: Audience[];
    wallet: number;
    total_contacts: number;
    opted_out: number;
    price_per_message: number;
}

const steps = ['Template', 'Audience', 'Schedule', 'Review & cost'];

export default function BroadcastCreate({ templates, audiences, wallet, total_contacts, opted_out, price_per_message }: Props) {
    const { toast } = useToast();
    const [step, setStep] = useState(0);
    const [templateId, setTemplateId] = useState<number | null>(null);
    const [audienceId, setAudienceId] = useState<number | null>(null);
    const [when, setWhen] = useState<'now' | 'later'>('now');

    const recipients = useMemo(() => {
        const a = audiences.find((x) => x.id === audienceId);
        const base = a ? a.size : total_contacts;
        return Math.max(0, base - opted_out);
    }, [audienceId, audiences, total_contacts, opted_out]);

    const cost = recipients * price_per_message;
    const insufficient = cost > wallet;
    const template = templates.find((t) => t.id === templateId);

    const canNext =
        (step === 0 && templateId !== null) ||
        (step === 1 && (audienceId !== null || true)) ||
        step === 2 ||
        step === 3;

    return (
        <AppShell title="New broadcast">
            <Head title="New broadcast" />
            <div className="h-full overflow-y-auto">
                <div className="mx-auto max-w-3xl px-6 py-6">
                    {/* Stepper */}
                    <ol className="mb-6 flex items-center gap-2">
                        {steps.map((s, i) => (
                            <li key={s} className="flex flex-1 items-center gap-2">
                                <span
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full border text-[12px] font-semibold',
                                        i < step ? 'border-success bg-success text-white' : i === step ? 'border-accent text-accent' : 'border-strong text-tertiary',
                                    )}
                                >
                                    {i < step ? <Check className="size-3.5" /> : i + 1}
                                </span>
                                <span className={cn('text-[13px] font-medium', i === step ? 'text-primary' : 'text-tertiary')}>{s}</span>
                                {i < steps.length - 1 && <span className="h-px flex-1 bg-default" />}
                            </li>
                        ))}
                    </ol>

                    {/* Step 1: Template */}
                    {step === 0 && (
                        <div className="space-y-2">
                            {templates.map((t) => {
                                const selectable = t.approval_status === 'approved';
                                return (
                                    <button
                                        key={t.id}
                                        disabled={!selectable}
                                        onClick={() => setTemplateId(t.id)}
                                        className={cn(
                                            'flex w-full items-start gap-3 rounded-[var(--radius-card)] border bg-surface p-4 text-start transition-colors',
                                            templateId === t.id ? 'border-accent ring-1 ring-accent' : 'border-default hover:border-strong',
                                            !selectable && 'cursor-not-allowed opacity-60',
                                        )}
                                    >
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-medium">{t.name}</span>
                                                <Badge tone="neutral">{t.category}</Badge>
                                            </div>
                                            <p className="mt-1 text-[13px] text-secondary">{t.body}</p>
                                        </div>
                                        {t.approval_status === 'approved' && <Badge tone="success">Approved</Badge>}
                                        {t.approval_status === 'pending' && <Badge tone="warning">Pending</Badge>}
                                        {t.approval_status === 'rejected' && <Badge tone="danger">Rejected</Badge>}
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {/* Step 2: Audience */}
                    {step === 1 && (
                        <div className="space-y-2">
                            <button
                                onClick={() => setAudienceId(null)}
                                className={cn(
                                    'flex w-full items-center justify-between rounded-[var(--radius-card)] border bg-surface p-4 text-start',
                                    audienceId === null ? 'border-accent ring-1 ring-accent' : 'border-default hover:border-strong',
                                )}
                            >
                                <div>
                                    <p className="text-sm font-medium">All contacts</p>
                                    <p className="text-[13px] text-secondary">Everyone who opted in</p>
                                </div>
                                <span className="text-sm font-medium tnum">{total_contacts.toLocaleString()}</span>
                            </button>
                            {audiences.map((a) => (
                                <button
                                    key={a.id}
                                    onClick={() => setAudienceId(a.id)}
                                    className={cn(
                                        'flex w-full items-center justify-between rounded-[var(--radius-card)] border bg-surface p-4 text-start',
                                        audienceId === a.id ? 'border-accent ring-1 ring-accent' : 'border-default hover:border-strong',
                                    )}
                                >
                                    <p className="text-sm font-medium">{a.name}</p>
                                    <span className="text-sm font-medium tnum">{a.size.toLocaleString()}</span>
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Step 3: Schedule */}
                    {step === 2 && (
                        <div className="space-y-2">
                            {(['now', 'later'] as const).map((w) => (
                                <button
                                    key={w}
                                    onClick={() => setWhen(w)}
                                    className={cn(
                                        'flex w-full items-center justify-between rounded-[var(--radius-card)] border bg-surface p-4 text-start',
                                        when === w ? 'border-accent ring-1 ring-accent' : 'border-default hover:border-strong',
                                    )}
                                >
                                    <p className="text-sm font-medium">{w === 'now' ? 'Send now' : 'Schedule for later'}</p>
                                    {when === w && <Check className="size-4 text-accent" />}
                                </button>
                            ))}
                            {when === 'later' && (
                                <input
                                    type="datetime-local"
                                    className="mt-1 h-9 rounded-[var(--radius-control)] border border-strong bg-surface px-3 text-sm outline-none focus:border-accent"
                                />
                            )}
                            <p className="pt-1 text-[12px] text-tertiary">Times use your workspace timezone.</p>
                        </div>
                    )}

                    {/* Step 4: Review & cost — the pre-flight gate */}
                    {step === 3 && (
                        <Card className="p-5">
                            <h3 className="text-sm font-semibold">Review &amp; cost</h3>
                            <p className="mt-1 text-[13px] text-secondary">Confirm before any credits are spent.</p>

                            <div className="mt-4 space-y-2.5 text-sm">
                                <Row label="Template" value={template?.name ?? '—'} />
                                <Row label="Targeted" value={(recipients + opted_out).toLocaleString()} />
                                <Row label="Opted-out / invalid excluded" value={`− ${opted_out}`} muted />
                                <div className="my-2 h-px bg-default" />
                                <Row label="Will receive" value={recipients.toLocaleString()} strong />
                                <Row label="Estimated cost" value={money(cost)} strong />
                                <Row label="Wallet balance" value={money(wallet)} />
                            </div>

                            {insufficient ? (
                                <div className="mt-4 flex items-center gap-2 rounded-[var(--radius-control)] border border-danger/30 bg-danger-subtle px-3 py-2.5 text-[13px] text-danger">
                                    <AlertTriangle className="size-4 shrink-0" />
                                    <span className="flex-1">Need {money(cost - wallet)} more to send this.</span>
                                    <Button size="sm" variant="secondary" onClick={() => router.visit('/settings/wallet')}>
                                        <Wallet className="size-3.5" /> Top up
                                    </Button>
                                </div>
                            ) : (
                                <p className="mt-4 text-[12px] text-tertiary">
                                    Recipients have opted in. This send is compliant with WhatsApp's Commerce Policy.
                                </p>
                            )}
                        </Card>
                    )}

                    {/* Footer */}
                    <div className="mt-6 flex items-center justify-between">
                        <Button variant="ghost" onClick={() => (step === 0 ? router.visit('/broadcasts') : setStep(step - 1))}>
                            <ChevronLeft className="size-4" /> Back
                        </Button>
                        {step < steps.length - 1 ? (
                            <Button disabled={!canNext} onClick={() => setStep(step + 1)}>
                                Continue <ChevronRight className="size-4" />
                            </Button>
                        ) : (
                            <Button
                                disabled={insufficient}
                                onClick={() => {
                                    toast(`Broadcast scheduled to ${recipients.toLocaleString()} contacts`, { tone: 'success' });
                                    router.visit('/broadcasts');
                                }}
                            >
                                {insufficient && <Lock className="size-4" />}
                                Send to {recipients.toLocaleString()} · {money(cost)}
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </AppShell>
    );
}

function Row({ label, value, strong, muted }: { label: string; value: string; strong?: boolean; muted?: boolean }) {
    return (
        <div className="flex justify-between">
            <span className={cn(muted ? 'text-tertiary' : 'text-secondary')}>{label}</span>
            <span className={cn('tnum', strong ? 'font-semibold text-primary' : muted ? 'text-tertiary' : 'font-medium')}>{value}</span>
        </div>
    );
}
