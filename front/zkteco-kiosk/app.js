/* ───────────────────────────────────────────────────
   Leopardo RH — ZKTeco Kiosk (H1–H4)
   Vanilla JS, offline-first via local bridge
   ─────────────────────────────────────────────────── */

// ── Configuration ────────────────────────────────────
const CONFIG = {
  localBridgeUrl: '/local',
  apiBaseUrl: window.__KIOSK_API_BASE || '',
  deviceCode: window.__KIOSK_DEVICE_CODE || '',
  kioskToken: window.__KIOSK_TOKEN || '',
  refreshInterval: 15000,
  announcementRefreshInterval: 60000,
};

// ── Feedback audio (issue #1628) ──────────────────────────
// Retour sonore immédiat au pointage (succès/échec) via Web Audio API,
// sans asset externe. Non bloquant : si l'API audio est indisponible
// (navigateur, permissions, contexte), le pointage fonctionne quand même.
const feedback = (() => {
  let ctx = null;

  function ensureContext() {
    if (!window.AudioContext && !window.webkitAudioContext) return null;
    if (!ctx) {
      const Ctor = window.AudioContext || window.webkitAudioContext;
      try { ctx = new Ctor(); } catch { return null; }
    }
    if (ctx.state === 'suspended') { try { ctx.resume(); } catch { /* noop */ } }
    return ctx;
  }

  function tone(freq, start, duration, gainValue, type = 'sine') {
    const audio = ensureContext();
    if (!audio) return;
    const osc = audio.createOscillator();
    const gain = audio.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.0001, audio.currentTime + start);
    gain.gain.exponentialRampToValueAtTime(gainValue, audio.currentTime + start + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + start + duration);
    osc.connect(gain).connect(audio.destination);
    osc.start(audio.currentTime + start);
    osc.stop(audio.currentTime + start + duration + 0.05);
  }

  return {
    /** Succès : double bip ascendant (do→mi). */
    success() {
      tone(523.25, 0, 0.18, 0.25);
      tone(659.25, 0.14, 0.24, 0.25);
    },
    /** Échec : buzz grave court. */
    error() {
      tone(196.0, 0, 0.28, 0.22, 'triangle');
    },
  };
})();

// ── State ────────────────────────────────────────────
const state = {
  status: null,
  currentTab: 'punch',
  lastStatusRefreshAt: null,
  isPunching: false,
  isRetryingSync: false,
};

// ── Selectors ────────────────────────────────────────
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const els = {
  companyName: $('#companyName'),
  locationLabel: $('#locationLabel'),
  deviceCode: $('#deviceCode'),
  queueCount: $('#queueCount'),
  lastSyncAt: $('#lastSyncAt'),
  identifier: $('#identifier'),
  biometricType: $('#biometricType'),
  statusBox: $('#statusBox'),
  syncDot: $('#syncDot'),
  syncLabel: $('#syncLabel'),
  checkInBtn: $('#checkInButton'),
  checkOutBtn: $('#checkOutButton'),
  syncRetryBtn: $('#syncRetryBtn'),
};

// ── Utilities ────────────────────────────────────────
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

async function kioskApi(path, options = {}) {
  const apiBaseUrl = (CONFIG.apiBaseUrl || '').replace(/\/$/, '');
  const versionedBaseUrl = apiBaseUrl.endsWith('/api/v1') ? apiBaseUrl : `${apiBaseUrl}/api/v1`;
  const url = `${versionedBaseUrl}/kiosks/${CONFIG.deviceCode}${path}`;
  return fetchJson(url, {
    ...options,
    headers: {
      'X-Kiosk-Token': CONFIG.kioskToken,
      ...(options.headers || {}),
    },
  });
}

function setStatus(boxId, message, isError = false) {
  const box = $(boxId);
  if (!box) return;
  box.textContent = message;
  box.classList.toggle('error', isError);
  box.classList.remove('hidden');
}

function initials(name) {
  const parts = (name || '').trim().split(' ');
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  return name ? name[0].toUpperCase() : '?';
}

function actionLabel(action) {
  return action === 'check_out' ? t('punch.action.checkOut') : t('punch.action.checkIn');
}

function setPunchButtonsDisabled(disabled) {
  if (els.checkInBtn) els.checkInBtn.disabled = disabled;
  if (els.checkOutBtn) els.checkOutBtn.disabled = disabled;
}

