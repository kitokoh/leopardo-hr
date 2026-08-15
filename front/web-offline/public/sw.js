// Leopardo Edge — Service Worker
// Stratégie : Cache First pour assets statiques, Network First pour /api/*
// La logique de décision vit dans sw-strategies.js (testée par Vitest, #3971)
// et est chargée ici via importScripts (SW classique, pas de module).

importScripts('/sw-strategies.js');

const CACHE_NAME = 'leopardo-edge-v1';
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/manifest.json',
];

const strategies = self.LeopardoSwStrategies;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      await Promise.allSettled(
        STATIC_ASSETS.map(async (asset) => {
          try {
            const response = await fetch(asset, { cache: 'no-cache' });
            if (response.ok) await cache.put(asset, response);
          } catch {
            // An unavailable optional asset must not abort SW installation.
          }
        })
      );
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // API calls → Network First, fallback graceful
  if (strategies.classifyRequest(url.pathname) === 'api') {
    event.respondWith(
      fetch(event.request).catch(() =>
        new Response(
          strategies.offlineApiResponse(),
          { status: 503, headers: { 'Content-Type': 'application/json' } }
        )
      )
    );
    return;
  }

  // Static assets → Cache First
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;
      return fetch(event.request)
        .then((response) => {
          if (!response || !strategies.isCacheable(response.status, response.type)) {
            return response;
          }
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
          return response;
        })
        .catch(() => {
          // Issue #3962 — navigation hors-ligne vers une route non visitée :
          // fetch() rejette → respondWith rejette → page d'erreur navigateur.
          // Fallback : servir l'app shell pré-cachée (SPA offline-first).
          const fallback = strategies.navigationFallback(url.pathname, event.request.mode);
          if (fallback) {
            return caches.match(fallback);
          }
          return new Response('Offline', { status: 503 });
        });
    })
  );
});
