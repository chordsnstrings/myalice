import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, RefreshCw, Send, X } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Drawer } from '@/components/ui/Drawer';
import { Input, Textarea, Field } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import type { PageProps } from '@/types';

interface Template {
    id: number;
    name: string;
    category: string;
    language: string;
    approval_status: string;
    quality: string;
    rejection_reason: string | null;
    body: string;
    variable_count: number;
    header_format: string | null;
}

interface Btn {
    [key: string]: string | undefined;
    type: string; // quick_reply | url | phone_number
    text: string;
    value?: string;
}

const statusTone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    approved: 'success',
    pending: 'warning',
    rejected: 'danger',
    paused: 'warning',
    disabled: 'neutral',
    draft: 'neutral',
};

const selectClass =
    'h-9 w-full rounded-[var(--radius-control)] border border-strong bg-surface px-3 text-sm focus:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40';

function countVars(body: string): number {
    const m = body.match(/\{\{\s*(\d+)\s*\}\}/g) ?? [];
    return new Set(m).size;
}

export default function Templates({ templates, waba_connected }: { templates: Template[]; waba_connected: boolean }) {
    const { toast } = useToast();
    const can = usePage<PageProps>().props.auth.can ?? {};
    const [editing, setEditing] = useState<Template | 'new' | null>(null);

    const sync = () =>
        router.post('/templates/sync', {}, { preserveScroll: true, onSuccess: () => toast('Synced from Meta', { tone: 'success' }) });

    const submit = (t: Template) =>
        router.post(`/templates/${t.id}/submit`, {}, { preserveScroll: true, onSuccess: () => toast('Submitted for approval', { tone: 'success' }) });

    return (
        <AppShell title="Templates">
            <Head title="Templates" />
            <Page
                title="Message templates"
                description="WhatsApp HSM templates — Meta-approved messages for broadcasts and the 24h-window fallback."
                actions={
                    can.manage_bots ? (
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={sync} disabled={!waba_connected} title={waba_connected ? '' : 'Connect WhatsApp first'}>
                                <RefreshCw className="size-4" /> Sync
                            </Button>
                            <Button onClick={() => setEditing('new')}><Plus className="size-4" /> New template</Button>
                        </div>
                    ) : undefined
                }
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
                                        {t.variable_count > 0 && <span className="text-[12px] text-tertiary">{t.variable_count} var</span>}
                                    </div>
                                </div>
                                <Badge tone={statusTone[t.approval_status] ?? 'neutral'}>{t.approval_status}</Badge>
                            </div>
                            <p className="mt-3 whitespace-pre-wrap rounded-[var(--radius-control)] bg-surface-2 p-2.5 text-[13px] text-secondary">{t.body}</p>
                            {t.rejection_reason && <p className="mt-2 text-[12px] text-danger">Meta: {t.rejection_reason}</p>}
                            {can.manage_bots && ['draft', 'rejected'].includes(t.approval_status) && (
                                <div className="mt-3 flex gap-2">
                                    <Button size="sm" variant="secondary" onClick={() => setEditing(t)}>Edit</Button>
                                    <Button size="sm" onClick={() => submit(t)}><Send className="size-3.5" /> Submit</Button>
                                </div>
                            )}
                        </Card>
                    ))}
                    {templates.length === 0 && (
                        <p className="text-[13px] text-tertiary">No templates yet. Create one to send broadcasts and out-of-window replies.</p>
                    )}
                </div>

                {editing && <TemplateBuilder template={editing === 'new' ? null : editing} onClose={() => setEditing(null)} toast={toast} />}
            </Page>
        </AppShell>
    );
}

