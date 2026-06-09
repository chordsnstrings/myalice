import { useMemo, useState, type FormEvent } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AuthLayout } from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { cn } from '@/lib/utils';

function strength(pw: string) {
    let s = 0;
    if (pw.length >= 8) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s;
}

export default function Register() {
    const [loading, setLoading] = useState(false);
    const [form, setForm] = useState({ name: '', workspace: '', email: '', password: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const score = useMemo(() => strength(form.password), [form.password]);
    const labels = ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        router.post('/register', form, {
            onError: (e) => setErrors(e),
            onFinish: () => setLoading(false),
        });
    };

    return (
        <AuthLayout title="Create your workspace" subtitle="Start your 14-day free trial. No card required.">
            <Head title="Sign up" />
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Input
                    label="Your name"
                    autoFocus
                    value={form.name}
                    error={errors.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                />
                <Input
                    label="Workspace name"
                    value={form.workspace}
                    error={errors.workspace}
                    hint="Your brand or company name."
                    onChange={(e) => setForm({ ...form, workspace: e.target.value })}
                />
                <Input
                    label="Work email"
                    type="email"
                    autoComplete="email"
                    value={form.email}
                    error={errors.email}
                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                />
                <div>
                    <Input
                        label="Password"
                        type="password"
                        autoComplete="new-password"
                        value={form.password}
                        error={errors.password}
                        onChange={(e) => setForm({ ...form, password: e.target.value })}
                    />
                    {form.password && (
                        <div className="mt-2 flex items-center gap-2">
                            <div className="flex flex-1 gap-1">
                                {[0, 1, 2, 3].map((i) => (
                                    <span
                                        key={i}
                                        className={cn(
                                            'h-1 flex-1 rounded-full transition-colors',
                                            i < score
                                                ? score <= 1
                                                    ? 'bg-danger'
                                                    : score <= 2
                                                      ? 'bg-warning'
                                                      : 'bg-success'
                                                : 'bg-surface-2',
                                        )}
                                    />
                                ))}
                            </div>
                            <span className="text-[11px] text-tertiary">{labels[score]}</span>
                        </div>
                    )}
                </div>

                <Button type="submit" loading={loading} className="w-full">
                    Start free trial
                </Button>
                <p className="text-center text-[12px] text-tertiary">
                    By continuing you agree to our{' '}
                    <a href="#" className="underline hover:text-secondary">Terms</a> and{' '}
                    <a href="#" className="underline hover:text-secondary">Privacy Policy</a>.
                </p>
            </form>

            <p className="mt-6 text-center text-[13px] text-secondary">
                Already have an account?{' '}
                <Link href="/login" className="font-medium text-accent hover:underline">
                    Log in
                </Link>
            </p>
        </AuthLayout>
    );
}