async function findLocalRosterEmployee(identifier) {
  try {
    const payload = await fetchJson(`${CONFIG.localBridgeUrl}/roster`);
    const roster = payload.data || [];
    const normalized = identifier.toLowerCase();
    return roster.find((employee) => {
      return [employee.matricule, employee.zkteco_id, employee.email, employee.name]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase() === normalized);
    }) || null;
  } catch {
    return null;
  }
}

// ── Tab Navigation ───────────────────────────────────
function initTabs() {
  $$('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.tab;
      $$('.tab').forEach(tabEl => { tabEl.classList.remove('active'); tabEl.setAttribute('aria-selected', 'false'); });
      $$('.tab-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      const panel = $(`#panel-${target}`);
      if (panel) panel.classList.add('active');
      state.currentTab = target;

      if (target === 'announcements') loadAnnouncements();
    });
  });
}

// ── Punch (existing) ─────────────────────────────────
function renderStatus() {
  if (!state.status) return;
  els.companyName.textContent = state.status.company_name || t('company.default');
  els.locationLabel.textContent = state.status.location_label || t('location.default');
  els.deviceCode.textContent = state.status.device_code || '-';
  els.queueCount.textContent = t('queue.count', { count: state.status.queue_count || 0 });
  if (els.lastSyncAt) {
    const lastSync = state.status.last_sync_at || state.status.last_synced_at || state.lastStatusRefreshAt;
    els.lastSyncAt.textContent = lastSync ? formatDateTime(lastSync) : t('meta.lastSync.checking');
  }

  const ok = state.status.online === true;
  els.syncDot.classList.toggle('ok', ok);
  els.syncDot.classList.toggle('bad', !ok);
  els.syncLabel.textContent = ok
    ? t('sync.online')
    : t('sync.offline', { error: state.status.last_error || t('error.networkUnavailable') });

  // PA2-KIO-003: surface an actionable retry action next to the sync
  // status pill whenever the kiosk is offline or has a pending error, so
  // field staff are not forced to open the separate admin page just to
  // force a resync.
  if (els.syncRetryBtn) {
    const hasPendingIssue = !ok || Number(state.status.queue_count || 0) > 0;
    els.syncRetryBtn.classList.toggle('hidden', !hasPendingIssue);
  }

  window.dispatchEvent(new CustomEvent('leopardo:kiosk-status', {
    detail: {
      online: ok,
      queue_count: Number(state.status.queue_count || 0),
      device_code: state.status.device_code || CONFIG.deviceCode || null,
      last_sync_at: state.status.last_sync_at || state.status.last_synced_at || null,
    },
  }));
}

async function refreshStatus() {
  try {
    const payload = await fetchJson(`${CONFIG.localBridgeUrl}/status`);
    state.status = payload.data;
    state.lastStatusRefreshAt = new Date().toISOString();
    renderStatus();
  } catch (error) {
    setStatus('#statusBox', error.message || t('error.bridgeUnavailable'), true);
    if (els.lastSyncAt) {
      els.lastSyncAt.textContent = t('error.bridgeUnavailableShort');
    }
  }
}

// PA2-KIO-003: actionable retry for the sync status pill. Forces a full
// roster + offline-queue sync through the local bridge and gives immediate
// feedback (success or the concrete error), instead of leaving the kiosk
// stuck showing "offline" with no in-context way to act on it.
async function retrySync() {
  if (!els.syncRetryBtn || state.isRetryingSync) return;
  state.isRetryingSync = true;
  els.syncRetryBtn.disabled = true;
  setStatus('#statusBox', t('sync.retry.inProgress'));
  try {
    await fetchJson(`${CONFIG.localBridgeUrl}/sync/all`, { method: 'POST', body: '{}' });
    setStatus('#statusBox', t('sync.retry.success'));
  } catch (error) {
    setStatus('#statusBox', t('sync.retry.failed', { error: error.message || t('error.networkUnavailable') }), true);
  } finally {
    state.isRetryingSync = false;
    els.syncRetryBtn.disabled = false;
    await refreshStatus();
  }
}

function pulseStatus(success) {
  const box = $('#statusBox');
  if (!box) return;
  box.classList.remove('status-pulse-ok', 'status-pulse-error');
  // Force reflow pour relancer l'animation CSS.
  void box.offsetWidth;
  box.classList.add(success ? 'status-pulse-ok' : 'status-pulse-error');
  window.setTimeout(() => {
    box.classList.remove('status-pulse-ok', 'status-pulse-error');
  }, 900);
}

