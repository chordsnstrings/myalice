import { WifiOff } from 'lucide-react';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

/** Persistent offline banner; reads continue from cache, writes queue (A10.6). */
export function OfflineBanner() {
    const online = useOnlineStatus();
    if (online) return null;

    return (
        <div
            role="status"
            className="flex items-center justify-center gap-2 bg-warning px-4 py-1.5 text-[12px] font-medium text-white"
        >
            <WifiOff className="size-3.5" />
            You're offline — we'll send queued messages when you reconnect.
        </div>
    );
}
