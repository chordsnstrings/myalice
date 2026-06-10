import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Lock, Send, Sparkles, Star, Trash2 } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Drawer } from '@/components/ui/Drawer';
import { Input, Textarea, Field } from '@/components/ui/Input';
import { Switch } from '@/components/ui/Switch';
import { useToast } from '@/components/ui/Toast';

interface Preset {
    key: string;
    type: string;
    name: string;
    model: string;
    base_url: string | null;
    custom: boolean;
}

interface Provider {
    id: number;
    type: string;
    name: string;
    model: string | null;
    base_url: string | null;
    status: string;
    is_default: boolean;
}

interface Opt {
    value: string;
    label: string;
    description?: string;
}

interface Guardrails {
    max_messages_per_conversation: number;
    order_total_cap: number | null;
    engage_new_conversations: boolean;
    handoff_keywords: string[];
}

interface AgentProfile {
    name: string;
    enabled: boolean;
    mode: string;
    goal: string;
    tone: string;
    methodology: string;
    business_profile: string | null;
    custom_instructions: string | null;
    ai_provider_id: number | null;
    guardrails: Guardrails;
}

interface Meta {
    tones: Opt[];
    methodologies: Opt[];
    goals: Opt[];
    modes: Opt[];
}

interface Props {
    providers: Provider[];
    presets: Preset[];
    agent: AgentProfile;
    meta: Meta;
    llm_unlocked: boolean;
}

const selectClass =
    'h-9 w-full rounded-[var(--radius-control)] border border-strong bg-surface px-3 text-sm text-primary ' +
    'focus:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40';

export default function AiAgents({ providers, presets, agent, meta, llm_unlocked }: Props) {
    const { toast } = useToast();
    const [connecting, setConnecting] = useState<Preset | null>(null);

    return (
        <SettingsLayout title="AI agent">
            <Head title="AI agent" />
            <p className="mb-5 text-[13px] text-secondary">
                Connect one or more models, then tune how your AI assistant sells. It engages new conversations
                automatically and backs off the moment a teammate replies.
            </p>

            <Section title="Models" subtitle="Pick a provider. The default is used first; others are fallbacks.">
                {providers.length > 0 && (
                    <div className="mb-3 space-y-2">
                        {providers.map((p) => (
                            <ProviderRow key={p.id} provider={p} toast={toast} />
                        ))}
                    </div>
                )}
                <div className="grid gap-2.5 sm:grid-cols-2">
                    {presets.map((preset) => {
                        const locked = preset.custom && !llm_unlocked;
                        return (
                            <Card key={preset.key} className="flex items-center gap-3 p-3.5">
                                <Sparkles className="size-4 shrink-0 text-accent" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-[13px] font-medium">{preset.name}</p>
                                    <p className="truncate text-[11px] text-tertiary">{preset.model}</p>
                                </div>
                                {locked ? (
                                    <Badge tone="neutral" className="gap-1">
                                        <Lock className="size-3" /> Enterprise
                                    </Badge>
                                ) : (
                                    <Button size="sm" variant="secondary" onClick={() => setConnecting(preset)}>
                                        Connect
                                    </Button>
                                )}
                            </Card>
                        );
                    })}
                </div>
            </Section>

            <AgentForm agent={agent} meta={meta} providers={providers} toast={toast} />

            <Playground hasProvider={providers.length > 0} />

            {connecting && (
                <ConnectDrawer preset={connecting} onClose={() => setConnecting(null)} toast={toast} />
            )}
        </SettingsLayout>
    );
}

function Section({ title, subtitle, children }: { title: string; subtitle?: string; children: React.ReactNode }) {
    return (
        <section className="mb-8">
            <h3 className="text-[15px] font-semibold">{title}</h3>
            {subtitle && <p className="mb-3 mt-0.5 text-[12px] text-tertiary">{subtitle}</p>}
            <div className={subtitle ? '' : 'mt-3'}>{children}</div>
        </section>
    );
}

