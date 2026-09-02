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
  // BIO-009 #6774 — action par defaut (config.example.json defaultAction)
  defaultAction: window.__KIOSK_DEFAULT_ACTION || 'check_in',
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
  // #5120 — méthodes de pointage autorisées (null = toutes)
  punchMethods: null,
  // BIO-009 #6774 — flux multi-méthodes
  serverMethods: null,      // matrice serveur GET /kiosks/{code}/config (autoritative)
  bridgeMethods: null,      // matrice locale /local/status (repli hors-ligne)
  screen: 'home',           // home|fingerprint|badge|pin|manager|face-id|face-camera|face-done|face-fail|face-offline
  lastMethod: null,         // méthode précédente (retour rapide)
  roster: [],               // cache employés (cloud /roster, repli bridge)
  rosterLoaded: false,
  faceStream: null,         // MediaStream caméra (visage)
  faceBusy: false,
  faceIdentifier: '',
  faceEmployee: null,
  faceAction: null,
  faceRoster: [],           // liste affichée pour le choix (visage activé)
  cameraError: null,
  faceSuccess: null,
  faceFailure: null,
  pendingIdentifier: '',   // identifiant scanné avant le choix d'une méthode
  matrixPending: true,      // /config pas encore résolue (écran de chargement)
  prevScreen: null,
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
  statusBox: $('#statusBox'),
  syncDot: $('#syncDot'),
  syncLabel: $('#syncLabel'),
  syncRetryBtn: $('#syncRetryBtn'),
  // BIO-009 — flux multi-méthodes
  punchStage: $('#punchStage'),
  methodGrid: $('#methodGrid'),
  noMethodWarning: $('#noMethodWarning'),
  demoAccessBtn: $('#demoAccessBtn'),
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

