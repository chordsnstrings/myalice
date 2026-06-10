import { useEffect, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Send, FlaskConical } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input, Field } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { money } from '@/lib/utils';

interface Template { id: number; name: string; category: string; language: string; body: string; variable_count: number }
interface Audience { id: number; name: string; size: number }
interface Sender { id: number; type: string; name: string }

interface Props {
    templates: Template[];
    audiences: Audience[];
    senders: Sender[];
    wallet: number;
    contact_fields: string[];
}

const selectClass =
    'h-9 w-full rounded-[var(--radius-control)] border border-strong bg-surface px-3 text-sm focus:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40';

const CHANNELS = [
    { value: 'whatsapp', label: 'WhatsApp', note: 'Template broadcast to opted-in contacts.' },
    { value: 'messenger', label: 'Messenger', note: 'Session-only: contacts active in the last 24h.' },
    { value: 'instagram', label: 'Instagram', note: 'Session-only: contacts active in the last 24h.' },
];

export default function BroadcastCreate({ templates, audiences, senders, wallet, contact_fields }: Props) {
    const { toast } = useToast();
    const [name, setName] = useState('');
    const [channel, setChannel] = useState('whatsapp');
    const [templateId, setTemplateId] = useState<number | null>(null);
    const [audienceId, setAudienceId] = useState<number | null>(null);
    const [varMap, setVarMap] = useState<Record<number, string>>({});
    const [when, setWhen] = useState<'now' | 'later'>('now');
    const [scheduleAt, setScheduleAt] = useState('');
    const [testTo, setTestTo] = useState('');
    const [estimate, setEstimate] = useState<{ recipients: number; cost: number } | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const template = useMemo(() => templates.find((t) => t.id === templateId) ?? null, [templates, templateId]);
    const isWhatsApp = channel === 'whatsapp';

    useEffect(() => {
        let cancelled = false;
        window.axios
            .post('/broadcasts/preview', { channel, audience_id: audienceId, message_template_id: templateId })
            .then(({ data }) => { if (!cancelled) setEstimate(data); })
            .catch(() => { if (!cancelled) setEstimate(null); });
        return () => { cancelled = true; };
    }, [channel, audienceId, templateId]);

    const affordable = !estimate || estimate.cost <= wallet;

    const submit = () => {
        setBusy(true);
        setErrors({});
        router.post('/broadcasts', {
            name,
            channel,
            message_template_id: templateId,
            audience_id: audienceId,
            variable_map: varMap,
            schedule_at: when === 'later' && scheduleAt ? scheduleAt : null,
        }, {
            onError: (e) => setErrors(e),
            onFinish: () => setBusy(false),
        });
    };

    const sendTest = () => {
        if (!templateId || !testTo) return;
        window.axios.post('/broadcasts/test', { channel, message_template_id: templateId, to: testTo })
            .then(() => toast('Test message sent', { tone: 'success' }))
            .catch(() => toast('Test failed — check the number and template', { tone: 'error' }));
    };

    return (
        <AppShell title="New broadcast">
            <Head title="New broadcast" />
            <Page title="New broadcast" description="Reach your audience with compliant, costed sends.">
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="space-y-4 p-5">
                            <Input label="Broadcast name" value={name} error={errors.name} onChange={(e) => setName(e.target.value)} />

                            <Field label="Channel">
                                <div className="grid gap-2 sm:grid-cols-3">
                                    {CHANNELS.map((c) => (
                                        <button key={c.value} type="button" onClick={() => setChannel(c.value)}
                                            className={'rounded-[var(--radius-control)] border p-2.5 text-start text-[12px] ' +
                                                (channel === c.value ? 'border-accent bg-accent-subtle' : 'border-default hover:bg-surface-hover')}>
                                            <span className="block text-[13px] font-medium text-primary">{c.label}</span>
                                            <span className="text-tertiary">{c.note}</span>
                                        </button>
                                    ))}
                                </div>
                            </Field>

                            <Field label={isWhatsApp ? 'Template (approved)' : 'Template (optional)'} error={errors.message_template_id} htmlFor="tpl">
                                <select id="tpl" className={selectClass} value={templateId ?? ''} onChange={(e) => { setTemplateId(e.target.value ? Number(e.target.value) : null); setVarMap({}); }}>
                                    <option value="">Select a template…</option>
                                    {templates.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.language})</option>)}
                                </select>
                            </Field>

                            {template && (
                                <p className="whitespace-pre-wrap rounded-[var(--radius-control)] bg-surface-2 p-2.5 text-[13px] text-secondary">{template.body}</p>
                            )}

                            {template && template.variable_count > 0 && (
                                <Field label="Personalization" hint="Map each variable to a contact field.">
                                    <div className="space-y-2">
                                        {Array.from({ length: template.variable_count }, (_, i) => i + 1).map((n) => (
                                            <div key={n} className="flex items-center gap-2">
                                                <span className="w-12 text-[13px] text-tertiary">{`{{${n}}}`}</span>
                                                <select className={selectClass} value={varMap[n] ?? ''} onChange={(e) => setVarMap({ ...varMap, [n]: e.target.value })}>
                                                    <option value="">—</option>
                                                    {contact_fields.map((f) => <option key={f} value={f}>{f}</option>)}
                                                </select>
                                            </div>
                                        ))}
                                    </div>
                                </Field>
                            )}

                            <Field label="Audience" htmlFor="aud">
                                <select id="aud" className={selectClass} value={audienceId ?? ''} onChange={(e) => setAudienceId(e.target.value ? Number(e.target.value) : null)}>
                                    <option value="">All eligible contacts</option>
                                    {audiences.map((a) => <option key={a.id} value={a.id}>{a.name} (~{a.size})</option>)}
                                </select>
                            </Field>

                            <Field label="When">
                                <div className="flex gap-2">
                                    <button type="button" onClick={() => setWhen('now')} className={'rounded-[var(--radius-control)] border px-3 py-1.5 text-[13px] ' + (when === 'now' ? 'border-accent bg-accent-subtle' : 'border-default')}>Send now</button>
                                    <button type="button" onClick={() => setWhen('later')} className={'rounded-[var(--radius-control)] border px-3 py-1.5 text-[13px] ' + (when === 'later' ? 'border-accent bg-accent-subtle' : 'border-default')}>Schedule</button>
                                </div>
                            </Field>
                            {when === 'later' && (
                                <Input type="datetime-local" label="Send at" error={errors.schedule_at} value={scheduleAt} onChange={(e) => setScheduleAt(e.target.value)} />
                            )}
                        </Card>

                        <Card className="space-y-3 p-5">
                            <p className="text-[13px] font-medium">Test send</p>
                            <div className="flex items-end gap-2">
                                <Input label="To (number / id)" className="flex-1" value={testTo} onChange={(e) => setTestTo(e.target.value)} />
                                <Button variant="secondary" onClick={sendTest} disabled={!templateId || !testTo}><FlaskConical className="size-4" /> Send test</Button>
                            </div>
                        </Card>
                    </div>

                    <Card className="h-fit space-y-3 p-5">
                        <p className="text-[15px] font-semibold">Review</p>
                        {!isWhatsApp && (
                            <div className="rounded-[var(--radius-control)] bg-warning-subtle px-3 py-2 text-[12px] text-warning">
                                {CHANNELS.find((c) => c.value === channel)?.note} Promotional blasts aren't allowed here.
                            </div>
                        )}
                        <Row label="Recipients" value={estimate ? estimate.recipients.toLocaleString() : '—'} />
                        <Row label="Estimated cost" value={estimate ? money(estimate.cost) : '—'} />
                        <Row label="Wallet balance" value={money(wallet)} />
                        {!affordable && <p className="text-[12px] text-danger">Cost exceeds wallet balance — top up to continue.</p>}
                        {errors.credit_cost && <p className="text-[12px] text-danger">{errors.credit_cost}</p>}
                        {errors.audience_id && <p className="text-[12px] text-danger">{errors.audience_id}</p>}
                        {senders.length === 0 && <p className="text-[12px] text-tertiary">Connect a channel in Settings to actually deliver.</p>}
                        <Button className="w-full" loading={busy} disabled={!name || (isWhatsApp && !templateId) || !affordable || (estimate?.recipients ?? 0) === 0} onClick={submit}>
                            <Send className="size-4" /> {when === 'later' ? 'Schedule broadcast' : 'Send broadcast'}
                        </Button>
                        <Badge tone="neutral">Recipients are opted-in &amp; compliant</Badge>
                    </Card>
                </div>
            </Page>
        </AppShell>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between text-[13px]">
            <span className="text-secondary">{label}</span>
            <span className="font-medium tnum">{value}</span>
        </div>
    );
}