function TemplateBuilder({
    template,
    onClose,
    toast,
}: {
    template: Template | null;
    onClose: () => void;
    toast: ReturnType<typeof useToast>['toast'];
}) {
    const [form, setForm] = useState({
        name: template?.name ?? '',
        category: template?.category ?? 'marketing',
        language: template?.language ?? 'en',
        header_format: template?.header_format ?? 'none',
        header_text: '',
        header_media_url: '',
        body: template?.body ?? '',
        footer: '',
    });
    const [buttons, setButtons] = useState<Btn[]>([]);
    const [samples, setSamples] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const varCount = useMemo(() => countVars(form.body), [form.body]);
    const sampleArr = useMemo(() => Array.from({ length: varCount }, (_, i) => samples[i] ?? ''), [varCount, samples]);

    const set = (k: keyof typeof form, v: string) => setForm({ ...form, [k]: v });

    const save = (alsoSubmit: boolean) => {
        setBusy(true);
        setErrors({});
        const payload = { ...form, buttons, variable_samples: sampleArr, submit: alsoSubmit };
        const opts = {
            preserveScroll: true,
            onSuccess: () => { toast(alsoSubmit ? 'Template submitted' : 'Template saved', { tone: 'success' }); onClose(); },
            onError: (e: Record<string, string>) => setErrors(e),
            onFinish: () => setBusy(false),
        };
        if (template) router.put(`/templates/${template.id}`, payload, opts);
        else router.post('/templates', payload, opts);
    };

    return (
        <Drawer
            open
            onClose={onClose}
            title={template ? 'Edit template' : 'New template'}
            footer={
                <div className="flex gap-2">
                    <Button variant="secondary" loading={busy} onClick={() => save(false)}>Save draft</Button>
                    <Button loading={busy} onClick={() => save(true)}>Save &amp; submit</Button>
                </div>
            }
        >
            <div className="space-y-4">
                <Input label="Name (lowercase, underscores)" error={errors.name} value={form.name}
                    onChange={(e) => set('name', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))} />
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Category">
                        <select className={selectClass} value={form.category} onChange={(e) => set('category', e.target.value)}>
                            <option value="marketing">Marketing</option>
                            <option value="utility">Utility</option>
                            <option value="authentication">Authentication</option>
                        </select>
                    </Field>
                    <Input label="Language" value={form.language} onChange={(e) => set('language', e.target.value)} />
                </div>

                <Field label="Header" hint="Optional. Media headers take a per-recipient image/video/doc.">
                    <select className={selectClass} value={form.header_format} onChange={(e) => set('header_format', e.target.value)}>
                        <option value="none">None</option>
                        <option value="text">Text</option>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                        <option value="document">Document</option>
                    </select>
                </Field>
                {form.header_format === 'text' && (
                    <Input label="Header text" error={errors.header_text} value={form.header_text} onChange={(e) => set('header_text', e.target.value)} />
                )}
                {['image', 'video', 'document'].includes(form.header_format) && (
                    <Input label="Sample media URL" hint="Used as the example for approval." error={errors.header_media_url}
                        value={form.header_media_url} onChange={(e) => set('header_media_url', e.target.value)} />
                )}

                <Textarea label="Body — use {{1}}, {{2}} … for personalization" rows={5} error={errors.body}
                    value={form.body} onChange={(e) => set('body', e.target.value)} />

                {varCount > 0 && (
                    <Field label="Variable samples" hint="Example values Meta uses to review the template.">
                        <div className="space-y-2">
                            {sampleArr.map((v, i) => (
                                <Input key={i} label={`{{${i + 1}}}`} value={v}
                                    onChange={(e) => { const next = [...sampleArr]; next[i] = e.target.value; setSamples(next); }} />
                            ))}
                        </div>
                    </Field>
                )}

                <Input label="Footer (optional)" value={form.footer} onChange={(e) => set('footer', e.target.value)} />

                <Field label="Buttons (up to 3)">
                    <div className="space-y-2">
                        {buttons.map((b, i) => (
                            <div key={i} className="flex items-center gap-2">
                                <select className={selectClass + ' max-w-[8rem]'} value={b.type}
                                    onChange={(e) => setButtons(buttons.map((x, j) => (j === i ? { ...x, type: e.target.value as Btn['type'] } : x)))}>
                                    <option value="quick_reply">Quick reply</option>
                                    <option value="url">URL</option>
                                    <option value="phone_number">Call</option>
                                </select>
                                <input className="h-9 flex-1 rounded-[var(--radius-control)] border border-strong bg-surface px-2 text-sm" placeholder="Label"
                                    value={b.text} onChange={(e) => setButtons(buttons.map((x, j) => (j === i ? { ...x, text: e.target.value } : x)))} />
                                {b.type !== 'quick_reply' && (
                                    <input className="h-9 flex-1 rounded-[var(--radius-control)] border border-strong bg-surface px-2 text-sm"
                                        placeholder={b.type === 'url' ? 'https://…' : '+1…'}
                                        value={b.value ?? ''} onChange={(e) => setButtons(buttons.map((x, j) => (j === i ? { ...x, value: e.target.value } : x)))} />
                                )}
                                <button onClick={() => setButtons(buttons.filter((_, j) => j !== i))} className="text-tertiary hover:text-danger"><X className="size-4" /></button>
                            </div>
                        ))}
                        {buttons.length < 3 && (
                            <Button size="sm" variant="secondary" onClick={() => setButtons([...buttons, { type: 'quick_reply', text: '' }])}>
                                <Plus className="size-3.5" /> Add button
                            </Button>
                        )}
                    </div>
                </Field>
            </div>
        </Drawer>
    );
}
