import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Translation lookup for the active locale. Strings are externalized in
 * lang/<locale>.json and shared via Inertia (A13). Falls back to the key.
 */
export function useTranslations() {
    const { props } = usePage<PageProps>();
    const dict = (props.translations ?? {}) as Record<string, string>;

    const t = (key: string, fallback?: string) => dict[key] ?? fallback ?? key;

    return { t, locale: props.locale };
}
