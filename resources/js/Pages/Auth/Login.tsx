import { useState, type FormEvent } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { AuthLayout } from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export default function Login() {
    const [show, setShow] = useState(false);
    const [loading, setLoading] = useState(false);
    const [form, setForm] = useState({ email: '', password: '', remember: true });
    const [errors, setErrors] = useState<{ email?: string; password?: string }>({});

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const next: typeof errors = {};
        if (!form.email) next.email = 'Enter your email';
        if (!form.password) next.password = 'Enter your password';
        setErrors(next);
        if (Object.keys(next).length) return;
        setLoading(true);
        router.post('/login', form, {
            onError: (e) => setErrors(e),
            onFinish: () => setLoading(false),
        });
    };

    return (
        <AuthLayout title="Welcome back" subtitle="Log in to your ARKS Messages Platform workspace.">
            <Head title="Log in" />
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Input
                    label="Email"
                    type="email"
                    autoComplete="email"
                    autoFocus
                    value={form.email}
                    error={errors.email}
                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                />
                <div className="relative">
                    <Input
                        label="Password"
                        type={show ? 'text' : 'password'}
                        autoComplete="current-password"
                        value={form.password}
                        error={errors.password}
                        onChange={(e) => setForm({ ...form, password: e.target.value })}
                    />
                    <button
                        type="button"
                        onClick={() => setShow((s) => !s)}
                        aria-label={show ? 'Hide password' : 'Show password'}
                        className="absolute end-3 top-[34px] text-tertiary hover:text-secondary"
                    >
                        {show ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                    </button>
                </div>

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-[13px] text-secondary">
                        <input
                            type="checkbox"
                            checked={form.remember}
                            onChange={(e) => setForm({ ...form, remember: e.target.checked })}
                            className="size-4 rounded border-strong accent-[var(--accent)]"
                        />
                        Remember me
                    </label>
                    <Link href="/forgot-password" className="text-[13px] font-medium text-accent hover:underline">
                        Forgot password?
                    </Link>
                </div>

                <Button type="submit" loading={loading} className="w-full">
                    Log in
                </Button>
            </form>

            <p className="mt-6 text-center text-[13px] text-secondary">
                Don't have an account?{' '}
                <Link href="/register" className="font-medium text-accent hover:underline">
                    Start free trial
                </Link>
            </p>
        </AuthLayout>
    );
}
