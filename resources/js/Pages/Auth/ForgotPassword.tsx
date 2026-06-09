import { useState, type FormEvent } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { AuthLayout } from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export default function ForgotPassword({ status }: { status?: string }) {
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        router.post('/forgot-password', { email }, { onFinish: () => setLoading(false) });
    };

    return (
        <AuthLayout title="Reset your password" subtitle="We'll email you a secure link to set a new one.">
            <Head title="Forgot password" />
            {status ? (
                <div className="flex items-start gap-2.5 rounded-[var(--radius-card)] border border-success/30 bg-success-subtle p-3 text-[13px] text-success">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                    <span>{status}</span>
                </div>
            ) : (
                <form onSubmit={submit} className="space-y-4" noValidate>
                    <Input
                        label="Email"
                        type="email"
                        autoFocus
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                    <Button type="submit" loading={loading} className="w-full">
                        Send reset link
                    </Button>
                </form>
            )}
            <p className="mt-6 text-center text-[13px] text-secondary">
                <Link href="/login" className="font-medium text-accent hover:underline">
                    Back to log in
                </Link>
            </p>
        </AuthLayout>
    );
}
