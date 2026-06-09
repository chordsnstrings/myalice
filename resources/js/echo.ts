import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Real-time client (A10.7). Initializes only when a Pusher key is configured;
 * otherwise the app degrades gracefully to no live updates (BLOCKERS → BLK-2).
 */
const key = import.meta.env.VITE_PUSHER_APP_KEY as string | undefined;

let echo: Echo<'pusher'> | null = null;

if (key) {
    window.Pusher = Pusher;
    echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster: (import.meta.env.VITE_PUSHER_APP_CLUSTER as string) ?? 'mt1',
        forceTLS: true,
    });
}

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'pusher'> | null;
    }
}

window.Echo = echo;

export { echo };