async function submitPunch(action) {
  if (state.isPunching) return;
  const identifier = els.identifier.value.trim();
  if (!identifier) {
    setStatus('#statusBox', t('error.identifierRequired'), true);
    return;
  }
  state.isPunching = true;
  setPunchButtonsDisabled(true);
  setStatus('#statusBox', t('punch.recognizing', { type: els.biometricType.value, action: actionLabel(action) }));
  try {
    const employee = await findLocalRosterEmployee(identifier);
    const payload = await fetchJson(`${CONFIG.localBridgeUrl}/punch`, {
      method: 'POST',
      body: JSON.stringify({
        identifier,
        action,
        biometric_type: els.biometricType.value,
      }),
    });
    const mode = payload.data.sync_status === 'synced' ? t('punch.mode.synced') : t('punch.mode.offline');
    const employeeLabel = employee?.name || identifier;
    const eventTime = payload.data.occurred_at ? formatTime(payload.data.occurred_at) : formatTime(new Date().toISOString());
    setStatus('#statusBox', t('punch.confirmed', { action: actionLabel(action), mode, time: eventTime, employee: employeeLabel }));
    pulseStatus(true);
    feedback.success();
    els.identifier.value = '';
    els.identifier.focus();
    await refreshStatus();
  } catch (error) {
    setStatus('#statusBox', error.message || t('error.punchFailed'), true);
    pulseStatus(false);
    feedback.error();
  } finally {
    state.isPunching = false;
    setPunchButtonsDisabled(false);
  }
}

// ── H1: Employee Info Post-Punch ─────────────────────
async function searchEmployeeInfo() {
  const identifier = $('#infoIdentifier').value.trim();
  if (!identifier) {
    setStatus('#infoStatus', t('error.identifierRequiredShort'), true);
    return;
  }
  setStatus('#infoStatus', t('search.inProgress'));
  $('#employeeInfoResult').classList.add('hidden');

  try {
    const data = await kioskApi('/employee-info', {
      method: 'POST',
      body: JSON.stringify({ identifier }),
    });

    const emp = data.data.employee;
    const att = data.data.today_attendance;
    const balances = data.data.leave_balances || [];
    const biometric = data.data.biometric_enrollment || null;

    // Avatar
    const avatarEl = $('#empAvatar');
    if (emp.photo_url) {
      avatarEl.innerHTML = `<img src="${safeImageUrl(emp.photo_url)}" alt="${escapeHtml(emp.name)}" />`;
    } else {
      avatarEl.textContent = initials(emp.name);
    }

    $('#empName').textContent = emp.name;
    $('#empMatricule').textContent = emp.matricule || '-';
    $('#empDepartment').textContent = emp.department || '-';
    $('#empPosition').textContent = emp.position || '-';

    // Today attendance
    const attEl = $('#empAttendance');
    if (att) {
      let html = '';
      if (att.check_in) html += `<span class="att-badge att-in">${escapeHtml(t('attendance.checkIn', { time: formatTime(att.check_in) }))}</span>`;
      if (att.check_out) html += `<span class="att-badge att-out">${escapeHtml(t('attendance.checkOut', { time: formatTime(att.check_out) }))}</span>`;
      if (!att.check_in && !att.check_out) html = `<span class="att-badge att-pending">${t('info.attendance.none')}</span>`;
      attEl.innerHTML = html;
    } else {
      attEl.innerHTML = `<span class="att-badge att-pending">${t('info.attendance.pending')}</span>`;
    }

    // Biometric enrollment status (PA2-KIO-004)
    const bioEl = $('#empBiometric');
    if (bioEl) {
      bioEl.innerHTML = renderBiometricStatus(biometric);
    }

    // Leave balances
    const balancesEl = $('#empBalances');
    if (balances.length > 0) {
      balancesEl.innerHTML = balances.map(b => `
        <div class="balance-item">
          <div class="balance-type">${escapeHtml(b.leave_type)}</div>
          <div class="balance-value">${escapeHtml(b.remaining)}</div>
          <div class="balance-total">${escapeHtml(t('balances.ofDays', { total: b.total }))}</div>
        </div>
      `).join('');
    } else {
      balancesEl.innerHTML = `<p style="color:var(--muted);font-size:13px;">${t('info.balances.none')}</p>`;
    }

    $('#employeeInfoResult').classList.remove('hidden');
    $('#infoStatus').classList.add('hidden');
  } catch (error) {
    setStatus('#infoStatus', error.message, true);
  }
}