function ProviderRow({ provider, toast }: { provider: Provider; toast: ReturnType<typeof useToast>['toast'] }) {
    const setDefault = () =>
        router.put(`/settings/ai-agents/providers/${provider.id}/default`, {}, {
            preserveScroll: true,
            onSuccess: () => toast('Default model updated', { tone: 'success' }),
        });
    const disconnect = () =>
        router.delete(`/settings/ai-agents/providers/${provider.id}`, {
            preserveScroll: true,
            onSuccess: () => toast('Provider disconnected', { tone: 'success' }),
        });

    return (
        <div className="flex items-center gap-3 rounded-[var(--radius-card)] border border-default bg-surface px-3.5 py-2.5">
            <Check className="size-4 shrink-0 text-success" />
            <div className="min-w-0 flex-1">
                <p className="truncate text-[13px] font-medium">
                    {provider.name}
                    {provider.is_default && (
                        <Badge tone="success" className="ms-2">
                            Default
                        </Badge>
                    )}
                </p>
                <p className="truncate text-[11px] text-tertiary">
                    {[provider.model, provider.base_url].filter(Boolean).join(' · ')}
                </p>
            </div>
            {!provider.is_default && (
                <Button size="sm" variant="ghost" onClick={setDefault} title="Make default">
                    <Star className="size-4" />
                </Button>
            )}
            <Button size="sm" variant="ghost" onClick={disconnect} title="Disconnect">
                <Trash2 className="size-4 text-danger" />
            </Button>
        </div>
    );
}

