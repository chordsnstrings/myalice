import { type ReactNode } from 'react';
import { MessageSquare, ShoppingBag, Zap } from 'lucide-react';
import { Brand } from '@/components/Brand';

/** Quiet split screen — form left, one-line proof right; collapses to form-only (B1.1). */
export function AuthLayout({
    title,
    subtitle,
    children,
}: {
    title: string;
    subtitle: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-screen bg-canvas">
            <div className="flex w-full flex-col px-6 py-8 lg:w-[460px] lg:shrink-0 lg:px-14">
                <Brand />

                <div className="flex flex-1 flex-col justify-center">
                    <div className="mx-auto w-full max-w-[340px] py-10">
                        <h1 className="text-2xl font-semibold tracking-tight text-primary">{title}</h1>
                        <p className="mt-1.5 text-sm text-secondary">{subtitle}</p>
                        <div className="mt-7">{children}</div>
                    </div>
                </div>
            </div>

            {/* Proof panel (hidden on small screens) */}
            <div className="relative hidden flex-1 overflow-hidden border-s border-default bg-surface lg:block">
                <div className="absolute inset-0 bg-gradient-to-br from-accent-subtle via-transparent to-transparent" />
                <div className="relative flex h-full flex-col justify-center px-16">
                    <p className="max-w-md text-xl font-medium leading-relaxed text-primary">
                        One inbox where conversations become orders — across WhatsApp, Instagram,
                        Messenger and the web.
                    </p>
                    <div className="mt-10 space-y-5">
                        {[
                            { icon: MessageSquare, title: 'Unified inbox', body: 'Every channel, one thread, full context.' },
                            { icon: ShoppingBag, title: 'Chat-to-order', body: 'Sell and create store orders inside the chat.' },
                            { icon: Zap, title: 'Automation & AI', body: 'Recover carts, answer FAQs, run campaigns.' },
                        ].map((f) => (
                            <div key={f.title} className="flex items-start gap-3.5">
                                <div className="flex size-9 items-center justify-center rounded-[var(--radius-card)] border border-default bg-surface">
                                    <f.icon className="size-[18px] text-accent" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-primary">{f.title}</p>
                                    <p className="text-[13px] text-secondary">{f.body}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
