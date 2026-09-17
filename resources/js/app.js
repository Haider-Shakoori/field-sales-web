import Alpine from 'alpinejs';
import { initTheme, initThemeToggle } from './theme';

const SIDEBAR_KEY = 'sidebar-collapsed';

initTheme();
initThemeToggle();

document.addEventListener('alpine:init', () => {
    Alpine.store('shell', {
        collapsed: localStorage.getItem(SIDEBAR_KEY) === '1',

        mobileOpen: false,

        toggleCollapsed() {
            this.collapsed = !this.collapsed;
            localStorage.setItem(SIDEBAR_KEY, this.collapsed ? '1' : '0');
            document.documentElement.classList.toggle('sidebar-collapsed', this.collapsed);
        },

        openMobile() {
            this.mobileOpen = true;
            document.documentElement.classList.add('nav-open');
        },

        closeMobile() {
            this.mobileOpen = false;
            document.documentElement.classList.remove('nav-open');
        },
    });
});

window.Alpine = Alpine;

Alpine.start();