// #3586 — le bridge local exige desormais le token de session injecte dans le
// HTML servi (window.__LOCAL_BRIDGE_TOKEN) via le header X-Local-Bridge-Token.
// Reserve aux appels `/local/*` : ne jamais l'envoyer vers l'API cloud.
async function localFetchJson(url, options = {}) {
  return fetchJson(url, {
    ...options,
    headers: {
      'X-Local-Bridge-Token': window.__LOCAL_BRIDGE_TOKEN || '',
      ...(options.headers || {}),
    },
  });
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

/**
 * BIO-009 — appel brut vers l'API kiosque : retourne {status, payload} sans
 * lever d'exception pour les réponses HTTP. Nécessaire pour /punch et
 * /verify-face dont les échecs sont STRUCTURÉS (code machine, reason_code,
 * fallback_methods) — fetchJson jetterait ces informations.
 */
async function rawKioskJson(path, options = {}) {
  const apiBaseUrl = (CONFIG.apiBaseUrl || '').replace(/\/$/, '');
  const versionedBaseUrl = apiBaseUrl.endsWith('/api/v1') ? apiBaseUrl : `${apiBaseUrl}/api/v1`;
  const url = `${versionedBaseUrl}/kiosks/${CONFIG.deviceCode}${path}`;
  const isForm = options.form === true;
  const response = await fetch(url, {
    method: options.method || 'GET',
    body: options.body,
    headers: {
      'Accept': 'application/json',
      ...(isForm ? {} : { 'Content-Type': 'application/json' }),
      'X-Kiosk-Token': CONFIG.kioskToken,
      ...(options.headers || {}),
    },
  });
  const payload = await response.json().catch(() => ({}));
  return { status: response.status, payload };
}

/** POST multipart (capture visage, BIO-004). Ne JAMAIS forcer un
 * Content-Type : fetch pose la boundary multipart lui-même. */
async function kioskForm(path, formData) {
  return rawKioskJson(path, { method: 'POST', body: formData, form: true });
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

/** Désactive/active tous les boutons d'action du flux courant. */
function setPunchButtonsDisabled(disabled) {
  const stage = els.punchStage;
  if (!stage) return;
  stage.querySelectorAll('[data-action="punch"], [data-action="face-start"], [data-action="face-capture"], [data-action="start-method"]').forEach((button) => {
    button.disabled = disabled;
    button.setAttribute('aria-disabled', String(disabled));
  });
}

function hasKioskConfiguration() {
  return Boolean((CONFIG.apiBaseUrl || '').trim() && (CONFIG.deviceCode || '').trim());
}

function setUnconfiguredState() {
  els.companyName.textContent = t('config.unconfigured.title');
  els.locationLabel.textContent = t('config.unconfigured.description');
  els.syncDot.classList.remove('ok');
  els.syncDot.classList.add('bad');
  els.syncLabel.textContent = t('config.unconfigured.sync');
  if (els.lastSyncAt) els.lastSyncAt.textContent = t('config.unconfigured.lastSync');
  setStatus('#statusBox', t('config.unconfigured.description'), true);
  state.matrixPending = false;
  if (els.methodGrid) els.methodGrid.innerHTML = '';
  setPunchButtonsDisabled(true);
  ['#demoAccessBtn', '#infoSearchBtn', '#leaveSearchBtn', '#qrCheckInBtn', '#qrCheckOutBtn', '#syncRetryBtn']
    .map((selector) => $(selector))
    .filter(Boolean)
    .forEach((button) => {
      button.disabled = true;
      button.setAttribute('aria-disabled', 'true');
    });
}

async function findLocalRosterEmployee(identifier) {
  try {
    const payload = await localFetchJson(`${CONFIG.localBridgeUrl}/roster`);
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
      const leavingPunch = state.currentTab === 'punch' && target !== 'punch';
      const enteringPunch = target === 'punch' && state.currentTab !== 'punch';

      $$('.tab').forEach(tabEl => { tabEl.classList.remove('active'); tabEl.setAttribute('aria-selected', 'false'); });
      $$('.tab-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      const panel = $(`#panel-${target}`);
      if (panel) panel.classList.add('active');
      state.currentTab = target;

      // BIO-009 — couper la caméra dès qu'on quitte l'onglet pointage.
      if (leavingPunch) stopFaceCamera();
      if (enteringPunch && state.screen === 'face-camera' && !state.faceStream && !state.faceBusy && !state.cameraError) {
        startFaceCamera();
      }

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
    const payload = await localFetchJson(`${CONFIG.localBridgeUrl}/status`);
    state.status = payload.data;
    state.lastStatusRefreshAt = new Date().toISOString();
    // BIO-009 — la matrice LOCALE (config.json du pont) sert de repli
    // hors-ligne ; la matrice serveur (/config) reste autoritative.
    if (Array.isArray(payload.data && payload.data.punch_methods)) {
      state.bridgeMethods = payload.data.punch_methods;
    } else {
      state.bridgeMethods = null; // null = toutes méthodes (rétro-compat)
    }
    maybeRenderMatrix();
    renderStatus();
  } catch (error) {
    setStatus('#statusBox', error.message || t('error.bridgeUnavailable'), true);
    if (els.lastSyncAt) {
      els.lastSyncAt.textContent = t('error.bridgeUnavailableShort');
    }
  }
}

// ═══════════════════════════════════════════════════════════════════
// BIO-009 #6774 — Interface kiosque de pointage multi-methodes
//
// La matrice des methodes est pilotee par le serveur (BIO-006) :
//   GET /api/v1/kiosks/{deviceCode}/config → data.punch_methods
// (fingerprint|face|badge|pin|manager). L'ecran d'accueil ne propose QUE
// les methodes activees. Chaque methode ouvre un flux guide :
//   fingerprint → pont local /local/punch (offline-first, inchange) ;
//   badge / pin → POST /punch (matrice + audit serveur) ; si le reseau
//                 coupe, repli sur la file du pont local (method card
//                 pour badge, legacy pour pin) ;
//   manager     → POST /punch + manager_employee_id ; JAMAIS mis en file
//                 hors-ligne (une validation manager sans le manager
//                 perdrait sa valeur d'audit) ;
//   face        → identification (badge/matricule ou roster) → capture
//                 camera → POST /verify-face (BIO-004) avec gestion de
//                 chaque etat (qualite, liveness, rejet, provider) et
//                 methodes de repli proposees par la reponse.
//
// Regle de confirmation : un pointage n'est jamais presente comme
// « confirme » sans confirmation backend (2xx) ; en cas de file locale,
// le message dit explicitement « stocke hors ligne, sync en attente ».
// ═══════════════════════════════════════════════════════════════════

const METHOD_ORDER = ['fingerprint', 'face', 'badge', 'pin', 'manager'];
const METHOD_META = {
  fingerprint: { icon: '&#128400;' },
  face: { icon: '&#128066;' },
  badge: { icon: '&#127991;' },
  pin: { icon: '&#128272;' },
  manager: { icon: '&#129309;' },
};

const FINGERPRINT_SVG = `
<svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M60 64c0 15-6 25-15 34" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
  <path d="M60 64c0 15 6 25 15 34" stroke="currentColor" stroke-width="5" stroke-linecap="round" opacity=".7"/>
  <path d="M40 58c0-12 9-21 20-21s20 9 20 21" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
  <path d="M29 69c-2-6-3-12-2-18 3-18 17-31 33-31s30 13 33 31c1 6 0 12-2 18" stroke="currentColor" stroke-width="5" stroke-linecap="round" opacity=".62"/>
  <path d="M21 87c-8-16-9-32-3-47C25 22 41 10 60 10s35 12 42 30c6 15 5 31-3 47" stroke="currentColor" stroke-width="5" stroke-linecap="round" opacity=".4"/>
  <path d="M47 78c3-5 4-10 4-16 0-6 4-10 9-10s9 4 9 10c0 6 1 11 4 16" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
</svg>`;

/** `card` (vocabulaire ZKTeco / legacy) === `badge` (domaine ATT-002). */
function normalizeMethod(method) {
  const value = String(method || '').trim().toLowerCase();
  if (value === 'card') return 'badge';
  return METHOD_ORDER.includes(value) ? value : null;
}

function normalizeMethodList(methods) {
  if (!Array.isArray(methods)) return null;
  const seen = [];
  for (const method of methods) {
    const normalized = normalizeMethod(method);
    if (normalized && !seen.includes(normalized)) seen.push(normalized);
  }
  return seen.length ? seen : null;
}

/** Matrice effective : serveur (autoritative) → bridge local → defaut. */
function allowedMethods() {
  const server = normalizeMethodList(state.serverMethods);
  if (server) return server;
  const bridge = normalizeMethodList(state.bridgeMethods);
  if (bridge) return bridge;
  return [...METHOD_ORDER];
}

let _matrixKey = null;
function maybeRenderMatrix() {
  if (state.screen !== 'home') return;
  const key = JSON.stringify(allowedMethods());
  if (key !== _matrixKey) {
    _matrixKey = key;
    renderMethodGrid();
  }
}

function renderMethodGrid() {
  const grid = els.methodGrid;
  if (!grid) return;
  const methods = allowedMethods();

  if (state.matrixPending && !state.bridgeMethods && !Array.isArray(state.serverMethods)) {
    grid.innerHTML = `<p class="stage-copy" style="padding:26px 0;text-align:center;">${escapeHtml(t('bio.methods.loading'))}</p>`;
    return;
  }

  if (methods.length === 0) {
    grid.innerHTML = '';
    if (els.noMethodWarning) els.noMethodWarning.classList.remove('hidden');
    return;
  }
  if (els.noMethodWarning) els.noMethodWarning.classList.add('hidden');

  grid.innerHTML = methods
    .map((method) => `
      <button type="button" class="method-btn" data-action="start-method" data-method="${method}">
        <span class="m-icon" aria-hidden="true">${METHOD_META[method] ? METHOD_META[method].icon : '&#10033;'}</span>
        <span class="m-name">${escapeHtml(t(`method.${method}`))}</span>
        <span class="m-tag">${escapeHtml(t(`method.tagline.${method}`))}</span>
      </button>`)
    .join('');
}

/** Charge la matrice serveur au boot (timeout : repli matrice locale). */
async function loadKioskConfig() {
  state.matrixPending = true;
  renderMethodGrid();

  if (!hasKioskConfiguration()) {
    state.matrixPending = false;
    state.serverMethods = null;
    maybeRenderMatrix();
    return;
  }

  let timedOut = false;
  try {
    const controller = new AbortController();
    const timer = window.setTimeout(() => { timedOut = true; controller.abort(); }, 4000);
    const apiBaseUrl = (CONFIG.apiBaseUrl || '').replace(/\/$/, '');
    const base = apiBaseUrl.endsWith('/api/v1') ? apiBaseUrl : `${apiBaseUrl}/api/v1`;
    const response = await fetch(`${base}/kiosks/${CONFIG.deviceCode}/config`, {
      headers: { 'Accept': 'application/json', 'X-Kiosk-Token': CONFIG.kioskToken },
      signal: controller.signal,
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      // DEVICE_REVOKED / INVALID_KIOSK_TOKEN etc. : la borne est inutilisable,
      // le message reste visible dans la statusBox (les pointages seront de
      // toute facon refuses par le serveur avec le meme code).
      const code = payload.error || payload.message || String(response.status);
      setStatus('#statusBox', friendlyServerCode(code), true);
      throw new Error(code);
    }
    window.clearTimeout(timer);
    state.serverMethods = Array.isArray(payload.data && payload.data.punch_methods)
      ? payload.data.punch_methods
      : null;
  } catch (error) {
    // Hors-ligne / timeout : matrice locale bridge ou defaut. Ne jamais
    // bloquer la borne sur un echec de /config.
    if (!timedOut && !(error instanceof TypeError)) {
      /* erreur HTTP deja affichee ci-dessus */
    }
  } finally {
    state.matrixPending = false;
    // Securite : meme si la config revient plus tard, ne pas re-afficher un
    // etat « pending » indefini.
    window.clearTimeout(window.__kioskMatrixFallbackTimer);
    maybeRenderMatrix();
  }
}

/** Repli si /config et /local/status sont tous deux injoignables. */
function scheduleMatrixFallback() {
  window.clearTimeout(window.__kioskMatrixFallbackTimer);
  window.__kioskMatrixFallbackTimer = window.setTimeout(() => {
    state.matrixPending = false;
    maybeRenderMatrix();
  }, 6000);
}

// ── Roster (affichage des noms + choix « face ») ────────────────
function normalizeRosterEmployee(employee) {
  if (!employee || typeof employee !== 'object') return null;
  return {
    employee_id: employee.employee_id,
    name: employee.name || '',
    email: employee.email || '',
    matricule: employee.matricule || '',
    zkteco_id: employee.zkteco_id || '',
    badge_number: employee.badge_number || '',
    face_enabled: Boolean(employee.face_enabled),
    fingerprint_enabled: Boolean(employee.fingerprint_enabled),
  };
}

async function ensureRoster() {
  if (state.rosterLoaded) return state.roster;
  let list = [];
  let sourceOk = false;
  try {
    const payload = await kioskApi('/roster');
    list = (payload.data && payload.data.employees) || [];
    sourceOk = true;
  } catch {
    try {
      const payload = await localFetchJson(`${CONFIG.localBridgeUrl}/roster`);
      list = payload.data || [];
      sourceOk = true;
    } catch { /* pas de roster local */ }
  }
  if (sourceOk) {
    state.roster = list.map(normalizeRosterEmployee).filter(Boolean);
    state.rosterLoaded = true;
  }
  return state.roster;
}

function resolveEmployeeInRoster(identifier, roster) {
  const query = String(identifier || '').trim();
  if (!query) return null;
  const needle = query.toLowerCase();
  return roster.find((employee) => {
    return [employee.matricule, employee.email, employee.zkteco_id, employee.badge_number, employee.name, employee.employee_id]
      .filter((value) => value !== null && value !== undefined && value !== '')
      .some((value) => String(value).toLowerCase() === needle);
  }) || null;
}

function resolveEmployeeByEmployeeId(employeeId, roster) {
  return roster.find((employee) => String(employee.employee_id) === String(employeeId)) || null;
}

// ── Ecrans du flux (templates) ──────────────────────────────────
function screenElId(name) {
  return name === 'home' ? 'punchScreenHome' : `punchScreen-${name}`;
}

function tplNavHome() {
  return `<button type="button" class="nav-chip" data-action="home" aria-label="${escapeHtml(t('bio.home'))}"><span aria-hidden="true">&#8592;</span> ${escapeHtml(t('bio.home'))}</button>`;
}

function tplActionRow(method, actionAttr = 'punch') {
  return `
    <div class="action-row">
      <button type="button" class="primary btn-action" data-action="${actionAttr}" data-method="${method}" data-kind="check_in" aria-label="${escapeHtml(t('checkIn.aria'))}">${escapeHtml(t('checkIn.label'))}</button>
      <button type="button" class="secondary btn-action" data-action="${actionAttr}" data-method="${method}" data-kind="check_out" aria-label="${escapeHtml(t('checkOut.aria'))}">${escapeHtml(t('checkOut.label'))}</button>
    </div>`;
}

function tplFingerprint() {
  return `
    <div class="flow-nav">${tplNavHome()}</div>
    <div class="punch-split">
      <div class="biometric-stage">
        <div class="fingerprint" aria-hidden="true">${FINGERPRINT_SVG}</div>
        <h2 class="stage-title">${escapeHtml(t('stage.title'))}</h2>
        <p class="stage-copy">${escapeHtml(t('stage.copy'))}</p>
      </div>
      <div class="card">
        <div class="field-stack">
          <div>
            <label for="fpId">${escapeHtml(t('identifier.label'))}</label>
            <input id="fpId" name="identifier" autocomplete="off" placeholder="${escapeHtml(t('identifier.placeholder'))}" aria-label="${escapeHtml(t('identifier.aria'))}" data-enter-method="fingerprint" data-enter-kind="check_in">
          </div>
        </div>
        ${tplActionRow('fingerprint')}
      </div>
    </div>`;
}

function tplBadge() {
  return `
    <div class="flow-nav">${tplNavHome()}</div>
    <div class="card">
      <h2 class="h-screen">${escapeHtml(t('badge.title'))}</h2>
      <p class="screen-copy">${escapeHtml(t('badge.copy'))}</p>
      <div class="field-stack">
        <div>
          <label for="badgeId">${escapeHtml(t('card.badge.label'))}</label>
          <input id="badgeId" name="badge_number" type="text" inputmode="numeric" autocomplete="off" placeholder="${escapeHtml(t('card.badge.placeholder'))}" aria-label="${escapeHtml(t('card.badge.aria'))}" data-enter-method="badge" data-enter-kind="check_in">
        </div>
      </div>
      ${tplActionRow('badge')}
    </div>`;
}

function tplPin() {
  return `
    <div class="flow-nav">${tplNavHome()}</div>
    <div class="card">
      <h2 class="h-screen">${escapeHtml(t('pin.title'))}</h2>
      <p class="screen-copy">${escapeHtml(t('pin.copy'))}</p>
      <div class="field-stack">
        <div>
          <label for="pinId">${escapeHtml(t('pin.label'))}</label>
          <input id="pinId" name="pin" type="text" inputmode="numeric" autocomplete="off" placeholder="${escapeHtml(t('pin.placeholder'))}" aria-label="${escapeHtml(t('pin.aria'))}" data-enter-method="pin" data-enter-kind="check_in">
        </div>
      </div>
      ${tplActionRow('pin')}
    </div>`;
}

function tplManager() {
  return `
    <div class="flow-nav">${tplNavHome()}</div>
    <div class="card">
      <h2 class="h-screen">${escapeHtml(t('manager.title'))}</h2>
      <p class="screen-copy">${escapeHtml(t('manager.copy'))}</p>
      <div class="field-stack">
        <div>
          <label for="mgrEmpId">${escapeHtml(t('manager.employee.label'))}</label>
          <input id="mgrEmpId" name="identifier" autocomplete="off" placeholder="${escapeHtml(t('manager.employee.placeholder'))}" aria-label="${escapeHtml(t('manager.employee.label'))}" data-enter-focus="mgrMgrId">
        </div>
        <div>
          <label for="mgrMgrId">${escapeHtml(t('manager.manager.label'))}</label>
          <input id="mgrMgrId" name="manager_badge" type="text" autocomplete="off" placeholder="${escapeHtml(t('manager.manager.placeholder'))}" aria-label="${escapeHtml(t('manager.manager.aria'))}" data-enter-method="manager" data-enter-kind="check_in">
        </div>
      </div>
      ${tplActionRow('manager')}
    </div>`;
}

function tplFaceId() {
  return `
    <div class="flow-nav">${tplNavHome()}</div>
    <div class="card">
      <h2 class="h-screen">${escapeHtml(t('face.id.title'))}</h2>
      <p class="screen-copy">${escapeHtml(t('face.id.copy'))}</p>
      <div class="field-stack">
        <div>
          <label for="faceId">${escapeHtml(t('identifier.label'))}</label>
          <input id="faceId" name="identifier" autocomplete="off" placeholder="${escapeHtml(t('identifier.placeholder'))}" aria-label="${escapeHtml(t('identifier.aria'))}" data-enter-method="face" data-enter-kind="check_in">
        </div>
      </div>
      ${tplActionRow('face', 'face-start')}
      <div class="roster-panel">
        <label for="faceRosterSearch">${escapeHtml(t('face.roster.title'))}</label>
        <input id="faceRosterSearch" type="search" autocomplete="off" placeholder="${escapeHtml(t('face.roster.search'))}" aria-label="${escapeHtml(t('face.roster.search.aria'))}">
        <div id="faceRosterList" class="roster-list" style="margin-top:10px;"></div>
      </div>
    </div>`;
}

function tplFaceCamera() {
  const employeeName = state.faceEmployee && state.faceEmployee.name
    ? state.faceEmployee.name
    : (state.faceIdentifier || '-');
  return `
    <div class="flow-nav">${tplNavHome()}</div>
    <div class="face-emp-banner">
      <span aria-hidden="true">&#128100;</span>
      <span><span id="faceBannerName">${escapeHtml(employeeName)}</span> <span class="dot-id" id="faceBannerId">(${escapeHtml(state.faceIdentifier)})</span> — <span id="faceBannerAction">${escapeHtml(actionLabel(state.faceAction))}</span></span>
    </div>
    <div class="face-cam-wrap" id="faceCamWrap">
      <div class="face-cam">
        <video id="faceVideo" autoplay playsinline muted></video>
        <div class="face-frame" aria-hidden="true"></div>
        <div class="face-cam-busy-tip hidden" id="faceBusyTip">${escapeHtml(t('face.checking'))}</div>
        <div class="face-cam-error hidden" id="faceCamError"></div>
      </div>
    </div>
    <div class="action-row">
      <button type="button" class="primary btn-action" data-action="face-capture" id="faceCaptureBtn">${escapeHtml(t('face.capture', { action: actionLabel(state.faceAction) }))}</button>
    </div>`;
}

function tplFaceDone() {
  const success = state.faceSuccess || {};
  const employeeName = success.name || state.faceIdentifier || '-';
  const time = success.time ? formatTime(success.time) : formatTime(new Date().toISOString());
  return `
    <div class="card" style="text-align:center;padding:34px 24px;">
      <div class="big-icon ok" aria-hidden="true">&#10003;</div>
      <h2 class="h-screen">${escapeHtml(t('face.done.title'))}</h2>
      <p class="stage-copy" style="font-size:1.35rem;font-weight:900;color:#d1fae5;">${escapeHtml(t('face.done.thanks', { name: employeeName }))}</p>
      <p class="stage-copy">${escapeHtml(t('punch.recorded', { action: actionLabel(success.action || 'check_in'), time }))}</p>
      <div class="center-actions">
        <button type="button" class="primary btn-action" data-action="home" style="min-width:220px;">${escapeHtml(t('bio.again'))}</button>
      </div>
    </div>`;
}

function tplFaceFail() {
  const failure = state.faceFailure || {};
  const reasonKey = `face.reason.${failure.reason || 'unknown'}`;
  const reasonText = t(reasonKey) === reasonKey ? t('face.reason.unknown') : t(reasonKey);
  const fallbacks = normalizeMethodList(failure.fallbacks) || allowedMethods().filter((method) => method !== 'face');
  return `
    <div class="card" style="text-align:center;padding:30px 24px;">
      <div class="big-icon ko" aria-hidden="true">&#10007;</div>
      <h2 class="h-screen">${escapeHtml(t('face.fail.title'))}</h2>
      <p class="stage-copy" id="faceFailReason">${escapeHtml(reasonText)}</p>
      <div class="fallback-methods" style="justify-content:center;">
        ${fallbacks.filter((method) => method !== 'face').map((method) => `
          <button type="button" class="fallback-method" data-action="start-method" data-method="${method}">${escapeHtml(t(`method.${method}`))}</button>`).join('')}
      </div>
      <div class="center-actions">
        <button type="button" class="secondary btn-action" data-action="face-retake">${escapeHtml(t('bio.retake'))}</button>
        <button type="button" class="secondary btn-action" data-action="home">${escapeHtml(t('bio.changeMethod'))}</button>
      </div>
    </div>`;
}

function tplFaceOffline() {
  const fallbacks = allowedMethods().filter((method) => method !== 'face');
  return `
    <div class="card" style="text-align:center;padding:30px 24px;">
      <div class="big-icon ko" aria-hidden="true">&#9888;</div>
      <h2 class="h-screen">${escapeHtml(t('face.offline.title'))}</h2>
      <p class="stage-copy">${escapeHtml(t('face.offline.copy'))}</p>
      <div class="fallback-methods" style="justify-content:center;">
        ${fallbacks.map((method) => `
          <button type="button" class="fallback-method" data-action="start-method" data-method="${method}">${escapeHtml(t(`method.${method}`))}</button>`).join('')}
      </div>
      <div class="center-actions">
        <button type="button" class="secondary btn-action" data-action="face-retake">${escapeHtml(t('bio.retry'))}</button>
        <button type="button" class="secondary btn-action" data-action="home">${escapeHtml(t('bio.changeMethod'))}</button>
      </div>
    </div>`;
}

const SCREEN_TEMPLATES = {
  'fingerprint': tplFingerprint,
  'badge': tplBadge,
  'pin': tplPin,
  'manager': tplManager,
  'face-id': tplFaceId,
  'face-camera': tplFaceCamera,
  'face-done': tplFaceDone,
  'face-fail': tplFaceFail,
  'face-offline': tplFaceOffline,
};

function runScreenLeaveHooks(name) {
  if (name === 'face-camera') stopFaceCamera();
}

function runScreenEnterHooks(name) {
  const node = document.getElementById(screenElId(name));
  if (!node) return;

  if (name === 'home') {
    renderMethodGrid();
    setStatus('#statusBox', t('statusBox.ready'));
    return;
  }

  if (name === 'fingerprint') {
    prefillPending('fpId');
    focusField('fpId');
  }
  if (name === 'badge') focusField('badgeId');
  if (name === 'pin') focusField('pinId');
  if (name === 'manager') focusField('mgrEmpId');

  if (name === 'face-id') {
    // Nouvelle identification : vider le champ si l'on revient depuis un
    // autre ecran (privacy) puis charger la liste des employes au visage.
    if (state.prevScreen === 'face-camera' || state.prevScreen === 'face-done' || state.prevScreen === 'face-fail' || state.prevScreen === 'face-offline') {
      const field = document.getElementById('faceId');
      if (field) field.value = '';
      const search = document.getElementById('faceRosterSearch');
      if (search) search.value = '';
    }
    focusField('faceId');
    renderFaceRoster();
    ensureRoster().then(renderFaceRoster).catch(() => {});
  }

  if (name === 'face-camera') {
    // Actualise le bandeau employé/action puis (re)démarre la caméra.
    const bannerName = document.getElementById('faceBannerName');
    if (bannerName) {
      bannerName.textContent = state.faceEmployee && state.faceEmployee.name
        ? state.faceEmployee.name
        : (state.faceIdentifier || '-');
    }
    const bannerId = document.getElementById('faceBannerId');
    if (bannerId) bannerId.textContent = `(${state.faceIdentifier || '-'})`;
    const bannerAction = document.getElementById('faceBannerAction');
    if (bannerAction) bannerAction.textContent = actionLabel(state.faceAction);
    const captureBtn = document.getElementById('faceCaptureBtn');
    if (captureBtn) {
      captureBtn.textContent = t('face.capture', { action: actionLabel(state.faceAction) });
    }
    startFaceCamera();
  }

  if (name === 'face-done' || name === 'face-fail' || name === 'face-offline') {
    node.innerHTML = SCREEN_TEMPLATES[name]();
  }
}

function focusField(id) {
  const field = document.getElementById(id);
  if (field) { try { field.focus(); } catch { /* noop */ } }
}

/** Un identifiant scanné (HID/clavier) avant le choix d'une méthode est
 * pré-rempli dans le champ de l'écran cible (héritage du flux legacy). */
function prefillPending(id) {
  if (!state.pendingIdentifier) return;
  const field = document.getElementById(id);
  if (field && !field.value.trim()) {
    field.value = state.pendingIdentifier;
  }
  state.pendingIdentifier = '';
}

function enterScreen(name) {
  if (!Object.prototype.hasOwnProperty.call({ home: 1, fingerprint: 1, badge: 1, pin: 1, manager: 1, 'face-id': 1, 'face-camera': 1, 'face-done': 1, 'face-fail': 1, 'face-offline': 1 }, name)) return;
  const previous = state.screen;
  if (previous === name) {
    runScreenEnterHooks(name);
    return;
  }
  runScreenLeaveHooks(previous);
  state.prevScreen = previous;

  let created = false;
  let node = document.getElementById(screenElId(name));
  if (!node && name !== 'home') {
    node = document.createElement('div');
    node.id = screenElId(name);
    node.className = 'flow-screen';
    node.dataset.screen = name;
    if (els.punchStage) els.punchStage.appendChild(node);
    created = true;
  }

  state.screen = name;
  if (els.punchStage) {
    els.punchStage.querySelectorAll('.flow-screen').forEach((el) => {
      el.classList.toggle('hidden', el.id !== screenElId(name));
    });
  }

  // Écrans de saisie : reconstruction systématique à l'entrée (langue
  // courante + champ vierge — confidentialité entre deux employés). Un
  // écran créé pour la première fois reçoit son template ici ; les écrans
  // terminaux (done/fail/offline) sont rafraîchis par leurs hooks d'entrée.
  if (node && name !== 'home') {
    const rebuildable = { fingerprint: 1, badge: 1, pin: 1, manager: 1, 'face-id': 1 };
    if (created || rebuildable[name]) {
      const template = SCREEN_TEMPLATES[name];
      node.innerHTML = template ? template() : '';
    }
  }

  runScreenEnterHooks(name);
}

async function startFaceFlow(kind) {
  if (state.faceBusy) return;
  const field = document.getElementById('faceId');
  const identifier = field ? field.value.trim() : '';
  if (!identifier) {
    setStatus('#statusBox', t('error.identifierRequired'), true);
    pulseStatus(false);
    feedback.error();
    return;
  }
  state.faceAction = kind || CONFIG.defaultAction;
  state.faceIdentifier = identifier;
  const roster = await ensureRoster().catch(() => []);
  state.faceEmployee = resolveEmployeeInRoster(identifier, roster) || null;
  enterScreen('face-camera');
}

// ── Délégation des événements du flux (#punchStage) ─────────────
function handlePunchStageClick(event) {
  const trigger = event.target.closest('[data-action]');
  if (!trigger || !els.punchStage || !els.punchStage.contains(trigger)) return;
  const action = trigger.dataset.action;
  if (action === 'start-method') startMethodFlow(trigger.dataset.method);
  else if (action === 'punch') performPunch(trigger.dataset.method, trigger.dataset.kind);
  else if (action === 'face-start') startFaceFlow(trigger.dataset.kind);
  else if (action === 'face-capture') submitFaceCapture();
  else if (action === 'face-retake') { state.faceFailure = null; state.faceSuccess = null; enterScreen('face-camera'); }
  else if (action === 'face-pick') pickFaceRosterEmployee(trigger.dataset.empId);
  else if (action === 'home') enterScreen('home');
}

function handlePunchStageInput(event) {
  if (event.target && event.target.id === 'faceRosterSearch') renderFaceRoster();
}

function handlePunchStageKeydown(event) {
  if (event.key !== 'Enter') return;
  const target = event.target;
  if (!target || target.tagName !== 'INPUT') return;
  const focusTarget = target.dataset.enterFocus;
  if (focusTarget) {
    event.preventDefault();
    const next = document.getElementById(focusTarget);
    if (next) { try { next.focus(); } catch { /* noop */ } }
    return;
  }
  const method = target.dataset.enterMethod;
  if (!method) return;
  event.preventDefault();
  const kind = target.dataset.enterKind || 'check_in';
  if (method === 'face') startFaceFlow(kind);
  else performPunch(method, kind);
}

function startMethodFlow(method) {
  if (!hasKioskConfiguration()) {
    setStatus('#statusBox', t('error.kioskNotConfigured'), true);
    return;
  }
  const target = { fingerprint: 'fingerprint', badge: 'badge', pin: 'pin', manager: 'manager', face: 'face-id' }[method];
  if (!target) return;
  state.lastMethod = method;
  enterScreen(target);
}

// ── Roster « face » (filtre + choix) ────────────────────────────
function renderFaceRoster() {
  const list = document.getElementById('faceRosterList');
  if (!list) return;
  const searchEl = document.getElementById('faceRosterSearch');
  const query = String(searchEl ? searchEl.value : '').trim().toLowerCase();
  const employees = state.roster.filter((employee) => employee.face_enabled);
  state.faceRoster = employees;

  const filtered = employees.filter((employee) => {
    if (!query) return true;
    return [employee.name, employee.matricule, employee.email, employee.zkteco_id]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));
  });

  if (filtered.length === 0) {
    list.innerHTML = `<p class="kbd-hint" style="text-align:left;">${escapeHtml(t('face.roster.empty'))}</p>`;
    return;
  }

  list.innerHTML = filtered.slice(0, 50).map((employee) => `
    <button type="button" class="roster-item" data-action="face-pick" data-emp-id="${escapeHtml(String(employee.employee_id))}">
      <span>${escapeHtml(employee.name)}</span>
      <small>${escapeHtml(employee.matricule || employee.zkteco_id || employee.email || '')}</small>
    </button>`).join('');
}

function pickFaceRosterEmployee(employeeId) {
  const employee = state.roster.find((item) => String(item.employee_id) === String(employeeId));
  if (!employee) return;
  const identifier = employee.matricule || employee.email || employee.zkteco_id || employee.badge_number || String(employee.employee_id);
  const field = document.getElementById('faceId');
  if (field) field.value = identifier;
  state.faceEmployee = employee;
  setStatus('#statusBox', t('demo.selected', { matricule: identifier }));
  focusField('faceId');
}

// ── Camera (flux visage) ────────────────────────────────────────
function stopFaceCamera() {
  if (state.faceStream) {
    try { state.faceStream.getTracks().forEach((track) => track.stop()); } catch { /* noop */ }
    state.faceStream = null;
  }
  const video = document.getElementById('faceVideo');
  if (video) video.srcObject = null;
}

function showFaceCameraError(message) {
  state.cameraError = message;
  const errorBox = document.getElementById('faceCamError');
  const wrap = document.getElementById('faceCamWrap');
  if (errorBox) {
    errorBox.textContent = message;
    errorBox.classList.remove('hidden');
  }
  if (wrap) wrap.classList.add('face-cam-error-mode');
}

function hideFaceCameraError() {
  state.cameraError = null;
  const errorBox = document.getElementById('faceCamError');
  if (errorBox) errorBox.classList.add('hidden');
  const wrap = document.getElementById('faceCamWrap');
  if (wrap) wrap.classList.remove('face-cam-error-mode');
}

function startFaceCamera() {
  const video = document.getElementById('faceVideo');
  const wrap = document.getElementById('faceCamWrap');
  if (!video) return;

  stopFaceCamera();
  hideFaceCameraError();

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    showFaceCameraError(t('face.camera.unsupported'));
    return;
  }

  navigator.mediaDevices.getUserMedia({
    audio: false,
    video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
  }).then((stream) => {
    if (state.screen !== 'face-camera') {
      // L'utilisateur a quitté l'écran pendant la demande de permission.
      try { stream.getTracks().forEach((track) => track.stop()); } catch { /* noop */ }
      return;
    }
    state.faceStream = stream;
    video.srcObject = stream;
    return video.play().catch(() => { /* autoplay bloqué : l'utilisateur capture quand même */ });
  }).catch((error) => {
    const denied = error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError' || error.name === 'SecurityError');
    const message = denied
      ? t('face.camera.denied')
      : t('face.camera.generic', { error: (error && (error.name || error.message)) || String(error) });
    showFaceCameraError(message);
  });
}

function setFaceBusy(busy) {
  const wrap = document.getElementById('faceCamWrap');
  const tip = document.getElementById('faceBusyTip');
  const captureBtn = document.getElementById('faceCaptureBtn');
  if (wrap) wrap.classList.toggle('face-cam-busy', busy);
  if (tip) tip.classList.toggle('hidden', !busy);
  if (captureBtn) captureBtn.disabled = busy;
}

function captureFaceBlob() {
  return new Promise((resolve, reject) => {
    const video = document.getElementById('faceVideo');
    if (!video || !video.videoWidth) {
      reject(new Error('NO_STREAM'));
      return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    if (!ctx) { reject(new Error('CANVAS_UNSUPPORTED')); return; }
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('CAPTURE_EMPTY'))), 'image/jpeg', 0.92);
  });
}

