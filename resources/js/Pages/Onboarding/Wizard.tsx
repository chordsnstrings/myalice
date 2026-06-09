import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Check, MessageCircle, Store, Users, Sparkles, ArrowRight } from 'lucide-react';
import { Brand } from '@/components/Brand';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/utils';

const steps = [
    { id: 'welcome', label: 'Welcome', icon: Sparkles },
    { id: 'channel', label: 'Connect a channel', icon: MessageCircle },
    { id: 'store', label: 'Connect your store', icon: Store },
    { id: 'team', label: 'Invite your team', icon: Users },
];

/** First-run onboarding (B1.5): skippable, resumable; each step independent. */
export default function Wizard() {
    const [step, setStep] = useState(0);
    const [done, setDone] = useState<Record<string, boolean>>({});

    const complete = (id: string) => setDone((d) => ({ ...d, [id]: true }));
    const next = () => (step < steps.length - 1 ? setStep(step + 1) : router.visit('/inbox'));

    return (
        <div className="flex min-h-screen flex-col bg-canvas">
            <Head title="Get started" />
            <header className="flex h-14 items-center justify-between border-b border-default bg-surface px-6">
                <Brand />
                <button onClick={() => router.visit('/inbox')} className="text-[13px] font-medium text-secondary hover:text-primary">
                    Do this later
                </button>
            </header>

            <div className="mx-auto flex w-full max-w-4xl flex-1 gap-10 px-6 py-12">
                {/* Stepper */}
                <ol className="hidden w-56 shrink-0 space-y-1 md:block">
                    {steps.map((s, i) => {
                        const Icon = s.icon;
                        const isDone = done[s.id];
                        const isCurrent = i === step;
                        return (
                            <li key={s.id}>
                                <button
                                    onClick={() => setStep(i)}
                                    className={cn(
                                        'flex w-full items-center gap-3 rounded-[var(--radius-control)] px-3 py-2.5 text-start text-[13px] font-medium transition-colors',
                                        isCurrent ? 'bg-accent-subtle text-accent' : 'text-secondary hover:bg-surface-hover',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-6 items-center justify-center rounded-full border',
                                            isDone ? 'border-success bg-success text-white' : isCurrent ? 'border-accent text-accent' : 'border-strong text-tertiary',
                                        )}
                                    >
                                        {isDone ? <Check className="size-3.5" /> : <Icon className="size-3.5" />}
                                    </span>
                                    {s.label}
                                </button>
                            </li>
                        );
                    })}
                </ol>

                {/* Step content */}
                <div className="flex-1">
                    <p className="text-[12px] font-medium uppercase tracking-wide text-tertiary">
                        Step {step + 1} of {steps.length}
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        {step === 0 && 'Welcome to ARKS Messages Platform 👋'}
                        {step === 1 && 'Connect your first channel'}
                        {step === 2 && 'Connect your store'}
                        {step === 3 && 'Invite your team'}
                    </h1>
                    <p className="mt-2 max-w-md text-sm text-secondary">
                        {step === 0 && "Let's get your workspace ready. You can skip any step and finish it later from your inbox checklist."}
                        {step === 1 && 'Bring WhatsApp, Instagram, Messenger and more into one inbox.'}
                        {step === 2 && 'Sync your catalog and orders so agents see full commercial context.'}
                        {step === 3 && 'Add teammates and assign roles. Your plan includes 5 seats.'}
                    </p>

                    <div className="mt-7 space-y-3">
                        {step === 1 &&
                            ['WhatsApp', 'Instagram', 'Messenger', 'Telegram'].map((c) => (
                                <button
                                    key={c}
                                    onClick={() => complete('channel')}
                                    className="flex w-full items-center justify-between rounded-[var(--radius-card)] border border-default bg-surface px-4 py-3 text-sm font-medium transition-colors hover:border-strong"
                                >
                                    {c}
                                    <span className="text-[13px] font-medium text-accent">Connect</span>
                                </button>
                            ))}
                        {step === 2 &&
                            ['Shopify', 'WooCommerce', 'Salla', 'Zid'].map((c) => (
                                <button
                                    key={c}
                                    onClick={() => complete('store')}
                                    className="flex w-full items-center justify-between rounded-[var(--radius-card)] border border-default bg-surface px-4 py-3 text-sm font-medium transition-colors hover:border-strong"
                                >
                                    {c}
                                    <span className="text-[13px] font-medium text-accent">Connect</span>
                                </button>
                            ))}
                        {step === 3 && (
                            <div className="rounded-[var(--radius-card)] border border-default bg-surface p-4">
                                <p className="text-[13px] text-secondary">
                                    Invite teammates by email — they'll get a link to join your workspace.
                                </p>
                                <Button variant="secondary" size="sm" className="mt-3" onClick={() => complete('team')}>
                                    Add teammates
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className="mt-8 flex items-center gap-3">
                        <Button onClick={next}>
                            {step === steps.length - 1 ? 'Go to inbox' : 'Continue'}
                            <ArrowRight className="size-4" />
                        </Button>
                        {step > 0 && step < steps.length - 1 && (
                            <button onClick={next} className="text-[13px] font-medium text-secondary hover:text-primary">
                                Skip this step
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
