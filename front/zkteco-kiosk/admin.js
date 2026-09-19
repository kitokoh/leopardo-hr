const t = (key, params) => window.KioskI18n.t(key, params);

async function fetchJson(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.headers || {}),
    },
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(payload.error || payload.message || t('error.generic', { status: response.status }));
    error.status = response.status;
    error.code = payload.error || '';
    throw error;
  }

  return payload;
}

// #7651 — admin.html ne reçoit plus AUCUN token injecté dans le DOM : la
// session admin est ouverte par PIN (POST /local/admin/login, rate-limité
// côté bridge) et le token de session ne vit qu'en mémoire JS, jamais
// persisté ni écrit dans la page.
let adminToken = '';

async function adminFetchJson(url, options = {}) {
  try {
    return await fetchJson(url, {
      ...options,
      headers: {
        'X-Local-Admin-Token': adminToken,
        ...(options.headers || {}),
      },
    });
  } catch (error) {
    if (error.status === 401) {
      // Session expirée ou révoquée : retour à l'écran PIN.
      adminToken = '';
      showLogin(t('admin.login.sessionExpired'));
    }
    throw error;
  }
}

const statusResult = document.getElementById('statusResult');
const syncResult = document.getElementById('syncResult');
const eventsResult = document.getElementById('eventsResult');
const loginCard = document.getElementById('adminLoginCard');
const loginForm = document.getElementById('adminLoginForm');
const loginStatus = document.getElementById('adminLoginStatus');
const pinInput = document.getElementById('adminPinInput');
const panels = document.getElementById('adminPanels');

function showLogin(message) {
  panels.hidden = true;
  loginCard.hidden = false;
  loginStatus.textContent = message || '';
  if (pinInput) {
    pinInput.value = '';
    try { pinInput.focus(); } catch { /* noop */ }
  }
}

function showPanels() {
  loginCard.hidden = true;
  panels.hidden = false;
}

function loginErrorMessage(error) {
  if (error.code === 'ADMIN_PIN_INVALID') return t('admin.login.invalid');
  if (error.code === 'ADMIN_LOGIN_RATE_LIMITED') return t('admin.login.rateLimited');
  if (error.code === 'ADMIN_PIN_NOT_CONFIGURED') return t('admin.login.notConfigured');
  return error.message || t('admin.login.invalid');
}

async function submitLogin(event) {
  event.preventDefault();
  const pin = (pinInput.value || '').trim();
  if (!pin) return;
  loginStatus.textContent = t('admin.login.checking');
  try {
    const payload = await fetchJson('/local/admin/login', {
      method: 'POST',
      body: JSON.stringify({ pin }),
    });
    adminToken = (payload.data && payload.data.admin_token) || '';
    pinInput.value = '';
    loginStatus.textContent = '';
    showPanels();
    await loadStatus();
    await loadEvents();
  } catch (error) {
    loginStatus.textContent = loginErrorMessage(error);
  }
}

async function loadStatus() {
  const status = await adminFetchJson('/local/status');
  statusResult.textContent = JSON.stringify(status.data, null, 2);
}

async function loadEvents() {
  const events = await adminFetchJson('/local/events');
  eventsResult.textContent = JSON.stringify(events.data, null, 2);
}

async function runSync(path) {
  try {
    syncResult.textContent = t('admin.sync.inProgress');
    const payload = await adminFetchJson(path, { method: 'POST', body: '{}' });
    syncResult.textContent = JSON.stringify(payload.data, null, 2);
    await loadStatus();
    await loadEvents();
  } catch (error) {
    syncResult.textContent = error.message || t('admin.sync.error');
  }
}

loginForm.addEventListener('submit', submitLogin);
document.getElementById('syncAll').addEventListener('click', () => runSync('/local/sync/all'));
document.getElementById('syncRoster').addEventListener('click', () => runSync('/local/sync/roster'));
document.getElementById('syncEvents').addEventListener('click', () => runSync('/local/sync/events'));

window.KioskI18n.applyStaticTranslations();
window.KioskI18n.initLangSelector('langSelect');
document.addEventListener('leopardo:lang-changed', () => {
  if (!adminToken) return;
  loadStatus();
  loadEvents();
});

showLogin('');
