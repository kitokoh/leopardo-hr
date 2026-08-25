// ============================================================
// Leopardo RH — Service Worker (PWA Offline)
// Provides offline support for the web dashboard
// ============================================================

const CACHE_NAME = 'leopardo-edge-v1';
const OFFLINE_URL = '/offline';

// Préfixes session-protégés — miroir de src/lib/protected-prefixes.ts (#3377).
// ⚠️ Source unique côté TS ; la garde Jest `protected-prefixes.test.ts` vérifie
// que cette liste ne dérive pas. Ne pas mettre en cache le HTML authentifié.
const PROTECTED_PREFIXES = [
  '/dashboard',
  '/absences',
  '/attendance',
  '/billing',
  '/contracts',
  '/employees',
  '/partner',
  '/payroll',
  '/reports',
  '/training',
  '/settings',
  '/attendance/geo',
  '/social',
  '/social-marketing',
];

function isProtectedPath(url) {
  const { pathname } = new URL(url);
  return PROTECTED_PREFIXES.some(
    (prefix) => pathname === prefix || pathname.startsWith(prefix + '/')
  );
}

// Assets to pre-cache on install
// Issue #2983 : les routes dashboard/attendance/absences/employees sont des
// routes AUTHENTIFIÉES — les précacher expose la page de login en cache et
// stocke du HTML privé côté client. On ne précache que les pages publiques.
const PRECACHE_ASSETS = [
  '/',
  '/offline',
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

  // Pages/assets: network first, cache fallback. Issue #3729 (audit 360°) :
  // le HTML des routes AUTHENTIFIÉES ne doit jamais être mis en cache — même
  // transitoirement (décision #2983, symétrique du précache).
  if (isProtectedPath(event.request.url)) {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(event.request).then((cached) => cached || caches.match(OFFLINE_URL)))
    );
    return;
  }

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
// QA #3028 — tags réellement enregistrés par le client (PWAProvider :
// 'sync-forms' + 'sync-analytics') ; 'leopardo-sync' conservé par compat.
const SYNC_TAGS = new Set(['leopardo-sync', 'sync-forms', 'sync-analytics']);
self.addEventListener('sync', (event) => {
  if (SYNC_TAGS.has(event.tag)) {
    event.waitUntil(
      self.clients.matchAll().then((clients) => {
        clients.forEach((client) =>
          client.postMessage({ type: 'SYNC_REQUESTED' })
        );
      })
    );
  }
});
