// Bumped so installed clients drop the old app shell and pick up the
// safe-area/session fixes on their next launch.
const CACHE_NAME = 'ero-v2';
const ASSETS = [
  '/',
  '/index.html',
  '/manifest.webmanifest'
];

// Install - cache assets
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

// Activate - clean old caches
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch - network first, fallback to cache
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  if (e.request.url.includes('/api/')) return; // Don't cache API calls
  // Activation state must never be answered from cache: a stale
  // "activated:false" would ask an activated student for their code again.
  // When offline this now fails fast and the client uses its local flag.
  if (e.request.url.includes('/codes.php')) return;

  e.respondWith(
    fetch(e.request)
      .then(response => {
        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(e.request, clone));
        return response;
      })
      .catch(() => caches.match(e.request))
  );
});