// ── H2: Announcements ────────────────────────────────
async function loadAnnouncements() {
  const container = $('#announcementsList');
  container.innerHTML = `<p style="text-align:center;padding:20px;color:var(--muted);">${t('announcements.loading')}</p>`;

  try {
    const data = await kioskApi('/announcements');
    const items = data.data || [];

    if (items.length === 0) {
      container.innerHTML = `<p style="text-align:center;padding:40px 0;color:var(--muted);">${t('announcements.empty')}</p>`;
      return;
    }

    container.innerHTML = items.map(a => {
      const priority = escapeHtml(a.priority);
      return `
        <div class="announcement ${priority}" role="article">
          <span class="ann-priority ${priority}">${priority}</span>
          <div class="ann-title">${escapeHtml(a.title)}</div>
          <div class="ann-body">${escapeHtml(a.body)}</div>
        </div>
      `;
    }).join('');
  } catch (error) {
    container.innerHTML = `<p style="text-align:center;padding:20px;color:#fecdd3;">${t('announcements.error', { message: escapeHtml(error.message) })}</p>`;
  }
}

// ── H3: Leave Balance ────────────────────────────────
async function searchLeaveBalance() {
  const identifier = $('#leaveIdentifier').value.trim();
  if (!identifier) {
    setStatus('#leaveStatus', t('error.identifierRequiredShort'), true);
    return;
  }
  setStatus('#leaveStatus', t('search.inProgress'));
  $('#leaveResult').classList.add('hidden');

  try {
    const data = await kioskApi('/leave-balance', {
      method: 'POST',
      body: JSON.stringify({ identifier }),
    });

    const result = data.data;
    $('#leaveEmpName').textContent = result.employee_name || '-';
    $('#leaveYear').textContent = result.year || new Date().getFullYear();

    const balances = result.balances || [];
    const container = $('#leaveBalances');

    if (balances.length > 0) {
      container.innerHTML = balances.map(b => `
        <div class="balance-item">
          <div class="balance-type">${escapeHtml(b.leave_type)}</div>
          <div class="balance-value">${escapeHtml(b.remaining)}</div>
          <div class="balance-total">${escapeHtml(t('leave.balances.usedOfTotal', { used: b.used, total: b.total }))}</div>
        </div>
      `).join('');
    } else {
      container.innerHTML = `<p style="color:var(--muted);font-size:13px;">${t('leave.balances.none')}</p>`;
    }

    $('#leaveResult').classList.remove('hidden');
    $('#leaveStatus').classList.add('hidden');
  } catch (error) {
    setStatus('#leaveStatus', error.message, true);
  }
}

// ── H4: QR Code Punch ────────────────────────────────
async function submitQrPunch(action) {
  const qrData = $('#qrDataInput').value.trim();
  if (!qrData) {
    setStatus('#qrStatus', t('error.qrDataRequired'), true);
    return;
  }
  setStatus('#qrStatus', t('search.inProgress'));

  try {
    const data = await kioskApi('/qr-punch', {
      method: 'POST',
      body: JSON.stringify({ qr_data: qrData, action }),
    });

    const result = data.data;
    const timeStr = action === 'check_in'
      ? formatTime(result.check_in)
      : formatTime(result.check_out);
    const label = action === 'check_in' ? t('qr.entry') : t('qr.exit');
    setStatus('#qrStatus', t('qr.confirmed', { label, time: timeStr, employeeId: result.employee_id }));
    $('#qrDataInput').value = '';
  } catch (error) {
    setStatus('#qrStatus', error.message, true);
  }
}

// ── Helpers ──────────────────────────────────────────
const INTL_LOCALES = { fr: 'fr-FR', en: 'en-US', tr: 'tr-TR', ar: 'ar-SA' };

function intlLocale() {
  return INTL_LOCALES[window.KioskI18n.getLang()] || 'fr-FR';
}

