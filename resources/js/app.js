import Alpine from 'alpinejs';
import { initTheme, initThemeToggle } from './theme';

initTheme();
initThemeToggle();

document.addEventListener('alpine:init', () => {
    Alpine.store('shell', { collapsed: false });
});

window.Alpine = Alpine;

Alpine.start();