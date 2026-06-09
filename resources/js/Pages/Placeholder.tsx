import { Head } from '@inertiajs/react';
import * as Icons from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Card } from '@/components/ui/Card';
import { FirstUseEmpty } from '@/components/ui/States';
import { Button } from '@/components/ui/Button';

interface Props {
    title: string;
    icon: string;
    description: string;
    cta: string;
    spec: string;
}

export default function Placeholder({ title, icon, description, cta, spec }: Props) {
    const Icon = (Icons as unknown as Record<string, Icons.LucideIcon>)[icon] ?? Icons.Sparkles;
    return (
        <AppShell title={title}>
            <Head title={title} />
            <div className="h-full overflow-y-auto p-6">
                <div className="mx-auto max-w-2xl">
                    <Card>
                        <FirstUseEmpty
                            icon={Icon}
                            title={`${title} lives here`}
                            description={description}
                            action={<Button>{cta}</Button>}
                        />
                    </Card>
                    <p className="mt-3 text-center text-[12px] text-tertiary">Spec: {spec}</p>
                </div>
            </div>
        </AppShell>
    );
}
