import { useState, type FormEvent } from 'react';
import { Head, router } from '@inertiajs/react';
import { AuthLayout } from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const [loading, setLoading] = useState(false);
    const [form, setForm] = useState({ password: '', password_confirmation: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        router.post(
            '/reset-password',
            { token, email, ...form },
            { onError: (e) => setErrors(e), onFinish: () => setLoading(false) },
        );
    };

    return (
        <AuthLayout title="Set a new password" subtitle="Choose a strong password you don't use elsewhere.">
            <Head title="Reset password" />
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Input label="Email" type="email" value={email} disabled />
                <Input
                    label="New password"
                    type="password"
                    autoFocus
                    error={errors.password}
                    value={form.password}
                    onChange={(e) => setForm({ ...form, password: e.target.value })}
                />
                <Input
                    label="Confirm password"
                    type="password"
                    value={form.password_confirmation}
                    onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
                />
                <Button type="submit" loading={loading} className="w-full">
                    Reset password
                </Button>
            </form>
        </AuthLayout>
    );
}
