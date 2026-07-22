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
    throw new Error(payload.error || payload.message || t('error.generic', { status: response.status }));
  }

  return payload;
}

const statusResult = document.getElementById('statusResult');
const syncResult = document.getElementById('syncResult');
const eventsResult = document.getElementById('eventsResult');

async function loadStatus() {
  const status = await fetchJson('/local/status');
  statusResult.textContent = JSON.stringify(status.data, null, 2);
}

async function loadEvents() {
  const events = await fetchJson('/local/events');
  eventsResult.textContent = JSON.stringify(events.data, null, 2);
}

async function runSync(path) {
  try {
    syncResult.textContent = t('admin.sync.inProgress');
    const payload = await fetchJson(path, { method: 'POST', body: '{}' });
    syncResult.textContent = JSON.stringify(payload.data, null, 2);
    await loadStatus();
    await loadEvents();
  } catch (error) {
    syncResult.textContent = error.message || t('admin.sync.error');
  }
}

document.getElementById('syncAll').addEventListener('click', () => runSync('/local/sync/all'));
document.getElementById('syncRoster').addEventListener('click', () => runSync('/local/sync/roster'));
document.getElementById('syncEvents').addEventListener('click', () => runSync('/local/sync/events'));

window.KioskI18n.applyStaticTranslations();
window.KioskI18n.initLangSelector('langSelect');
document.addEventListener('leopardo:lang-changed', () => {
  loadStatus();
  loadEvents();
});

loadStatus();
loadEvents();