function formatTime(isoString) {
  if (!isoString) return '-';
  try {
    const d = new Date(isoString);
    return d.toLocaleTimeString(intlLocale(), { hour: '2-digit', minute: '2-digit' });
  } catch {
    return isoString.substring(11, 16) || '-';
  }
}

function formatDateTime(isoString) {
  if (!isoString) return '-';
  try {
    return new Date(isoString).toLocaleString(intlLocale(), {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return String(isoString);
  }
}

// PA2-KIO-004: render the employee's biometric enrollment consent/status
// badge on the kiosk employee-info screen (enabled / pending / rejected / none).
function renderBiometricStatus(biometric) {
  if (!biometric) {
    return `<span class="att-badge att-pending">${t('info.biometric.unavailable')}</span>`;
  }

  const badges = [];

  if (biometric.face_enabled) {
    badges.push(`<span class="att-badge att-in">${t('info.biometric.faceEnabled')}</span>`);
  }

  if (biometric.fingerprint_enabled) {
    badges.push(`<span class="att-badge att-in">${t('info.biometric.fingerprintEnabled')}</span>`);
  }

  if (biometric.pending_request) {
    badges.push(`<span class="att-badge att-pending">${t('info.biometric.pending')}</span>`);
  } else if (biometric.latest_request_status === 'rejected') {
    badges.push(`<span class="att-badge att-out">${t('info.biometric.rejected')}</span>`);
  }

  if (badges.length === 0) {
    badges.push(`<span class="att-badge att-pending">${t('info.biometric.none')}</span>`);
  }

  return badges.join('');
}

function escapeHtml(str) {
  if (str == null) return '';
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(String(str)));
  return div.innerHTML;
}

// Audit #1701 : n'autorise que les URLs d'image sûres (http/https, chemin
// relatif ou data:image) — jamais javascript: ni protocole arbitraire.
function safeImageUrl(url) {
  if (url == null) return '';
  const value = String(url);
  if (/^(https?:|\/|data:image\/)/i.test(value)) return escapeHtml(value);
  return '';
}

// ── ZKTeco Bridge (hardware interface) ───────────────
window.ZKTecoBridge = {
  submitIdentifier(value, action = 'check_in', biometricType = 'fingerprint') {
    els.identifier.value = value || '';
    els.biometricType.value = biometricType || 'fingerprint';
    return submitPunch(action);
  },
  fillIdentifier(value) {
    els.identifier.value = value || '';
    els.identifier.focus();
  },
  showEmployeeInfo(identifier) {
    $$('.tab').forEach(tabEl => { tabEl.classList.remove('active'); tabEl.setAttribute('aria-selected', 'false'); });
    $$('.tab-panel').forEach(p => p.classList.remove('active'));
    const infoTab = document.querySelector('[data-tab="info"]');
    if (infoTab) { infoTab.classList.add('active'); infoTab.setAttribute('aria-selected', 'true'); }
    $('#panel-info').classList.add('active');
    $('#infoIdentifier').value = identifier || '';
    searchEmployeeInfo();
  },
};

// ── Event Listeners ──────────────────────────────────
function init() {
  window.KioskI18n.applyStaticTranslations();
  window.KioskI18n.initLangSelector('langSelect');
  document.addEventListener('leopardo:lang-changed', () => {
    if (state.status) renderStatus();
  });

  initTabs();

  // Punch
  els.checkInBtn.addEventListener('click', () => submitPunch('check_in'));
  els.checkOutBtn.addEventListener('click', () => submitPunch('check_out'));

  // PA2-KIO-003: actionable sync retry button next to the status pill
  if (els.syncRetryBtn) {
    els.syncRetryBtn.addEventListener('click', retrySync);
  }
  els.identifier.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); submitPunch('check_in'); }
  });

  // H1: Employee info
  $('#infoSearchBtn').addEventListener('click', searchEmployeeInfo);
  $('#infoIdentifier').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); searchEmployeeInfo(); }
  });

  // H3: Leave balance
  $('#leaveSearchBtn').addEventListener('click', searchLeaveBalance);
  $('#leaveIdentifier').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); searchLeaveBalance(); }
  });

  // H4: QR punch
  $('#qrCheckInBtn').addEventListener('click', () => submitQrPunch('check_in'));
  $('#qrCheckOutBtn').addEventListener('click', () => submitQrPunch('check_out'));
  $('#qrDataInput').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); submitQrPunch('check_in'); }
  });

  // Demo access
  initDemoAccess();

  // Initial loads
  refreshStatus();
  setInterval(refreshStatus, CONFIG.refreshInterval);

  // Keyboard navigation for tabs
  $$('.tab').forEach(tab => {
    tab.addEventListener('keydown', (event) => {
      const tabs = [...$$('.tab')];
      const idx = tabs.indexOf(tab);
      if (event.key === 'ArrowRight' && idx < tabs.length - 1) {
        event.preventDefault(); tabs[idx + 1].focus(); tabs[idx + 1].click();
      }
      if (event.key === 'ArrowLeft' && idx > 0) {
        event.preventDefault(); tabs[idx - 1].focus(); tabs[idx - 1].click();
      }
    });
  });
}

