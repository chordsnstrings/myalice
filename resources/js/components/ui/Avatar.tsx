import { cn, initials } from '@/lib/utils';
import type { Channel } from '@/types';

const channelColor: Record<Channel, string> = {
    whatsapp: 'bg-ch-whatsapp',
    instagram: 'bg-ch-instagram',
    messenger: 'bg-ch-messenger',
    telegram: 'bg-ch-telegram',
    line: 'bg-ch-line',
    viber: 'bg-ch-viber',
    web: 'bg-ch-web',
};

const sizes = {
    sm: 'size-7 text-[11px]',
    md: 'size-9 text-[13px]',
    lg: 'size-11 text-sm',
};

interface AvatarProps {
    name: string;
    src?: string | null;
    size?: keyof typeof sizes;
    channel?: Channel;
    presence?: boolean;
    className?: string;
}

export function Avatar({ name, src, size = 'md', channel, presence, className }: AvatarProps) {
    return (
        <div className={cn('relative shrink-0', className)}>
            <div
                className={cn(
                    'flex items-center justify-center rounded-full font-semibold',
                    'bg-surface-2 text-secondary overflow-hidden',
                    sizes[size],
                )}
            >
                {src ? (
                    <img src={src} alt={name} className="size-full object-cover" />
                ) : (
                    initials(name)
                )}
            </div>
            {channel && (
                <span
                    className={cn(
                        'absolute -bottom-0.5 -end-0.5 size-3 rounded-full ring-2 ring-surface',
                        channelColor[channel],
                    )}
                    aria-label={channel}
                    title={channel}
                />
            )}
            {presence && !channel && (
                <span className="absolute -bottom-0.5 -end-0.5 size-2.5 rounded-full bg-success ring-2 ring-surface" />
            )}
        </div>
    );
}

export function ChannelDot({ channel, className }: { channel: Channel; className?: string }) {
    return (
        <span
            className={cn('inline-block size-2 rounded-full', channelColor[channel], className)}
            aria-label={channel}
            title={channel}
        />
    );
}