function makeEventId() {
  try {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
  } catch { /* noop */ }
  return `kiosk-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

async function submitFaceCapture() {
  if (state.faceBusy) return;
  const video = document.getElementById('faceVideo');
  if (!video || !video.srcObject) {
    showFaceCameraError(t('face.camera.denied'));
    return;
  }

  state.faceBusy = true;
  setFaceBusy(true);
  setStatus('#statusBox', t('face.checking'));

  try {
    const blob = await captureFaceBlob();
    const form = new FormData();
    form.append('identifier', state.faceIdentifier || '');
    form.append('capture', blob, 'face-capture.jpg');
    form.append('action', state.faceAction || 'check_in');
    form.append('device_event_id', makeEventId());

    // BIO-004 — POST /verify-face (multipart). On garde le payload brut :
    // l'echec facial est STRUCTURE (reason_code + fallback_methods), pas une
    // simple erreur HTTP.
    const result = await kioskForm('/verify-face', form);
    const data = result.payload && result.payload.data;

    if (result.status >= 200 && result.status < 300 && data && data.verified === true) {
      feedback.success();
      handleFaceSuccess(data);
      return;
    }
    feedback.error();
    handleFaceFailure(result);
  } catch (error) {
    // Reseau indisponible / serveur injoignable : pas de verification locale
    // possible (matching serveur, BIO-004). On ne met JAMAIS une capture en
    // file : les donnees brutes du visage ne sont pas stockées par l'UI.
    feedback.error();
    state.faceFailure = {
      reason: 'OFFLINE',
      fallbacks: allowedMethods().filter((method) => method !== 'face'),
    };
    setStatus('#statusBox', t('error.offline.unavailable'), true);
    enterScreen('face-offline');
  } finally {
    state.faceBusy = false;
    setFaceBusy(false);
  }
}

function handleFaceSuccess(data) {
  const employees = state.roster;
  const employee = state.faceEmployee
    || resolveEmployeeByEmployeeId(data.employee_id, employees)
    || null;
  state.faceSuccess = {
    name: (employee && employee.name) || state.faceIdentifier || '-',
    action: state.faceAction,
    time: data.check_in || data.check_out || new Date().toISOString(),
  };
  const time = formatTime(state.faceSuccess.time);
  const employeeLabel = state.faceSuccess.name;
  setStatus('#statusBox', t('punch.confirmed', {
    action: actionLabel(state.faceAction),
    mode: t('punch.mode.synced'),
    time,
    employee: employeeLabel,
  }));
  pulseStatus(true);
  enterScreen('face-done');
}

function handleFaceFailure(result) {
  const payload = result.payload || {};
  const data = payload.data || {};
  const reason = data.reason_code || payload.error || 'unknown';
  state.faceFailure = {
    reason,
    fallbacks: Array.isArray(data.fallback_methods) ? data.fallback_methods : null,
  };
  const reasonKey = `face.reason.${reason}`;
  const reasonText = t(reasonKey) === reasonKey ? t('face.reason.unknown') : t(reasonKey);
  setStatus('#statusBox', reasonText, true);
  pulseStatus(false);
  enterScreen('face-fail');
}

// ── Envoi des pointages (badge / pin / manager / fingerprint) ────
function isTransientError(error) {
  if (!error) return false;
  if (error instanceof TypeError) return true; // fetch réseau
  const message = String((error && error.message) || error || '');
  if (/Failed to fetch|NetworkError|Network request failed|load failed|ERR_CONNECTION|abort/i.test(message)) return true;
  return Boolean(error.status && error.status >= 500);
}

function friendlyServerCode(code) {
  const key = `code.${String(code || '').trim()}`;
  const translated = t(key);
  return translated === key ? t('code.unknown', { code }) : translated;
}

function punchErrorMessage(error, action) {
  const message = String((error && error.message) || error || '').trim();
  // abort() Laravel renvoie {message: CODE} → code machine exploitable.
  if (/^[A-Z][A-Z0-9_]{2,}$/.test(message)) return friendlyServerCode(message);
  // 404 ModelNotFoundException (employé inconnu au /punch direct).
  if (/No query results|not found/i.test(message)) return friendlyServerCode('EMPLOYEE_NOT_FOUND');
  if (message && !isTransientError(error)) return message;
  return t('error.punchFailed');
}

async function resolveManagerEmployeeId(value) {
  const trimmed = String(value || '').trim();
  if (!trimmed) return null;
  const roster = await ensureRoster().catch(() => []);
  const byId = roster.find((employee) => String(employee.employee_id) === trimmed);
  if (byId) return byId.employee_id;
  const byKey = resolveEmployeeInRoster(trimmed, roster);
  if (byKey) return byKey.employee_id;
  // Repli : l'endpoint employee-info résout matricule/email/zkteco_id.
  try {
    const payload = await kioskApi('/employee-info', {
      method: 'POST',
      body: JSON.stringify({ identifier: trimmed }),
    });
    const employee = payload.data && payload.data.employee;
    if (employee && employee.id) return employee.id;
  } catch { /* introuvable */ }
  return null;
}

function clearFieldAndRefocus(id) {
  const field = document.getElementById(id);
  if (!field) return;
  field.value = '';
  try { field.focus(); } catch { /* noop */ }
}

async function submitFingerprintPunch(action) {
  if (state.isPunching) return;
  const field = document.getElementById('fpId');
  const identifier = field ? field.value.trim() : '';
  if (!identifier) {
    setStatus('#statusBox', t('error.identifierRequired'), true);
    pulseStatus(false);
    feedback.error();
    return;
  }

  state.isPunching = true;
  setPunchButtonsDisabled(true);
  setStatus('#statusBox', t('punch.recognizing', { type: t('method.fingerprint'), action: actionLabel(action) }));

  try {
    // Chemin fingerprint INCHANGE : pont local offline-first + sync auto.
    const employee = await findLocalRosterEmployee(identifier);
    const payload = await localFetchJson(`${CONFIG.localBridgeUrl}/punch`, {
      method: 'POST',
      body: JSON.stringify({ identifier, action, biometric_type: 'fingerprint', method: 'fingerprint' }),
    });
    const mode = payload.data.sync_status === 'synced' ? t('punch.mode.synced') : t('punch.mode.offline');
    const employeeLabel = employee ? employee.name : identifier;
    const eventTime = payload.data.occurred_at ? formatTime(payload.data.occurred_at) : formatTime(new Date().toISOString());
    setStatus('#statusBox', t('punch.confirmed', { action: actionLabel(action), mode, time: eventTime, employee: employeeLabel }));
    pulseStatus(true);
    feedback.success();
    clearFieldAndRefocus('fpId');
    await refreshStatus();
  } catch (error) {
    setStatus('#statusBox', punchErrorMessage(error, action), true);
    pulseStatus(false);
    feedback.error();
  } finally {
    state.isPunching = false;
    setPunchButtonsDisabled(false);
  }
}

/** badge → /punch direct ; hors-ligne → file bridge (method card). */
async function submitBadgePunch(action) {
  if (state.isPunching) return;
  const field = document.getElementById('badgeId');
  const identifier = field ? field.value.trim() : '';
  if (!identifier) {
    setStatus('#statusBox', t('error.badgeRequired'), true);
    pulseStatus(false);
    feedback.error();
    return;
  }
  await directOrQueuedPunch({ method: 'badge', action, identifier, fieldId: 'badgeId', badgeLike: true });
}

/** pin → /punch direct ; hors-ligne → file bridge (legacy identifier). */
async function submitPinPunch(action) {
  if (state.isPunching) return;
  const field = document.getElementById('pinId');
  const identifier = field ? field.value.trim() : '';
  if (!identifier) {
    setStatus('#statusBox', t('error.identifierRequiredShort'), true);
    pulseStatus(false);
    feedback.error();
    return;
  }
  await directOrQueuedPunch({ method: 'pin', action, identifier, fieldId: 'pinId', badgeLike: false });
}

async function directOrQueuedPunch({ method, action, identifier, fieldId, badgeLike }) {
  state.isPunching = true;
  setPunchButtonsDisabled(true);
  setStatus('#statusBox', t('punch.sending'));

  try {
    const result = await rawKioskJson('/punch', {
      method: 'POST',
      body: JSON.stringify({ identifier, action, method }),
    });
    if (result.status >= 200 && result.status < 300) {
      const data = result.payload.data || {};
      const timeValue = data.check_in || data.check_out || new Date().toISOString();
      const employee = await resolveEmployeeInRoster(identifier, await ensureRoster().catch(() => []));
      const employeeLabel = employee ? employee.name : identifier;
      setStatus('#statusBox', t('punch.confirmed', {
        action: actionLabel(action),
        mode: t('punch.mode.synced'),
        time: formatTime(timeValue),
        employee: employeeLabel,
      }));
      pulseStatus(true);
      feedback.success();
      clearFieldAndRefocus(fieldId);
      await refreshStatus();
      return;
    }
    // 4xx / 5xx structuré (message = code machine ou message Laravel).
    const payload = result.payload || {};
    const code = payload.error || payload.message || String(result.status);
    throw Object.assign(new Error(code), { status: result.status });
  } catch (error) {
    // Hors-ligne / erreur transitoire : repli file du pont local pour
    // badge (method card) et pin (legacy). Une erreur 4xx métier n'est
    // JAMAIS mise en file : elle serait dead-letter à la sync sans
    // explication pour l'employé.
    if (isTransientError(error) && (badgeLike || method === 'pin')) {
      const queued = await queueViaBridge({ method, action, identifier, fieldId });
      if (queued) return;
    }
    setStatus('#statusBox', punchErrorMessage(error, action), true);
    pulseStatus(false);
    feedback.error();
  } finally {
    state.isPunching = false;
    setPunchButtonsDisabled(false);
  }
}

/**
 * Repli hors-ligne : file SQLite du pont local (synchro auto quand le reseau
 * revient). Le pont ne connait que fingerprint|face|card : badge → card
 * (equivalence domaine), pin → legacy fingerprint (meme comportement que la
 * saisie manuelle historique). La confirmation reste honnete : « stocke hors
 * ligne » tant que le serveur n'a pas repondu.
 */
async function queueViaBridge({ method, action, identifier, fieldId }) {
  try {
    const employee = await findLocalRosterEmployee(identifier);
    const isBadge = method === 'badge';
    const body = {
      identifier,
      action,
      biometric_type: 'fingerprint', // rétro-compat pont local
      method: isBadge ? 'card' : 'fingerprint',
      ...(isBadge ? { badge_number: identifier } : {}),
    };
    const payload = await localFetchJson(`${CONFIG.localBridgeUrl}/punch`, {
      method: 'POST',
      body: JSON.stringify(body),
    });
    const mode = payload.data.sync_status === 'synced' ? t('punch.mode.synced') : t('punch.mode.offline');
    const employeeLabel = employee ? employee.name : identifier;
    const eventTime = payload.data.occurred_at ? formatTime(payload.data.occurred_at) : formatTime(new Date().toISOString());
    setStatus('#statusBox', t('punch.confirmed', { action: actionLabel(action), mode, time: eventTime, employee: employeeLabel }));
    pulseStatus(true);
    feedback.success();
    clearFieldAndRefocus(fieldId);
    await refreshStatus();
    return true;
  } catch (error) {
    setStatus('#statusBox', t('error.offline.unavailable'), true);
    pulseStatus(false);
    feedback.error();
    return false;
  }
}

/** manager → /punch + manager_employee_id ; jamais de file hors-ligne. */
async function submitManagerPunch(action) {
  if (state.isPunching) return;
  const empField = document.getElementById('mgrEmpId');
  const mgrField = document.getElementById('mgrMgrId');
  const identifier = empField ? empField.value.trim() : '';
  const managerValue = mgrField ? mgrField.value.trim() : '';
  if (!identifier) {
    setStatus('#statusBox', t('error.identifierRequired'), true);
    pulseStatus(false);
    feedback.error();
    return;
  }
  if (!managerValue) {
    setStatus('#statusBox', t('manager.unresolved'), true);
    pulseStatus(false);
    feedback.error();
    if (mgrField) { try { mgrField.focus(); } catch { /* noop */ } }
    return;
  }

  state.isPunching = true;
  setPunchButtonsDisabled(true);
  setStatus('#statusBox', t('punch.sending'));

  try {
    const managerEmployeeId = await resolveManagerEmployeeId(managerValue);
    if (!managerEmployeeId) {
      setStatus('#statusBox', t('manager.unresolved'), true);
      pulseStatus(false);
      feedback.error();
      return;
    }
    const result = await rawKioskJson('/punch', {
      method: 'POST',
      body: JSON.stringify({ identifier, action, method: 'manager', manager_employee_id: managerEmployeeId }),
    });
    if (result.status >= 200 && result.status < 300) {
      const data = result.payload.data || {};
      const timeValue = data.check_in || data.check_out || new Date().toISOString();
      const employee = await resolveEmployeeInRoster(identifier, await ensureRoster().catch(() => []));
      const employeeLabel = employee ? employee.name : identifier;
      setStatus('#statusBox', t('punch.confirmed', {
        action: actionLabel(action),
        mode: t('punch.mode.synced'),
        time: formatTime(timeValue),
        employee: employeeLabel,
      }));
      pulseStatus(true);
      feedback.success();
      clearFieldAndRefocus('mgrEmpId');
      if (mgrField) mgrField.value = '';
      await refreshStatus();
      return;
    }
    const payload = result.payload || {};
    const code = payload.error || payload.message || String(result.status);
    throw Object.assign(new Error(code), { status: result.status });
  } catch (error) {
    if (isTransientError(error)) {
      // Hors-ligne : pas de mise en file (le manager_employee_id ne survit
      // pas au contrat /sync) → message clair + propositions de repli.
      setStatus('#statusBox', t('error.offline.unavailable'), true);
      state.faceFailure = { reason: 'OFFLINE', fallbacks: allowedMethods().filter((m) => m !== 'manager') };
      feedback.error();
      return;
    }
    setStatus('#statusBox', punchErrorMessage(error, action), true);
    pulseStatus(false);
    feedback.error();
  } finally {
    state.isPunching = false;
    setPunchButtonsDisabled(false);
  }
}

/** Point d'entrée unique des boutons Arrivée/Départ et du hardware. */
async function performPunch(method, action) {
  if (state.isPunching) return;
  if (!hasKioskConfiguration()) {
    setStatus('#statusBox', t('error.kioskNotConfigured'), true);
    pulseStatus(false);
    feedback.error();
    return;
  }
  if (method === 'fingerprint') return submitFingerprintPunch(action);
  if (method === 'badge') return submitBadgePunch(action);
  if (method === 'pin') return submitPinPunch(action);
  if (method === 'manager') return submitManagerPunch(action);
  setStatus('#statusBox', t('error.punchRefused', { code: method || 'UNKNOWN_METHOD' }), true);
  pulseStatus(false);
  feedback.error();
  return null;
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
    await localFetchJson(`${CONFIG.localBridgeUrl}/sync/all`, { method: 'POST', body: '{}' });
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
// BIO-009 : le pont matériel continue d'injecter l'identifiant scanné.
// La méthode est normalisée (card→badge) et route vers le flux adapté :
//   fingerprint → écran empreinte + pointage pont local (comportement
//                 historique conservé) ;
//   badge/card  → écran badge, identifiant pré-rempli (pointage à la
//                 validation par Arrivée/Départ ou appel direct) ;
//   face        → flux visage guidé, identifiant pré-rempli.
window.ZKTecoBridge = {
  /**
   * Soumet un pointage depuis le bridge matériel.
   *
   * #5121 — `method` est le champ (fingerprint|face|card|badge|...).
   * `biometricType` est conservé pour rétro-compatibilité.
   */
  submitIdentifier(value, action = 'check_in', biometricType = 'fingerprint', method = null) {
    const resolvedMethod = normalizeMethod(method || biometricType) || 'fingerprint';
    if (!hasKioskConfiguration()) {
      setStatus('#statusBox', t('error.kioskNotConfigured'), true);
      return Promise.resolve();
    }
    if (resolvedMethod === 'face') {
      enterScreen('face-id');
      const field = document.getElementById('faceId');
      if (field) field.value = value || '';
      state.faceIdentifier = value || '';
      return Promise.resolve();
    }
    const screenFor = { fingerprint: 'fingerprint', badge: 'badge', pin: 'pin', manager: 'manager' }[resolvedMethod] || 'fingerprint';
    enterScreen(screenFor);
    const fieldId = { fingerprint: 'fpId', badge: 'badgeId', pin: 'pinId', manager: 'mgrEmpId' }[screenFor];
    const field = fieldId ? document.getElementById(fieldId) : null;
    if (field) field.value = value || '';
    if (resolvedMethod === 'fingerprint') {
      return performPunch('fingerprint', action);
    }
    return Promise.resolve();
  },
  fillIdentifier(value) {
    state.pendingIdentifier = value || '';
    const fieldIds = ['fpId', 'badgeId', 'pinId', 'faceId', 'mgrEmpId'];
    for (const fieldId of fieldIds) {
      const field = document.getElementById(fieldId);
      if (field && !field.closest('.hidden')) { field.value = value || ''; break; }
    }
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
  document.addEventListener('leopardo:lang-changed', () => onKioskLangChanged());

  initTabs();

  if (!hasKioskConfiguration()) {
    setUnconfiguredState();
    return;
  }

  // BIO-009 — flux multi-méthodes : délégation unique des clics/saisies
  bindPunchStage();

  // PA2-KIO-003: actionable sync retry button next to the status pill
  if (els.syncRetryBtn) {
    els.syncRetryBtn.addEventListener('click', retrySync);
  }

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

  // Issue #2911 : sans apiBaseUrl/deviceCode (config.json absent), la borne
  // appelait /api/v1/kiosks//… → alerte « Error 404 » brute. Afficher un
  // état « borne non configurée » explicite et désactiver les actions.
  if (!CONFIG.apiBaseUrl || !CONFIG.deviceCode) {
    setStatus('#statusBox', t('error.kioskNotConfigured'), true);
    setPunchButtonsDisabled(true);
    if (els.syncRetryBtn) els.syncRetryBtn.disabled = true;
    return;
  }

  // Initial loads — BIO-009 : accueil multi-méthodes, matrice serveur puis
  // statut bridge (offline-first).
  enterScreen('home');
  refreshStatus();
  setInterval(refreshStatus, CONFIG.refreshInterval);
  loadKioskConfig();
  scheduleMatrixFallback();

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

function bindPunchStage() {
  if (!els.punchStage) return;
  els.punchStage.addEventListener('click', handlePunchStageClick);
  els.punchStage.addEventListener('input', handlePunchStageInput);
  els.punchStage.addEventListener('keydown', handlePunchStageKeydown);
}

/** Re-rendu des écrans dynamiques quand la langue change. */
function onKioskLangChanged() {
  if (!hasKioskConfiguration()) {
    setUnconfiguredState();
    return;
  }
  if (state.status) renderStatus();
  const dynamicScreens = ['fingerprint', 'badge', 'pin', 'manager', 'face-id'];
  if (dynamicScreens.indexOf(state.screen) !== -1) {
    const node = document.getElementById(screenElId(state.screen));
    if (node) node.innerHTML = SCREEN_TEMPLATES[state.screen]();
    if (state.screen === 'face-id') {
      renderFaceRoster();
      ensureRoster().then(renderFaceRoster).catch(() => {});
    }
  }
  if (state.screen === 'home') renderMethodGrid();
  else maybeRenderMatrix();
}

// ── Demo Access ──────────────────────────────────────
// #5619 — Les données de démo ne doivent jamais polluer un déploiement
// de production. La fonction ne s'active que lorsque le serveur injecte
// `window.__KIOSK_DEMO_MODE = true` (config.example.json, section demo).
// En production (flag absent ou false), le bouton est masqué et la
// constante DEMO_COMPANIES n'est jamais déclarée dans le scope global.
function initDemoAccess() {
  const openBtn = $('#demoAccessBtn');

  // Guard : cacher le bouton et ne pas initialiser si on n'est pas en mode démo.
  if (!window.__KIOSK_DEMO_MODE) {
    if (openBtn) {
      openBtn.classList.add('hidden');
      openBtn.setAttribute('aria-hidden', 'true');
    }
    return;
  }

  // Données de démo — déclarées localement, jamais exposées en prod.
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

  const overlay = $('#demoOverlay');
  const list = $('#demoUsersList');
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
  if (closeBtn) closeBtn.addEventListener('click', () => overlay.classList.add('hidden'));
  overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.add('hidden'); });

  list.addEventListener('click', (e) => {
    const btn = e.target.closest('.demo-user-btn');
    if (!btn) return;
    const matricule = btn.dataset.matricule;
    // Pré-remplit le champ identifiant de l'écran courant (flux BIO-009),
    // ou mémorise l'identifiant en attente sur l'accueil.
    const fieldIds = ['fpId', 'badgeId', 'pinId', 'faceId', 'mgrEmpId'];
    let filled = false;
    for (const fieldId of fieldIds) {
      const field = document.getElementById(fieldId);
      if (field && !field.closest('.hidden')) { field.value = matricule; filled = true; break; }
    }
    if (!filled) state.pendingIdentifier = matricule;
    const infoId = $('#infoIdentifier');
    if (infoId) infoId.value = matricule;
    const leaveId = $('#leaveIdentifier');
    if (leaveId) leaveId.value = matricule;
    overlay.classList.add('hidden');
    setStatus('#statusBox', t('demo.selected', { matricule }));
  });
}

document.addEventListener('DOMContentLoaded', init);