// ── Demo Access ──────────────────────────────────────
const DEMO_COMPANIES = [
  {
    name: 'TechCorp Algerie SARL', country: 'DZ',
    employees: [
      { matricule: 'DZ-EMP-001', name: 'Ahmed Benali', email: 'ahmed.benali@techcorp-algerie.dz', role: 'principal' },
      { matricule: 'DZ-EMP-002', name: 'Fatima Meziane', email: 'fatima.meziane@techcorp-algerie.dz', role: 'rh' },
      { matricule: 'DZ-EMP-003', name: 'Karim Aouad', email: 'karim.aouad@techcorp-algerie.dz', role: 'employee' },
    ],
  },
  {
    name: 'PharmaPlus Casablanca', country: 'MA',
    employees: [
      { matricule: 'MA-EMP-001', name: 'Amina Tahiri', email: 'amina.tahiri@pharmaplus.ma', role: 'principal' },
      { matricule: 'MA-EMP-002', name: 'Sara Mansouri', email: 'sara.mansouri@pharmaplus.ma', role: 'rh' },
      { matricule: 'MA-EMP-003', name: 'Youssef Bennani', email: 'youssef.bennani@pharmaplus.ma', role: 'employee' },
    ],
  },
  {
    name: 'DigitalFlow Tunis', country: 'TN',
    employees: [
      { matricule: 'TN-EMP-001', name: 'Sofiane Mrad', email: 'sofiane.mrad@digitalflow.tn', role: 'principal' },
      { matricule: 'TN-EMP-002', name: 'Olfa Trabelsi', email: 'olfa.trabelsi@digitalflow.tn', role: 'rh' },
      { matricule: 'TN-EMP-003', name: 'Aziz Khelifi', email: 'aziz.khelifi@digitalflow.tn', role: 'employee' },
    ],
  },
];

function initDemoAccess() {
  const overlay = $('#demoOverlay');
  const list = $('#demoUsersList');
  const openBtn = $('#demoAccessBtn');
  const closeBtn = $('#demoCloseBtn');

  if (!overlay || !list || !openBtn) return;

  let html = '';
  for (const company of DEMO_COMPANIES) {
    html += `<div class="demo-company-title">${escapeHtml(company.name)} (${escapeHtml(company.country)})</div>`;
    for (const emp of company.employees) {
      html += `<button class="demo-user-btn" data-matricule="${escapeHtml(emp.matricule)}" data-email="${escapeHtml(emp.email)}">`;
      html += `<span class="demo-user-role">${escapeHtml(emp.role)}</span>`;
      html += `<div class="demo-user-name">${escapeHtml(emp.name)}</div>`;
      html += `<div class="demo-user-email">${escapeHtml(emp.matricule)} · ${escapeHtml(emp.email)}</div>`;
      html += `</button>`;
    }
  }
  list.innerHTML = html;

  openBtn.addEventListener('click', () => overlay.classList.remove('hidden'));
  closeBtn.addEventListener('click', () => overlay.classList.add('hidden'));
  overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.add('hidden'); });

  list.addEventListener('click', (e) => {
    const btn = e.target.closest('.demo-user-btn');
    if (!btn) return;
    const matricule = btn.dataset.matricule;
    if (els.identifier) els.identifier.value = matricule;
    const infoId = $('#infoIdentifier');
    if (infoId) infoId.value = matricule;
    const leaveId = $('#leaveIdentifier');
    if (leaveId) leaveId.value = matricule;
    overlay.classList.add('hidden');
    setStatus('#statusBox', t('demo.selected', { matricule }));
  });
}

document.addEventListener('DOMContentLoaded', init);
