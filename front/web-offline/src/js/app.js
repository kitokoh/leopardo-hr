/**
 * Leopardo Edge — Application principale
 * Point d'entrée PWA : init, routing, gestion du cycle online/offline.
 */

import * as Api from './api.js';
import * as UI from './ui.js';

// ---------------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', async () => {
  registerServiceWorker();
  bindNetworkEvents();
  await refreshSyncBadge();

  if (!Api.isAuthenticated()) {
    showView('login');
  } else {
    showView('dashboard');
    await loadDashboard();
  }
});

// ---------------------------------------------------------------------------
// Service Worker
// ---------------------------------------------------------------------------
function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker
      .register('/sw.js')
      .then((reg) => console.log('[App] SW registered', reg.scope))
      .catch((err) => console.error('[App] SW registration failed', err));

    // Recevoir les messages du SW (background sync trigger)
    navigator.serviceWorker.addEventListener('message', async (event) => {
      if (event.data?.type === 'BACKGROUND_SYNC_ATTENDANCE') {
        console.log('[App] Background sync triggered');
        await flushQueue();
      }
    });
  }
}

// ---------------------------------------------------------------------------
// Network events
// ---------------------------------------------------------------------------
function bindNetworkEvents() {
  window.addEventListener('online', async () => {
    UI.showToast('Connexion rétablie — synchronisation en cours…', 'info');
    await refreshSyncBadge();
    await flushQueue();
    await loadAttendanceList();
  });

  window.addEventListener('offline', () => {
    UI.showToast('Mode offline activé — les pointages seront mis en file', 'warning');
    refreshSyncBadge();
  });
}

async function refreshSyncBadge() {
  const online = navigator.onLine && (await Api.checkCloudStatus());
  const queue = JSON.parse(localStorage.getItem('offline_queue') || '[]');
  UI.updateSyncBadge(online, queue.length);
}

async function flushQueue() {
  try {
    const results = await Api.flushOfflineQueue();
    if (results.success > 0) {
      UI.showToast(`${results.success} pointage(s) synchronisé(s)`, 'success');
      await refreshSyncBadge();
    }
  } catch (err) {
    console.warn('[App] Flush failed:', err);
  }
}

// ---------------------------------------------------------------------------
// View routing
// ---------------------------------------------------------------------------
function showView(name) {
  document.querySelectorAll('[data-view]').forEach((el) => {
    el.style.display = el.dataset.view === name ? '' : 'none';
  });
}

// ---------------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------------
const loginForm = document.getElementById('login-form');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('login-email')?.value;
    const password = document.getElementById('login-password')?.value;
    const btn = loginForm.querySelector('[type=submit]');

    UI.setLoading(btn, true);
    try {
      await Api.login({ email, password });
      showView('dashboard');
      await loadDashboard();
    } catch (err) {
      UI.showToast(err.message, 'error');
    } finally {
      UI.setLoading(btn, false);
    }
  });
}

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------
async function loadDashboard() {
  await Promise.allSettled([loadAttendanceList(), loadEmployeeSelect()]);
}

async function loadAttendanceList() {
  const container = document.getElementById('attendance-list');
  if (!container) return;
  try {
    const data = await Api.fetchTodayAttendance();
    UI.renderAttendanceList(data.data ?? data, container);
  } catch {
    UI.renderAttendanceList([], container);
  }
}

async function loadEmployeeSelect() {
  const select = document.getElementById('employee-select');
  if (!select) return;
  try {
    const data = await Api.fetchEmployees();
    UI.renderEmployeeSelect(data.data ?? data, select);
  } catch {
    console.warn('[App] Could not load employees — offline mode');
  }
}

// ---------------------------------------------------------------------------
// Attendance form
// ---------------------------------------------------------------------------
const attendanceForm = document.getElementById('attendance-form');
if (attendanceForm) {
  attendanceForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = attendanceForm.querySelector('[type=submit]');
    const employeeId = document.getElementById('employee-select')?.value;
    const type = document.getElementById('attendance-type')?.value;

    if (!employeeId || !type) {
      UI.showToast('Veuillez sélectionner un employé et un type de pointage', 'warning');
      return;
    }

    UI.setLoading(btn, true);
    try {
      const result = await Api.createAttendance({
        employee_id: Number(employeeId),
        type,
        checked_at: new Date().toISOString(),
      });

      if (result.queued) {
        UI.showToast('Pointage mis en file d\'attente (offline)', 'warning');
      } else {
        UI.showToast('Pointage enregistré ✅', 'success');
      }

      attendanceForm.reset();
      await loadAttendanceList();
      await refreshSyncBadge();
    } catch (err) {
      UI.showToast(err.message || 'Erreur lors du pointage', 'error');
    } finally {
      UI.setLoading(btn, false);
    }
  });
}

// ---------------------------------------------------------------------------
// Logout
// ---------------------------------------------------------------------------
document.getElementById('logout-btn')?.addEventListener('click', () => {
  Api.logout();
  showView('login');
});
