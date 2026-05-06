/**
 * Service Worker for Leopardo PWA
 * Handles offline support, caching, and background sync
 */

const CACHE_NAME = 'leopardo-v1';
const RUNTIME_CACHE = 'leopardo-runtime-v1';
const ASSETS_CACHE = 'leopardo-assets-v1';

// Assets to cache on install
const ASSETS_TO_CACHE = [
  '/',
  '/offline.html',
  '/favicon.ico',
  '/manifest.json',
];

// API endpoints to cache
const API_CACHE_PATTERNS = [
  /^https:\/\/api\.leopardo\.com\/api\//,
];

// Install event - cache essential assets
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');

  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[Service Worker] Caching essential assets');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );

  // Skip waiting to activate immediately
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');

  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE && cacheName !== ASSETS_CACHE) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );

  // Claim all clients
  self.clients.claim();
});

// Fetch event - implement caching strategies
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Skip chrome extensions and other non-http(s) requests
  if (!url.protocol.startsWith('http')) {
    return;
  }

  // Handle API requests
  if (API_CACHE_PATTERNS.some((pattern) => pattern.test(url.href))) {
    event.respondWith(networkFirstStrategy(request));
    return;
  }

  // Handle image requests
  if (request.destination === 'image') {
    event.respondWith(cacheFirstStrategy(request, ASSETS_CACHE));
    return;
  }

  // Handle font requests
  if (request.destination === 'font') {
    event.respondWith(cacheFirstStrategy(request, ASSETS_CACHE));
    return;
  }

  // Handle style and script requests
  if (request.destination === 'style' || request.destination === 'script') {
    event.respondWith(cacheFirstStrategy(request, ASSETS_CACHE));
    return;
  }

  // Default strategy for HTML and other requests
  event.respondWith(networkFirstStrategy(request));
});

/**
 * Network First Strategy
 * Try network first, fall back to cache
 */
async function networkFirstStrategy(request) {
  try {
    const response = await fetch(request);

    // Cache successful responses
    if (response.ok) {
      const cache = await caches.open(RUNTIME_CACHE);
      cache.put(request, response.clone());
    }

    return response;
  } catch {
    console.log('[Service Worker] Network request failed, using cache:', request.url);

    // Try to get from cache
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    // Return offline page for navigation requests
    if (request.mode === 'navigate') {
      return caches.match('/offline.html');
    }

    // Return error response
    return new Response('Offline - Resource not available', {
      status: 503,
      statusText: 'Service Unavailable',
      headers: new Headers({
        'Content-Type': 'text/plain',
      }),
    });
  }
}

/**
 * Cache First Strategy
 * Try cache first, fall back to network
 */
async function cacheFirstStrategy(request, cacheName) {
  try {
    // Try to get from cache
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    // Try network
    const response = await fetch(request);

    // Cache successful responses
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }

    return response;
  } catch {
    console.log('[Service Worker] Cache first strategy failed:', request.url);

    // Return error response
    return new Response('Offline - Resource not available', {
      status: 503,
      statusText: 'Service Unavailable',
      headers: new Headers({
        'Content-Type': 'text/plain',
      }),
    });
  }
}

/**
 * Message event - handle messages from clients
 */
self.addEventListener('message', (event) => {
  console.log('[Service Worker] Message received:', event.data);

  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data && event.data.type === 'CLEAR_CACHE') {
    caches.keys().then((cacheNames) => {
      Promise.all(
        cacheNames.map((cacheName) => caches.delete(cacheName))
      );
    });
  }
});

/**
 * Background Sync event - sync data when back online
 */
self.addEventListener('sync', (event) => {
  console.log('[Service Worker] Background sync:', event.tag);

  if (event.tag === 'sync-forms') {
    event.waitUntil(syncForms());
  }

  if (event.tag === 'sync-analytics') {
    event.waitUntil(syncAnalytics());
  }
});

/**
 * Sync pending form submissions
 */
async function syncForms() {
  try {
    const db = await openIndexedDB();
    const pendingForms = await getPendingForms(db);

    for (const form of pendingForms) {
      try {
        const response = await fetch(form.endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(form.data),
        });

        if (response.ok) {
          await removePendingForm(db, form.id);
        }
      } catch (error) {
        console.error('[Service Worker] Failed to sync form:', form.id, error);
      }
    }
  } catch (error) {
    console.error('[Service Worker] Form sync failed:', error);
  }
}

/**
 * Sync pending analytics events
 */
async function syncAnalytics() {
  try {
    const db = await openIndexedDB();
    const pendingEvents = await getPendingAnalytics(db);

    for (const event of pendingEvents) {
      try {
        const response = await fetch('/api/analytics', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(event),
        });

        if (response.ok) {
          await removePendingAnalytics(db, event.id);
        }
      } catch (error) {
        console.error('[Service Worker] Failed to sync analytics:', event.id, error);
      }
    }
  } catch (error) {
    console.error('[Service Worker] Analytics sync failed:', error);
  }
}

/**
 * IndexedDB helpers
 */
function openIndexedDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('leopardo', 1);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains('pendingForms')) {
        db.createObjectStore('pendingForms', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('pendingAnalytics')) {
        db.createObjectStore('pendingAnalytics', { keyPath: 'id' });
      }
    };
  });
}

function getPendingForms(db) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['pendingForms'], 'readonly');
    const store = transaction.objectStore('pendingForms');
    const request = store.getAll();

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

function removePendingForm(db, id) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['pendingForms'], 'readwrite');
    const store = transaction.objectStore('pendingForms');
    const request = store.delete(id);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve();
  });
}

function getPendingAnalytics(db) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['pendingAnalytics'], 'readonly');
    const store = transaction.objectStore('pendingAnalytics');
    const request = store.getAll();

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

function removePendingAnalytics(db, id) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['pendingAnalytics'], 'readwrite');
    const store = transaction.objectStore('pendingAnalytics');
    const request = store.delete(id);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve();
  });
}

console.log('[Service Worker] Loaded');
