import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { Copy, Download } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input, Textarea } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';

export default function Qr({ phone }: { phone: string }) {
    const { toast } = useToast();
    const [number, setNumber] = useState(phone);
    const [message, setMessage] = useState('Hi! I have a question about my order.');
    const [source, setSource] = useState('packaging');

    const link = useMemo(() => {
        const params = new URLSearchParams();
        if (message) params.set('text', message);
        const utm = source ? `&utm_source=${encodeURIComponent(source)}` : '';
        return `https://wa.me/${number.replace(/\D/g, '')}?${params.toString()}${utm}`;
    }, [number, message, source]);

    return (
        <SettingsLayout title="QR codes & links">
            <Head title="QR & links" />
            <div className="grid gap-5 lg:grid-cols-[1fr_260px]">
                <div className="space-y-4">
                    <Card className="space-y-4 p-5">
                        <Input label="WhatsApp number" value={number} onChange={(e) => setNumber(e.target.value)} hint="Include country code, digits only." />
                        <Textarea label="Pre-filled message" rows={3} value={message} onChange={(e) => setMessage(e.target.value)} />
                        <Input label="Attribution source" value={source} onChange={(e) => setSource(e.target.value)} hint="Flows into reports as the entry-point source." />
                    </Card>

                    <Card className="p-4">
                        <p className="mb-1.5 text-[13px] font-medium">Click-to-chat link</p>
                        <div className="flex items-center gap-2 rounded-[var(--radius-control)] bg-surface-2 p-3">
                            <code className="flex-1 truncate text-[12px] text-secondary">{link}</code>
                            <button onClick={() => { navigator.clipboard?.writeText(link); toast('Link copied', { tone: 'success' }); }} className="text-tertiary hover:text-primary">
                                <Copy className="size-4" />
                            </button>
                        </div>
                    </Card>
                </div>

                <Card className="flex flex-col items-center p-5">
                    <p className="mb-3 text-[12px] font-medium uppercase tracking-wide text-tertiary">QR code</p>
                    <div className="rounded-[var(--radius-card)] bg-white p-4">
                        <QRCodeSVG value={link} size={168} level="M" />
                    </div>
                    <div className="mt-4 flex gap-2">
                        <Button size="sm" variant="secondary"><Download className="size-3.5" /> PNG</Button>
                        <Button size="sm" variant="secondary"><Download className="size-3.5" /> SVG</Button>
                    </div>
                    <p className="mt-3 text-center text-[11px] text-tertiary">Use high contrast and ≥2cm when printed on packaging.</p>
                </Card>
            </div>
        </SettingsLayout>
    );
}
