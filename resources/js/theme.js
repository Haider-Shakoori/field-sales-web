const STORAGE_KEY = 'theme';

export function resolveInitialTheme() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(value) {
    document.documentElement.classList.toggle('dark', value === 'dark');
    localStorage.setItem(STORAGE_KEY, value);
}

export function initTheme() {
    applyTheme(resolveInitialTheme());
}

export function initThemeToggle() {
    document.addEventListener('alpine:init', () => {
        Alpine.data('themeToggle', () => ({
            value: resolveInitialTheme(),

            init() {
                this.$watch('value', (value) => applyTheme(value));
            },
        }));
    });
}