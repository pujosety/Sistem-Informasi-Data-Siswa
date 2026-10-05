import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

const pwaOptions = {
    registerType: 'prompt',
    injectRegister: null, // we register manually in resources/js/pwa.js
    manifest: {
        // LYFLA — Learning & Your Future, Linked Anywhere.
        //
        // These are literals rather than config('branding.platform.*') because
        // this file is evaluated by Node, not by PHP: there is no Laravel
        // container here to ask. The values are pinned to the same constants as
        // config/branding.php, and a test asserts the two agree — a duplicated
        // literal with nothing checking it is exactly how "SIDA" survived on the
        // home screen while the app was already maroon.
        name: 'LYFLA — Learning & Your Future, Linked Anywhere',
        short_name: 'LYFLA',
        description: 'Modern Education Management Platform — manajemen sekolah, akademik, PPDB, dokumen, dan e-learning.',
        lang: 'id',
        dir: 'ltr',
        start_url: '/dashboard',
        scope: '/',
        display: 'standalone',
        orientation: 'portrait-primary',
        // LYFLA palette: maroon accent, warm neutral splash. background_color is
        // the splash screen behind the installed window, so it stays a light
        // warm neutral rather than taking the brand colour — a full-bleed maroon
        // splash reads as an error state on launch.
        background_color: '#fbf9f8',
        theme_color: '#7A1F32',
        categories: ['education', 'productivity'],
        icons: [
            { src: '/branding/pwa-192x192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
            { src: '/branding/pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
            { src: '/branding/maskable-192x192.png', sizes: '192x192', type: 'image/png', purpose: 'maskable' },
            { src: '/branding/maskable-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
        shortcuts: [
            { name: 'Dashboard', short_name: 'Beranda', url: '/dashboard' },
            { name: 'Dokumen Saya', short_name: 'Dokumen', url: '/siswa/dokumen' },
            { name: 'Laporan', short_name: 'Laporan', url: '/laporan' },
        ],
    },
    workbox: {
        globPatterns: ['**/*.{js,css,woff2,png,svg,ico,webmanifest}'],
        // Runtime caching ONLY for the static asset CDN/style sheets.
        // Never NetworkFirst/NetworkOnly for HTML or API JSON.
        runtimeCaching: [
            {
                urlPattern: ({ request }) => request.destination === 'image',
                handler: 'CacheFirst',
                options: {
                    cacheName: 'sida-images',
                    expiration: { maxEntries: 40, maxAgeSeconds: 60 * 60 * 24 * 30 },
                },
            },
            {
                urlPattern: ({ request }) =>
                    request.destination === 'font' || /\.(?:woff2?|ttf)$/.test(request.url),
                handler: 'CacheFirst',
                options: { cacheName: 'sida-fonts', expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 * 365 } },
            },
        ],
        navigateFallback: null, // don't serve cached HTML for private pages
        navigateFallbackDenylist: [/^\/.*/],
        cleanupOutdatedCaches: true,
        clientsClaim: false,
    },
    devOptions: { enabled: false },
};

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        VitePWA(pwaOptions),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: { host: 'localhost' },
    },
    build: {
        // Long-lived hashed filenames; PWA precache can safely cache them.
        rollupOptions: {
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },
});
