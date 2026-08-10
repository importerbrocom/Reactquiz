/// <reference lib="webworker" />
import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching';
import { registerRoute, NavigationRoute } from 'workbox-routing';
import { CacheFirst, NetworkFirst, StaleWhileRevalidate, NetworkOnly } from 'workbox-strategies';
import { BackgroundSyncPlugin } from 'workbox-background-sync';

declare const self: ServiceWorkerGlobalScope;

// ─── Precaching (injected by vite-plugin-pwa at build time) ──────────────────
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

// ─── Navigation: Network First → cached index.html → offline.html ────────────
const navigationRoute = new NavigationRoute(
  new NetworkFirst({
    cacheName: 'qp-shell',
    networkTimeoutSeconds: 3,
  }),
  {
    // Don't intercept API calls or admin routes
    denylist: [/^\/api\//, /^\/admin\//],
  },
);
registerRoute(navigationRoute);

// ─── Static assets (hashed JS/CSS/fonts): Cache First, 1 year ────────────────
registerRoute(
  ({ request, url }) =>
    url.pathname.startsWith('/assets/') &&
    (request.destination === 'script' ||
      request.destination === 'style' ||
      request.destination === 'font'),
  new CacheFirst({
    cacheName: 'qp-static',
  }),
);

// ─── Icons and images: Cache First, 30d ──────────────────────────────────────
registerRoute(
  ({ url }) => url.pathname.startsWith('/icons/') || url.pathname.startsWith('/splash/'),
  new CacheFirst({
    cacheName: 'qp-images',
  }),
);

// ─── API: GET /bootstrap, /exam-categories, /courses: SWR ────────────────────
registerRoute(
  ({ url }) =>
    url.pathname.includes('/api/v1/bootstrap') ||
    url.pathname.includes('/api/v1/exam-categories') ||
    url.pathname.match(/\/api\/v1\/courses/) !== null,
  new StaleWhileRevalidate({
    cacheName: 'qp-api-safe',
  }),
);

// ─── API: Student dashboard, days, progress: Network First, 5min ─────────────
registerRoute(
  ({ url }) =>
    url.pathname.includes('/api/v1/student/dashboard') ||
    url.pathname.includes('/api/v1/student/days') ||
    url.pathname.includes('/api/v1/student/progress'),
  new NetworkFirst({
    cacheName: 'qp-api-user',
    networkTimeoutSeconds: 5,
  }),
);

// ─── API: Quiz day questions (answer-free): Network First, cacheable ─────────
registerRoute(
  ({ url }) => url.pathname.match(/\/api\/v1\/student\/courses\/.*\/quiz-days\/\d+/) !== null,
  new NetworkFirst({
    cacheName: 'qp-api-safe',
    networkTimeoutSeconds: 5,
  }),
);

// ─── Answer submissions: Network Only + Background Sync ──────────────────────
const bgSyncPlugin = new BackgroundSyncPlugin('sync-answers', {
  maxRetentionTime: 24 * 60, // 24 hours in minutes
});

registerRoute(
  ({ url, request }) =>
    request.method === 'POST' &&
    (url.pathname.includes('/answers') || url.pathname.includes('/answers/batch')),
  new NetworkOnly({
    plugins: [bgSyncPlugin],
  }),
  'POST',
);

// ─── SW lifecycle ────────────────────────────────────────────────────────────

// No skipWaiting by default — waiting worker activates when user accepts update
// or when no in-progress quiz detected
self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});
