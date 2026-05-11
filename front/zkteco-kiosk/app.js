const state = {
  status: null,
};

const companyNameEl = document.getElementById('companyName');
const locationLabelEl = document.getElementById('locationLabel');
const deviceCodeEl = document.getElementById('deviceCode');
const queueCountEl = document.getElementById('queueCount');
const identifierInput = document.getElementById('identifier');
const biometricTypeSelect = document.getElementById('biometricType');
const statusBox = document.getElementById('statusBox');
const syncDot = document.getElementById('syncDot');
const syncLabel = document.getElementById('syncLabel');
const checkInButton = document.getElementById('checkInButton');
const checkOutButton = document.getElementById('checkOutButton');

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
    throw new Error(payload.error || payload.message || 'Erreur locale');
  }

  return payload;
}

function setStatus(message, isError = false) {
  statusBox.textContent = message;
  statusBox.classList.toggle('error', isError);
}

function renderStatus() {
  if (!state.status) return;

  companyNameEl.textContent = state.status.company_name || 'Leopardo RH Client';
  locationLabelEl.textContent = state.status.location_label || 'Entree principale';
  deviceCodeEl.textContent = state.status.device_code || '-';
  queueCountEl.textContent = `${state.status.queue_count || 0} evenement(s) en attente`;

  const ok = state.status.online === true;
  syncDot.classList.toggle('ok', ok);
  syncDot.classList.toggle('bad', !ok);
  syncLabel.textContent = ok
    ? 'Connexion OK - synchronisation auto active'
    : `Mode offline - sync plus tard (${state.status.last_error || 'reseau indisponible'})`;
}

async function refreshStatus() {
  try {
    const payload = await fetchJson('/local/status');
    state.status = payload.data;
    renderStatus();
  } catch (error) {
    setStatus(error.message || 'Bridge local indisponible.', true);
  }
}

async function submitPunch(action) {
  const identifier = identifierInput.value.trim();
  if (!identifier) {
    setStatus('Veuillez saisir ou scanner un identifiant employe.', true);
    return;
  }

  setStatus('Enregistrement local du pointage...');

  try {
    const payload = await fetchJson('/local/punch', {
      method: 'POST',
      body: JSON.stringify({
        identifier,
        action,
        biometric_type: biometricTypeSelect.value,
      }),
    });

    const mode = payload.data.sync_status === 'synced' ? 'synchronise' : 'stocke hors ligne';
    setStatus(`Pointage ${mode} pour ${identifier}.`);
    identifierInput.value = '';
    identifierInput.focus();
    await refreshStatus();
  } catch (error) {
    setStatus(error.message || 'Echec de pointage.', true);
  }
}

checkInButton.addEventListener('click', () => submitPunch('check_in'));
checkOutButton.addEventListener('click', () => submitPunch('check_out'));
identifierInput.addEventListener('keydown', (event) => {
  if (event.key === 'Enter') {
    event.preventDefault();
    submitPunch('check_in');
  }
});

window.ZKTecoBridge = {
  submitIdentifier(value, action = 'check_in', biometricType = 'fingerprint') {
    identifierInput.value = value || '';
    biometricTypeSelect.value = biometricType || 'fingerprint';
    return submitPunch(action);
  },
  fillIdentifier(value) {
    identifierInput.value = value || '';
    identifierInput.focus();
  },
};

refreshStatus();
setInterval(refreshStatus, 15000);
