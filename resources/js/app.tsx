import './bootstrap';
import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ToastProvider } from '@/components/ui/Toast';

createInertiaApp({
    title: (title) => (title ? `${title} · MyAlice` : 'MyAlice'),
    resolve: (name) => {
        const pages = import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx');
        return pages[`./Pages/${name}.tsx`]().then((m) => m.default);
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <ToastProvider>
                <App {...props} />
            </ToastProvider>,
        );
    },
    progress: {
        color: '#0d9488',
        showSpinner: false,
    },
});
