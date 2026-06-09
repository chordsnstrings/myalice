import { Head } from '@inertiajs/react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';

interface Props {
    workspace: { name: string; locale: string; timezone: string; currency: string };
}

export default function Workspace({ workspace }: Props) {
    return (
        <SettingsLayout title="Workspace">
            <Head title="Workspace settings" />
            <Card className="p-5">
                <div className="space-y-4">
                    <Input label="Brand name" defaultValue={workspace.name} />
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Timezone" defaultValue={workspace.timezone} hint="Drives scheduling, hours & reports." />
                        <Input label="Currency" defaultValue={workspace.currency} />
                    </div>
                    <Input label="Default language" defaultValue={workspace.locale === 'en' ? 'English' : workspace.locale} />
                </div>
                <div className="mt-5 flex justify-end">
                    <Button>Save changes</Button>
                </div>
            </Card>
        </SettingsLayout>
    );
}
