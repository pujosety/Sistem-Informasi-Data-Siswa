import './bootstrap';
import Alpine from 'alpinejs';
import { registerSW } from './pwa.js';

/**
 * Root Alpine store: sidebar, toasts, and global UI flags.
 * Registered once, available to every view via x-data.
 */
document.addEventListener('alpine:init', () => {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const stored = localStorage.getItem('sida.sidebar.collapsed');
    const storedMobile = localStorage.getItem('sida.sidebar.mobile');

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
