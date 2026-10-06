import './bootstrap';
import Alpine from 'alpinejs';
import { registerSW } from './pwa.js';

const THEME_KEY = 'lyfla.theme';
const themeOptions = [
    { value: 'light', label: 'Terang' },
    { value: 'dark', label: 'Gelap' },
    { value: 'system', label: 'Sistem' },
];

function systemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function storedTheme() {
    const value = window.localStorage.getItem(THEME_KEY);
    return ['light', 'dark', 'system'].includes(value) ? value : 'system';
}

function applyTheme(preference) {
    const effective = preference === 'system' ? systemTheme() : preference;
    const root = document.documentElement;
    root.dataset.themePreference = preference;
    root.dataset.theme = effective;
    root.style.colorScheme = effective;
    window.localStorage.setItem(THEME_KEY, preference);
}

// The inline head script applies the first paint before CSS is parsed. This
// second call keeps Alpine and the document state synchronized after boot.
if (typeof window !== 'undefined') {
    applyTheme(storedTheme());
}

/**
 * Root Alpine store: sidebar, toasts, theme, and global UI flags.
 * Registered once, available to every view via x-data.
 */
document.addEventListener('alpine:init', () => {
    const stored = localStorage.getItem('sida.sidebar.collapsed');
    const storedMobile = localStorage.getItem('sida.sidebar.mobile');

    window.Alpine.store('theme', {
        preference: storedTheme(),
        effective: document.documentElement.dataset.theme || systemTheme(),
        options: themeOptions,

        setTheme(preference) {
            applyTheme(preference);
            this.preference = preference;
            this.effective = document.documentElement.dataset.theme;
        },

        label() {
            return this.options.find((option) => option.value === this.preference)?.label || 'Sistem';
        },
    });

    Alpine.data('themeSwitcher', () => ({
        open: false,
        options: themeOptions,
        get preference() { return this.$store.theme.preference; },
        get label() { return this.$store.theme.label(); },
        choose(value) {
            this.$store.theme.setTheme(value);
            this.open = false;
        },
    }));

    const prefersColorScheme = window.matchMedia('(prefers-color-scheme: dark)');
    prefersColorScheme.addEventListener?.('change', () => {
        if (window.localStorage.getItem(THEME_KEY) === 'system') {
            applyTheme('system');
            window.Alpine.store('theme').effective = document.documentElement.dataset.theme;
        }
    });

    window.Alpine.store('app', {
        // Desktop: collapsed rail. Mobile: off-canvas drawer.
        sidebarCollapsed: stored === '1',
        mobileNavOpen: storedMobile === '1',
        commandOpen: false,
        // Phone "more" sheet. Kept out of the persisted sidebar state on
        // purpose: it must always open closed.
        moreOpen: false,

        openMore() {
            this.moreOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        closeMore() {
            this.moreOpen = false;

            if (! this.mobileNavOpen) {
                document.body.classList.remove('overflow-hidden');
            }
        },

        toggle() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sida.sidebar.collapsed', this.sidebarCollapsed ? '1' : '0');
        },

        openMobileNav() {
            this.mobileNavOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        closeMobileNav() {
            this.mobileNavOpen = false;

            if (! this.moreOpen) {
                document.body.classList.remove('overflow-hidden');
            }
        },

        toggleCommand() {
            this.commandOpen = !this.commandOpen;
        },
    });

    /**
     * Toast queue. Trigger from any view:  $dispatch('toast', {type:'success', title:'…'})
     */
    window.Alpine.store('toast', {
        items: [],
        nextId: 1,

        push(detail = {}) {
            const id = this.nextId++;
            const item = {
                id,
                type: detail.type || 'info',
                title: detail.title || '',
                message: detail.message || '',
                timeout: detail.timeout ?? 5000,
            };
            this.items.push(item);
            window.setTimeout(() => this.dismiss(id), item.timeout);
        },

        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });
});

window.Alpine = Alpine;
Alpine.start();

/* Surface Laravel's flash messages through the toast queue. */
document.addEventListener('DOMContentLoaded', () => {
    const flashes = JSON.parse(document.getElementById('sida-flashes')?.textContent || '[]');

    flashes.forEach((f) => window.Alpine.store('toast').push(f));

    if ('serviceWorker' in navigator && import.meta.env.PROD) {
        registerSW();
    }
});