function ConnectDrawer({
    preset,
    onClose,
    toast,
}: {
    preset: Preset;
    onClose: () => void;
    toast: ReturnType<typeof useToast>['toast'];
}) {
    const [busy, setBusy] = useState(false);
    const [form, setForm] = useState<Record<string, string>>({ model: preset.model, base_url: preset.base_url ?? '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const showBase = preset.type === 'openai_compatible';

    const connect = () => {
        setBusy(true);
        setErrors({});
        router.post(
            '/settings/ai-agents/providers',
            { preset: preset.key, api_key: form.api_key ?? '', model: form.model, base_url: form.base_url },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast(`${preset.name} connected`, { tone: 'success' });
                    onClose();
                },
                onError: (e) => setErrors(e),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Drawer
            open
            onClose={onClose}
            title={`Connect ${preset.name}`}
            footer={
                <Button loading={busy} onClick={connect}>
                    Test &amp; connect
                </Button>
            }
        >
            <div className="space-y-4">
                <p className="text-[13px] text-secondary">
                    We send one tiny test message to verify the key before saving. Keys are stored encrypted and never
                    shown again.
                </p>
                <Input
                    label="API key"
                    type="password"
                    error={errors.api_key}
                    value={form.api_key ?? ''}
                    onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                />
                <Input
                    label="Model"
                    hint="Override the default model id if you like."
                    error={errors.model}
                    value={form.model ?? ''}
                    onChange={(e) => setForm({ ...form, model: e.target.value })}
                />
                {showBase && (
                    <Input
                        label="Base URL"
                        hint="OpenAI-compatible endpoint (self-hosted Ollama/vLLM or an aggregator)."
                        error={errors.base_url}
                        value={form.base_url ?? ''}
                        onChange={(e) => setForm({ ...form, base_url: e.target.value })}
                    />
                )}
                {errors.preset && <p className="text-[12px] text-danger">{errors.preset}</p>}
            </div>
        </Drawer>
    );
}

function AgentForm({
    agent,
    meta,
    providers,
    toast,
}: {
    agent: AgentProfile;
    meta: Meta;
    providers: Provider[];
    toast: ReturnType<typeof useToast>['toast'];
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: agent.name,
        enabled: agent.enabled,
        mode: agent.mode,
        goal: agent.goal,
        tone: agent.tone,
        methodology: agent.methodology,
        business_profile: agent.business_profile ?? '',
        custom_instructions: agent.custom_instructions ?? '',
        ai_provider_id: agent.ai_provider_id,
        guardrails: {
            max_messages_per_conversation: agent.guardrails.max_messages_per_conversation,
            order_total_cap: agent.guardrails.order_total_cap,
            engage_new_conversations: agent.guardrails.engage_new_conversations,
            handoff_keywords: agent.guardrails.handoff_keywords,
        },
    });

    const setGuard = <K extends keyof Guardrails>(key: K, value: Guardrails[K]) =>
        setData('guardrails', { ...data.guardrails, [key]: value });

    const submit = () =>
        put('/settings/ai-agents/agent', {
            preserveScroll: true,
            onSuccess: () => toast('AI agent saved', { tone: 'success' }),
        });

    return (
        <Section title="Agent" subtitle="How the assistant talks and what it's allowed to do.">
            <Card className="space-y-5 p-5">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-[13px] font-medium">Enabled</p>
                        <p className="text-[12px] text-tertiary">Turn the assistant on or off entirely.</p>
                    </div>
                    <Switch checked={data.enabled} onChange={(v) => setData('enabled', v)} />
                </div>

                <Input
                    label="Agent name"
                    error={errors.name}
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />

                <Field label="Autonomy mode" error={errors.mode}>
                    <div className="space-y-1.5">
                        {meta.modes.map((m) => (
                            <label
                                key={m.value}
                                className="flex cursor-pointer items-start gap-2.5 rounded-[var(--radius-control)] border border-default px-3 py-2 hover:bg-surface-hover"
                            >
                                <input
                                    type="radio"
                                    name="mode"
                                    className="mt-0.5 accent-[var(--color-accent)]"
                                    checked={data.mode === m.value}
                                    onChange={() => setData('mode', m.value)}
                                />
                                <span className="text-[12px] text-secondary">{m.label}</span>
                            </label>
                        ))}
                    </div>
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Goal" htmlFor="goal">
                        <select id="goal" className={selectClass} value={data.goal} onChange={(e) => setData('goal', e.target.value)}>
                            {meta.goals.map((g) => (
                                <option key={g.value} value={g.value}>
                                    {g.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Tone" htmlFor="tone">
                        <select id="tone" className={selectClass} value={data.tone} onChange={(e) => setData('tone', e.target.value)}>
                            {meta.tones.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <Field
                    label="Sales methodology"
                    htmlFor="methodology"
                    hint={meta.methodologies.find((m) => m.value === data.methodology)?.description}
                >
                    <select
                        id="methodology"
                        className={selectClass}
                        value={data.methodology}
                        onChange={(e) => setData('methodology', e.target.value)}
                    >
                        {meta.methodologies.map((m) => (
                            <option key={m.value} value={m.value}>
                                {m.label}
                            </option>
                        ))}
                    </select>
                </Field>

                {providers.length > 1 && (
                    <Field label="Preferred model" htmlFor="provider" hint="Leave on Default to use your fallback chain.">
                        <select
                            id="provider"
                            className={selectClass}
                            value={data.ai_provider_id ?? ''}
                            onChange={(e) => setData('ai_provider_id', e.target.value ? Number(e.target.value) : null)}
                        >
                            <option value="">Default (workspace)</option>
                            {providers.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                )}

                <Textarea
                    label="Business profile"
                    rows={4}
                    error={errors.business_profile}
                    placeholder="What you sell, who you serve, shipping/returns, what makes you different…"
                    value={data.business_profile}
                    onChange={(e) => setData('business_profile', e.target.value)}
                />

                <Textarea
                    label="Custom instructions"
                    rows={3}
                    placeholder="Anything specific — promos to mention, things never to say, etc."
                    value={data.custom_instructions}
                    onChange={(e) => setData('custom_instructions', e.target.value)}
                />

                <div className="rounded-[var(--radius-card)] border border-default bg-canvas p-4">
                    <p className="mb-3 text-[12px] font-semibold uppercase tracking-wide text-tertiary">Guardrails</p>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-[13px] font-medium">Engage new conversations</p>
                                <p className="text-[12px] text-tertiary">Reply to brand-new chats automatically.</p>
                            </div>
                            <Switch
                                checked={data.guardrails.engage_new_conversations}
                                onChange={(v) => setGuard('engage_new_conversations', v)}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input
                                label="Max messages before handoff"
                                type="number"
                                min={1}
                                value={String(data.guardrails.max_messages_per_conversation)}
                                onChange={(e) => setGuard('max_messages_per_conversation', Number(e.target.value))}
                            />
                            <Input
                                label="Auto-order cap (blank = no orders auto-approved)"
                                type="number"
                                min={0}
                                value={data.guardrails.order_total_cap === null ? '' : String(data.guardrails.order_total_cap)}
                                onChange={(e) =>
                                    setGuard('order_total_cap', e.target.value === '' ? null : Number(e.target.value))
                                }
                            />
                        </div>
                        <Input
                            label="Handoff keywords"
                            hint="Comma-separated. Any of these instantly hands off to a human."
                            value={data.guardrails.handoff_keywords.join(', ')}
                            onChange={(e) =>
                                setGuard(
                                    'handoff_keywords',
                                    e.target.value.split(',').map((s) => s.trim()).filter(Boolean),
                                )
                            }
                        />
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button loading={processing} onClick={submit}>
                        Save agent
                    </Button>
                </div>
            </Card>
        </Section>
    );
}

interface ChatTurn {
    role: 'user' | 'assistant';
    content: string;
}

function Playground({ hasProvider }: { hasProvider: boolean }) {
    const [turns, setTurns] = useState<ChatTurn[]>([]);
    const [input, setInput] = useState('');
    const [tools, setTools] = useState<{ name: string; arguments: Record<string, unknown> }[]>([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const send = async () => {
        if (!input.trim() || busy) return;
        const next: ChatTurn[] = [...turns, { role: 'user', content: input.trim() }];
        setTurns(next);
        setInput('');
        setBusy(true);
        setError(null);
        setTools([]);
        try {
            const { data } = await window.axios.post('/settings/ai-agents/playground', { messages: next });
            setTurns([...next, { role: 'assistant', content: data.text || '(no reply)' }]);
            setTools(data.tool_calls ?? []);
        } catch (e: unknown) {
            const msg =
                (e as { response?: { data?: { error?: string } } })?.response?.data?.error ??
                'The model call failed.';
            setError(msg);
            setTurns(next);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Section title="Playground" subtitle="Try the agent live. Nothing here is saved or sent to a customer.">
            <Card className="p-4">
                {!hasProvider && (
                    <div className="mb-3 rounded-[var(--radius-control)] bg-warning-subtle px-3 py-2 text-[13px] text-warning">
                        Connect a model above to use the playground.
                    </div>
                )}
                <div className="mb-3 max-h-72 space-y-2 overflow-y-auto">
                    {turns.length === 0 && (
                        <p className="py-6 text-center text-[13px] text-tertiary">
                            Send a message as if you were a customer.
                        </p>
                    )}
                    {turns.map((t, i) => (
                        <div key={i} className={t.role === 'user' ? 'flex justify-end' : 'flex justify-start'}>
                            <div
                                className={
                                    'max-w-[80%] rounded-[var(--radius-card)] px-3 py-2 text-[13px] ' +
                                    (t.role === 'user'
                                        ? 'bg-accent text-accent-contrast'
                                        : 'bg-surface-2 text-primary')
                                }
                            >
                                {t.content}
                            </div>
                        </div>
                    ))}
                    {tools.length > 0 && (
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            {tools.map((tool, i) => (
                                <Badge key={i} tone="accent">
                                    would call: {tool.name}({Object.keys(tool.arguments).join(', ')})
                                </Badge>
                            ))}
                        </div>
                    )}
                    {error && <p className="text-[12px] text-danger">{error}</p>}
                </div>
                <div className="flex items-center gap-2">
                    <input
                        className="h-9 flex-1 rounded-[var(--radius-control)] border border-strong bg-surface px-3 text-sm focus:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
                        placeholder="Type a customer message…"
                        value={input}
                        disabled={!hasProvider || busy}
                        onChange={(e) => setInput(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && send()}
                    />
                    <Button loading={busy} disabled={!hasProvider} onClick={send}>
                        <Send className="size-4" />
                    </Button>
                </div>
            </Card>
        </Section>
    );
}
