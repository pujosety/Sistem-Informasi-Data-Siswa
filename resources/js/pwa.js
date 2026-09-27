/**
 * Service worker registration.
 *
 * Deliberately conservative: only static build assets are precached by the
 * Workbox config in vite.config.js. Authenticated HTML/JSON is never cached,
 * so student data cannot leak between users on a shared device.
 */
export function registerSW() {
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                // Surface an update without forcing a disruptive reload.
                registration.addEventListener('updatefound', () => {
                    const worker = registration.installing;
                    if (!worker) return;

                    worker.addEventListener('statechange', () => {
                        if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                            window.Alpine?.store('toast')?.push({
                                type: 'info',
                                title: 'Pembaruan tersedia',
                                message: 'Muat ulang halaman untuk memakai versi terbaru.',
                                timeout: 12000,
                            });
                        }
                    });
                });
            })
            .catch(() => {
                // Offline support is a progressive enhancement; never break the app.
            });
    });
}
