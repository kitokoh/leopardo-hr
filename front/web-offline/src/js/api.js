/**
 * Leopardo Edge — API Client
 * Communique avec le nœud Edge local via http://leopardo.local/api/v1/edge/*
 * Stocke en IndexedDB les pointages en attente si le réseau est absent.
 */

const BASE_URL = window.EDGE_API_URL || 'http://leopardo.local';
const API_PREFIX = `${BASE_URL}/api/v1/edge`;

// ---------------------------------------------------------------------------
// IndexedDB — queue offline
// ---------------------------------------------------------------------------
const DB_NAME = 'leopardo_offline_queue';
const DB_VERSION = 1;

let _db = null;

function openDb() {
  if (_db) return Promise.resolve(_db);
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('attendance_queue')) {
        const store = db.createObjectStore('attendance_queue', {
          keyPath: 'localId',
        });
        store.createIndex('status', 'status');
      }
    };
    req.onsuccess = (e) => {
      _db = e.target.result;
      resolve(_db);
    };
    req.onerror = () => reject(req.error);
  });
}

async function enqueueOffline(entry) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('attendance_queue', 'readwrite');
    tx.objectStore('attendance_queue').put({ ...entry, status: 'pending' });
    tx.oncomplete = () => resolve(entry.localId);
    tx.onerror = () => reject(tx.error);
  });
}

async function getPendingQueue() {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('attendance_queue', 'readonly');
    const req = tx.objectStore('attendance_queue').index('status').getAll('pending');
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function markQueueSynced(localId) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('attendance_queue', 'readwrite');
    const store = tx.objectStore('attendance_queue');
    const req = store.get(localId);
    req.onsuccess = () => {
      const entry = req.result;
      if (entry) {
        entry.status = 'synced';
        store.put(entry);
      }
    };
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

// ---------------------------------------------------------------------------
// HTTP helpers
// ---------------------------------------------------------------------------
async function apiFetch(path, options = {}) {
  const token = localStorage.getItem('edge_token');
  const res = await fetch(`${API_PREFIX}${path}`, {
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
    ...options,
  });

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw Object.assign(new Error(body.message || res.statusText), {
      status: res.status,
    });
  }

  return res.json();
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/** Récupère les employés actifs depuis le cache Edge */
export async function fetchEmployees() {
  return apiFetch('/employees');
}

/** Récupère les pointages du jour */
export async function fetchTodayAttendance() {
  return apiFetch('/attendance/today');
}

/**
 * Crée un pointage. Si offline, stocke en IDB et enregistre un Background Sync.
 * @param {object} payload - { employee_id, type, checked_at, location }
 */
export async function createAttendance(payload) {
  const localId = crypto.randomUUID();
  const entry = { localId, ...payload, created_at: new Date().toISOString() };

  try {
    const result = await apiFetch('/attendance', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    return { ...result, localId, synced: true };
  } catch (err) {
    if (!navigator.onLine || err.status === undefined) {
      await enqueueOffline(entry);
      // Demander Background Sync si supporté
      if ('serviceWorker' in navigator && 'SyncManager' in window) {
        const reg = await navigator.serviceWorker.ready;
        await reg.sync.register('sync-attendance');
      }
      return { localId, synced: false, queued: true };
    }
    throw err;
  }
}

/**
 * Rejoue tous les pointages en attente dans IDB vers l'API Edge.
 * Appelé au retour du réseau ou depuis le Background Sync.
 */
export async function flushOfflineQueue() {
  const queue = await getPendingQueue();
  const results = { success: 0, failed: 0 };

  for (const entry of queue) {
    try {
      await apiFetch('/attendance', {
        method: 'POST',
        body: JSON.stringify(entry),
      });
      await markQueueSynced(entry.localId);
      results.success++;
    } catch {
      results.failed++;
    }
  }

  return results;
}

/** Vérifie si le Cloud est accessible */
export async function checkCloudStatus() {
  try {
    const res = await fetch(`${BASE_URL}/api/v1/edge/cloud-status`, {
      signal: AbortSignal.timeout(4000),
    });
    return res.ok;
  } catch {
    return false;
  }
}

/** Authentification Edge (retourne un token JWT) */
export async function login(credentials) {
  const res = await fetch(`${API_PREFIX}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(credentials),
  });
  if (!res.ok) throw new Error('Identifiants incorrects');
  const data = await res.json();
  localStorage.setItem('edge_token', data.token);
  return data;
}

export function logout() {
  localStorage.removeItem('edge_token');
}

export function isAuthenticated() {
  return !!localStorage.getItem('edge_token');
}
