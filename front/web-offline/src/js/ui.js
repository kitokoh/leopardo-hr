/**
 * Leopardo Edge — UI helpers
 * Fonctions utilitaires pour le rendu DOM.
 */

// ---------------------------------------------------------------------------
// Toast notifications
// ---------------------------------------------------------------------------
export function showToast(message, type = 'info', duration = 3500) {
  const container =
    document.getElementById('toast-container') || createToastContainer();

  const toast = document.createElement('div');
  toast.className = `toast toast--${type}`;
  toast.setAttribute('role', 'alert');
  toast.innerHTML = `
    <span class="toast__icon">${iconFor(type)}</span>
    <span class="toast__message">${escapeHtml(message)}</span>
  `;

  container.appendChild(toast);
  // Trigger animation
  requestAnimationFrame(() => toast.classList.add('toast--visible'));

  setTimeout(() => {
    toast.classList.remove('toast--visible');
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });
  }, duration);
}

function createToastContainer() {
  const el = document.createElement('div');
  el.id = 'toast-container';
  el.setAttribute('aria-live', 'polite');
  document.body.appendChild(el);
  return el;
}

function iconFor(type) {
  return { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' }[type] ?? 'ℹ️';
}

// ---------------------------------------------------------------------------
// Sync status badge
// ---------------------------------------------------------------------------
export function updateSyncBadge(online, queueCount = 0) {
  const badge = document.getElementById('sync-badge');
  if (!badge) return;

  if (online) {
    badge.className = 'sync-badge sync-badge--online';
    badge.textContent = queueCount > 0 ? `☁ Sync en cours (${queueCount})` : '☁ Cloud connecté';
  } else {
    badge.className = 'sync-badge sync-badge--offline';
    badge.textContent = queueCount > 0
      ? `✈ Offline — ${queueCount} en attente`
      : '✈ Mode offline';
  }
}

// ---------------------------------------------------------------------------
// Attendance list rendering
// ---------------------------------------------------------------------------
export function renderAttendanceList(logs, container) {
  if (!container) return;

  if (!logs || logs.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <p>Aucun pointage enregistré aujourd'hui.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = logs
    .map(
      (log) => `
    <div class="attendance-row attendance-row--${log.type}">
      <div class="attendance-row__avatar">
        ${initials(log.employee_name || '?')}
      </div>
      <div class="attendance-row__info">
        <div class="attendance-row__name">${escapeHtml(log.employee_name || 'N/A')}</div>
        <div class="attendance-row__meta">${formatTime(log.checked_at)} — ${labelFor(log.type)}</div>
      </div>
      <div class="attendance-row__badge ${log.sync_status === 'pending' ? 'badge--pending' : 'badge--synced'}">
        ${log.sync_status === 'pending' ? '⏳' : '✅'}
      </div>
    </div>
  `
    )
    .join('');
}

// ---------------------------------------------------------------------------
// Employee selector
// ---------------------------------------------------------------------------
export function renderEmployeeSelect(employees, selectEl) {
  if (!selectEl) return;
  selectEl.innerHTML =
    '<option value="">— Sélectionner un employé —</option>' +
    employees
      .map(
        (e) =>
          `<option value="${e.id}">${escapeHtml(e.last_name)} ${escapeHtml(e.first_name)}</option>`
      )
      .join('');
}

// ---------------------------------------------------------------------------
// Loading states
// ---------------------------------------------------------------------------
export function setLoading(el, loading) {
  if (!el) return;
  if (loading) {
    el.setAttribute('disabled', 'true');
    el.dataset.originalText = el.textContent;
    el.textContent = 'Chargement…';
  } else {
    el.removeAttribute('disabled');
    el.textContent = el.dataset.originalText || el.textContent;
  }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function initials(name) {
  return name
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('');
}

function formatTime(isoString) {
  if (!isoString) return '--:--';
  return new Date(isoString).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function labelFor(type) {
  return (
    {
      check_in: 'Entrée',
      check_out: 'Sortie',
      break_start: 'Début pause',
      break_end: 'Fin pause',
    }[type] ?? type
  );
}
