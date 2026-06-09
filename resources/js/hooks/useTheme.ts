import { useCallback, useEffect, useState } from 'react';

type Theme = 'light' | 'dark';

/** Persisted light/dark theme; respects OS default until the user chooses (A12). */
export function useTheme() {
    const [theme, setTheme] = useState<Theme>(() =>
        typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
            ? 'dark'
            : 'light',
    );

    const apply = useCallback((next: Theme) => {
        document.documentElement.classList.toggle('dark', next === 'dark');
        try {
            localStorage.setItem('myalice-theme', next);
        } catch {
            /* storage may be unavailable */
        }
        setTheme(next);
    }, []);

    const toggle = useCallback(() => apply(theme === 'dark' ? 'light' : 'dark'), [theme, apply]);

    useEffect(() => {
        setTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    }, []);

    return { theme, toggle };
}
