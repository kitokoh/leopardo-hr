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

// ── State ────────────────────────────────────────────
const state = {
  status: null,
  currentTab: 'punch',
};

// ── Selectors ────────────────────────────────────────
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const els = {
  companyName: $('#companyName'),
  locationLabel: $('#locationLabel'),
  deviceCode: $('#deviceCode'),
  queueCount: $('#queueCount'),
  identifier: $('#identifier'),
  biometricType: $('#biometricType'),
  statusBox: $('#statusBox'),
  syncDot: $('#syncDot'),
  syncLabel: $('#syncLabel'),
  checkInBtn: $('#checkInButton'),
  checkOutBtn: $('#checkOutButton'),
};

// ── Utilities ────────────────────────────────────────
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
    throw new Error(payload.error || payload.message || `Erreur ${response.status}`);
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

// ── Tab Navigation ───────────────────────────────────
function initTabs() {
  $$('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.tab;
      $$('.tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
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
  els.companyName.textContent = state.status.company_name || 'Leopardo RH Client';
  els.locationLabel.textContent = state.status.location_label || 'Entree principale';
  els.deviceCode.textContent = state.status.device_code || '-';
  els.queueCount.textContent = `${state.status.queue_count || 0} evenement(s) en attente`;

  const ok = state.status.online === true;
  els.syncDot.classList.toggle('ok', ok);
  els.syncDot.classList.toggle('bad', !ok);
  els.syncLabel.textContent = ok
    ? 'Connexion OK - synchronisation auto active'
    : `Mode offline - sync plus tard (${state.status.last_error || 'reseau indisponible'})`;
}

async function refreshStatus() {
  try {
    const payload = await fetchJson(`${CONFIG.localBridgeUrl}/status`);
    state.status = payload.data;
    renderStatus();
  } catch (error) {
    setStatus('#statusBox', error.message || 'Bridge local indisponible.', true);
  }
}

async function submitPunch(action) {
  const identifier = els.identifier.value.trim();
  if (!identifier) {
    setStatus('#statusBox', 'Veuillez saisir ou scanner un identifiant employe.', true);
    return;
  }
  setStatus('#statusBox', 'Enregistrement local du pointage...');
  try {
    const payload = await fetchJson(`${CONFIG.localBridgeUrl}/punch`, {
      method: 'POST',
      body: JSON.stringify({
        identifier,
        action,
        biometric_type: els.biometricType.value,
      }),
    });
    const mode = payload.data.sync_status === 'synced' ? 'synchronise' : 'stocke hors ligne';
    setStatus('#statusBox', `Pointage ${mode} pour ${identifier}.`);
    els.identifier.value = '';
    els.identifier.focus();
    await refreshStatus();
  } catch (error) {
    setStatus('#statusBox', error.message || 'Echec de pointage.', true);
  }
}

// ── H1: Employee Info Post-Punch ─────────────────────
async function searchEmployeeInfo() {
  const identifier = $('#infoIdentifier').value.trim();
  if (!identifier) {
    setStatus('#infoStatus', 'Veuillez saisir un identifiant.', true);
    return;
  }
  setStatus('#infoStatus', 'Recherche en cours...');
  $('#employeeInfoResult').classList.add('hidden');

  try {
    const data = await kioskApi('/employee-info', {
      method: 'POST',
      body: JSON.stringify({ identifier }),
    });

    const emp = data.data.employee;
    const att = data.data.today_attendance;
    const balances = data.data.leave_balances || [];

    // Avatar
    const avatarEl = $('#empAvatar');
    if (emp.photo_url) {
      avatarEl.innerHTML = `<img src="${emp.photo_url}" alt="${emp.name}" />`;
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
      if (att.check_in) html += `<span class="att-badge att-in">Entree: ${formatTime(att.check_in)}</span>`;
      if (att.check_out) html += `<span class="att-badge att-out">Sortie: ${formatTime(att.check_out)}</span>`;
      if (!att.check_in && !att.check_out) html = `<span class="att-badge att-pending">Aucun pointage</span>`;
      attEl.innerHTML = html;
    } else {
      attEl.innerHTML = `<span class="att-badge att-pending">Aucun pointage aujourd'hui</span>`;
    }

    // Leave balances
    const balancesEl = $('#empBalances');
    if (balances.length > 0) {
      balancesEl.innerHTML = balances.map(b => `
        <div class="balance-item">
          <div class="balance-type">${b.leave_type}</div>
          <div class="balance-value">${b.remaining}</div>
          <div class="balance-total">sur ${b.total} jours</div>
        </div>
      `).join('');
    } else {
      balancesEl.innerHTML = '<p style="color:var(--muted);font-size:13px;">Aucun solde disponible</p>';
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
  container.innerHTML = '<p style="text-align:center;padding:20px;color:var(--muted);">Chargement...</p>';

  try {
    const data = await kioskApi('/announcements');
    const items = data.data || [];

    if (items.length === 0) {
      container.innerHTML = '<p style="text-align:center;padding:40px 0;color:var(--muted);">Aucune annonce active pour le moment.</p>';
      return;
    }

    container.innerHTML = items.map(a => `
      <div class="announcement ${a.priority}" role="article">
        <span class="ann-priority ${a.priority}">${a.priority}</span>
        <div class="ann-title">${escapeHtml(a.title)}</div>
        <div class="ann-body">${escapeHtml(a.body)}</div>
      </div>
    `).join('');
  } catch (error) {
    container.innerHTML = `<p style="text-align:center;padding:20px;color:#fecdd3;">Erreur: ${escapeHtml(error.message)}</p>`;
  }
}

// ── H3: Leave Balance ────────────────────────────────
async function searchLeaveBalance() {
  const identifier = $('#leaveIdentifier').value.trim();
  if (!identifier) {
    setStatus('#leaveStatus', 'Veuillez saisir un identifiant.', true);
    return;
  }
  setStatus('#leaveStatus', 'Recherche en cours...');
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
          <div class="balance-type">${b.leave_type}</div>
          <div class="balance-value">${b.remaining}</div>
          <div class="balance-total">${b.used} utilise(s) sur ${b.total}</div>
        </div>
      `).join('');
    } else {
      container.innerHTML = '<p style="color:var(--muted);font-size:13px;">Aucun solde conges configure.</p>';
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
    setStatus('#qrStatus', 'Veuillez scanner ou coller les donnees du QR code.', true);
    return;
  }
  setStatus('#qrStatus', 'Traitement du QR code...');

  try {
    const data = await kioskApi('/qr-punch', {
      method: 'POST',
      body: JSON.stringify({ qr_data: qrData, action }),
    });

    const result = data.data;
    const timeStr = action === 'check_in'
      ? formatTime(result.check_in)
      : formatTime(result.check_out);
    const label = action === 'check_in' ? 'Entree' : 'Sortie';
    setStatus('#qrStatus', `${label} enregistree a ${timeStr} pour employe #${result.employee_id}.`);
    $('#qrDataInput').value = '';
  } catch (error) {
    setStatus('#qrStatus', error.message, true);
  }
}

// ── Helpers ──────────────────────────────────────────
function formatTime(isoString) {
  if (!isoString) return '-';
  try {
    const d = new Date(isoString);
    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  } catch {
    return isoString.substring(11, 16) || '-';
  }
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
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
    $$('.tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
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
  initTabs();

  // Punch
  els.checkInBtn.addEventListener('click', () => submitPunch('check_in'));
  els.checkOutBtn.addEventListener('click', () => submitPunch('check_out'));
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
    html += `<div class="demo-company-title">${company.name} (${company.country})</div>`;
    for (const emp of company.employees) {
      html += `<button class="demo-user-btn" data-matricule="${emp.matricule}" data-email="${emp.email}">`;
      html += `<span class="demo-user-role">${emp.role}</span>`;
      html += `<div class="demo-user-name">${emp.name}</div>`;
      html += `<div class="demo-user-email">${emp.matricule} · ${emp.email}</div>`;
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
    setStatus('#statusBox', `Employe demo selectionne : ${matricule}. Cliquez Pointer entree ou sortie.`);
  });
}

document.addEventListener('DOMContentLoaded', init);
