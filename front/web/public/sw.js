// ============================================================
// Leopardo RH — Service Worker (PWA Offline)
// Provides offline support for the web dashboard
// ============================================================

const CACHE_NAME = 'leopardo-edge-v1';
const OFFLINE_URL = '/offline';

// Assets to pre-cache on install
const PRECACHE_ASSETS = [
  '/',
  '/offline',
  '/dashboard',
  '/dashboard/attendance',
  '/dashboard/absences',
  '/dashboard/employees',
  '/favicon.ico',
];

// ── Install ────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[SW] Pre-caching assets');
      return cache.addAll(PRECACHE_ASSETS);
    })
  );
  self.skipWaiting();
});

// ── Activate ───────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) =>
      Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      )
    )
  );
  self.clients.claim();
});

// ── Fetch Strategy: Network first, Cache fallback ─────────
self.addEventListener('fetch', (event) => {
  // Skip non-GET and non-HTTP requests
  if (event.request.method !== 'GET') return;
  if (!event.request.url.startsWith('http')) return;

  // API calls: network only (don't cache API responses in SW)
  if (event.request.url.includes('/api/')) {
    event.respondWith(
      fetch(event.request).catch(() =>
        new Response(
          JSON.stringify({ error: 'offline', message: 'No network connection' }),
          { headers: { 'Content-Type': 'application/json' }, status: 503 }
        )
      )
    );
    return;
  }

  // Pages/assets: network first, cache fallback
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response.ok) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) =>
            cache.put(event.request, responseClone)
          );
        }
        return response;
      })
      .catch(async () => {
        const cached = await caches.match(event.request);
        return cached || caches.match(OFFLINE_URL);
      })
  );
});

// ── Background Sync (when back online) ────────────────────
self.addEventListener('sync', (event) => {
  if (event.tag === 'leopardo-sync') {
    event.waitUntil(
      self.clients.matchAll().then((clients) => {
        clients.forEach((client) =>
          client.postMessage({ type: 'SYNC_REQUESTED' })
        );
      })
    );
  }
});
