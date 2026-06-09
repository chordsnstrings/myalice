import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Copy, MessageSquare } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';

export default function Widget({ workspace_id }: { workspace_id: number; whatsapp: string | null }) {
    const { toast } = useToast();
    const [greeting, setGreeting] = useState('Hi 👋 How can we help?');
    const snippet = `<script src="https://cdn.arksmessages.com/widget.js" data-workspace="${workspace_id}" async></script>`;

    return (
        <SettingsLayout title="Web chat widget">
            <Head title="Web widget" />
            <div className="grid gap-5 lg:grid-cols-[1fr_280px]">
                <div className="space-y-5">
                    <Card className="p-5">
                        <h3 className="text-sm font-semibold">Appearance</h3>
                        <div className="mt-4 space-y-4">
                            <Input label="Greeting" value={greeting} onChange={(e) => setGreeting(e.target.value)} />
                            <div>
                                <label className="mb-1.5 block text-[13px] font-medium">Accent color</label>
                                <div className="flex gap-2">
                                    {['#0d9488', '#2563eb', '#7c3aed', '#db2777', '#ea580c'].map((c) => (
                                        <button key={c} className="size-7 rounded-full ring-2 ring-transparent hover:ring-strong" style={{ background: c }} aria-label={c} />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-5">
                        <h3 className="text-sm font-semibold">Install snippet</h3>
                        <p className="mt-1 text-[13px] text-secondary">Paste before <code className="text-[12px]">&lt;/body&gt;</code>. Loads async — never blocks your store.</p>
                        <div className="mt-3 flex items-center gap-2 rounded-[var(--radius-control)] bg-surface-2 p-3">
                            <code className="flex-1 truncate text-[12px] text-secondary">{snippet}</code>
                            <button onClick={() => { navigator.clipboard?.writeText(snippet); toast('Snippet copied', { tone: 'success' }); }} className="text-tertiary hover:text-primary">
                                <Copy className="size-4" />
                            </button>
                        </div>
                        <Button variant="secondary" size="sm" className="mt-3">Install on Shopify (one-click)</Button>
                    </Card>
                </div>

                {/* Live preview */}
                <div className="relative">
                    <p className="mb-2 text-[12px] font-medium uppercase tracking-wide text-tertiary">Preview</p>
                    <div className="rounded-[var(--radius-card)] border border-default bg-surface-2 p-4">
                        <div className="overflow-hidden rounded-[var(--radius-card)] border border-default bg-surface shadow-[var(--shadow-sm)]">
                            <div className="flex items-center gap-2 bg-accent px-4 py-3 text-accent-contrast">
                                <MessageSquare className="size-4" />
                                <span className="text-[13px] font-semibold">Acme DTC</span>
                            </div>
                            <div className="space-y-2 p-3">
                                <div className="max-w-[80%] rounded-[var(--radius-card)] border border-default bg-surface px-3 py-2 text-[12px]">{greeting}</div>
                            </div>
                            <div className="border-t border-default p-2">
                                <div className="h-8 rounded-[var(--radius-control)] bg-surface-2" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    );
}
