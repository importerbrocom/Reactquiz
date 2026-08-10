import { usePwaStore } from '@/store/pwa.store';

/**
 * Register the service worker and set up update detection.
 * Called once from main.tsx after render.
 */
export function registerServiceWorker(): void {
  if (!('serviceWorker' in navigator)) return;

  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/service-worker.js', {
        scope: '/',
      });

      // Check for updates
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing;
        if (!newWorker) return;

        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            // New version available — prompt user
            usePwaStore.getState().setUpdateAvailable(true, registration);
          }
        });
      });

      // Listen for controller change (after skipWaiting)
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        window.location.reload();
      });
    } catch (error) {
      console.error('SW registration failed:', error);
    }
  });

  // Capture install prompt
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    usePwaStore.getState().setInstallable(true, event);
  });

  // Listen for contract mismatch from the interceptor
  window.addEventListener('pwa:contract-mismatch', () => {
    usePwaStore.getState().setContractMismatch(true);
    // Trigger SW update check
    navigator.serviceWorker.getRegistration().then((reg) => {
      reg?.update();
    });
  });
}
