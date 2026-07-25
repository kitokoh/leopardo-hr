import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const punchMode = (__ENV.PUNCH_MODE || 'manual').toLowerCase();
const allowCheckout = __ENV.ALLOW_CHECKOUT !== 'false';
const tokens = (__ENV.EMPLOYEE_TOKENS || __ENV.EMPLOYEE_TOKEN || '')
  .split(',')
  .map((token) => token.trim())
  .filter(Boolean);

const punchLatency = new Trend('attendance_punch_latency_ms');
const punchFailures = new Counter('attendance_punch_failures');

function stageVus(name, fallback) {
  const value = Number(__ENV[name] || fallback);
  return Number.isFinite(value) && value > 0 ? Math.floor(value) : fallback;
}

export const options = {
  scenarios: {
    attendance_punch_scale: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: __ENV.STAGE_DURATION || '30s', target: stageVus('STAGE_1_VUS', 10) },
        { duration: __ENV.STAGE_DURATION || '30s', target: stageVus('STAGE_2_VUS', 20) },
        { duration: __ENV.STAGE_DURATION || '30s', target: stageVus('STAGE_3_VUS', 50) },
        { duration: __ENV.STAGE_DURATION || '30s', target: stageVus('STAGE_4_VUS', 100) },
      ],
      gracefulRampDown: __ENV.STAGE_GRACEFUL_RAMPDOWN || '10s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    attendance_punch_latency_ms: ['p(95)<1500'],
    attendance_punch_failures: ['count<1'],
  },
};

function tokenForVu() {
  if (tokens.length === 0) {
    return '';
  }

  return tokens[(__VU - 1) % tokens.length];
}

function headers(token, json) {
  const base = {
    Accept: 'application/json',
    Authorization: token ? `Bearer ${token}` : undefined,
  };

  if (json) {
    base['Content-Type'] = 'application/json';
  }

  return { headers: base };
}

function recordAcceptedOrRejected(response, label) {
  punchLatency.add(response.timings.duration);

  const accepted = check(response, {
    [`${label} accepted or business-rejected`]: (res) => [200, 201, 409, 422].includes(res.status),
  });

  if (!accepted) {
    punchFailures.add(1);
  }
}

function manualPunch(token) {
  group('manual check-in/check-out (path-based=false)', () => {
    const checkIn = http.post(`${baseUrl}/api/v1/attendance/check-in`, null, headers(token, false));
    recordAcceptedOrRejected(checkIn, 'manual check-in');

    if (allowCheckout) {
      const checkOut = http.post(`${baseUrl}/api/v1/attendance/check-out`, null, headers(token, false));
      recordAcceptedOrRejected(checkOut, 'manual check-out');
    }
  });
}

function pathBasedPunch(token) {
  const latitude = Number(__ENV.GEO_LAT || 33.5731);
  const longitude = Number(__ENV.GEO_LNG || -7.5898);
  const accuracyMeters = Number(__ENV.GEO_ACCURACY_METERS || 15);

  group('path-based geofence punch (zone_enter/zone_exit)', () => {
    const enterPayload = JSON.stringify({
      event_type: 'zone_enter',
      latitude,
      longitude,
      accuracy_meters: accuracyMeters,
    });
    const zoneEnter = http.post(`${baseUrl}/api/v1/smart-attendance/geo-events`, enterPayload, headers(token, true));
    recordAcceptedOrRejected(zoneEnter, 'geo zone_enter');

    sleep(Number(__ENV.GEO_DWELL_SECONDS || 1));

    const exitPayload = JSON.stringify({
      event_type: 'zone_exit',
      latitude,
      longitude,
      accuracy_meters: accuracyMeters,
    });
    const zoneExit = http.post(`${baseUrl}/api/v1/smart-attendance/geo-events`, exitPayload, headers(token, true));
    recordAcceptedOrRejected(zoneExit, 'geo zone_exit');
  });
}

export default function () {
  const token = tokenForVu();

  if (!token) {
    const health = http.get(`${baseUrl}/api/v1/health/live`, { headers: { Accept: 'application/json' } });
    check(health, { 'health fallback status is 2xx': (res) => res.status >= 200 && res.status < 300 });
    sleep(1);
    return;
  }

  if (punchMode === 'path') {
    pathBasedPunch(token);
  } else {
    manualPunch(token);
  }

  sleep(Number(__ENV.PUNCH_SLEEP || 1));
}
