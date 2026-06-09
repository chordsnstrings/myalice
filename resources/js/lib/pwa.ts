/** PWA glue: register the service worker and expose the install prompt. */

type InstallPromptEvent = Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: string }> };

let deferredPrompt: InstallPromptEvent | null = null;
const listeners = new Set<(available: boolean) => void>();

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e as InstallPromptEvent;
        listeners.forEach((l) => l(true));
    });
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        listeners.forEach((l) => l(false));
    });
}

export function registerServiceWorker(): void {
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;
    // Only register over HTTPS or localhost (SW requirement).
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* registration is best-effort */
        });
    });
}

export function canInstall(): boolean {
    return deferredPrompt !== null;
}

export async function promptInstall(): Promise<boolean> {
    if (!deferredPrompt) return false;
    await deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;
    deferredPrompt = null;
    listeners.forEach((l) => l(false));
    return choice.outcome === 'accepted';
}

export function onInstallAvailability(cb: (available: boolean) => void): () => void {
    listeners.add(cb);
    cb(canInstall());
    return () => listeners.delete(cb);
}
