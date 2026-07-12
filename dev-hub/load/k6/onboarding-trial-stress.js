/**
 * k6 Stress Test — Trial Signup & Onboarding Flow
 *
 * Covers P2 critical paths:
 *   - POST /trial/signup            (OTP request)
 *   - POST /trial/verify            (OTP verify + tenant provisioning)
 *   - POST /auth/login              (existing demo account)
 *   - GET  /onboarding-setup/checklist
 *   - GET  /onboarding-setup/progress
 *   - POST /employees
 *
 * Usage (staging):
 *   k6 run onboarding-trial-stress.js \
 *     -e BASE_URL=https://gestionemployerbackend.onrender.com/api/v1
 *
 * IMPORTANT: This test creates real data on staging.
 *   Use only against a dedicated stress-test environment.
 *   Never run against production clients.
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// ── Custom metrics ─────────────────────────────────────────────────────────
const signupErrorRate  = new Rate('signup_errors');
const loginErrorRate   = new Rate('login_errors');
const checklistLatency = new Trend('checklist_latency_ms');
const createEmpLatency = new Trend('create_employee_latency_ms');

// ── Test options ───────────────────────────────────────────────────────────
export const options = {
  scenarios: {
    // Ramp-up: 0 → 20 VU over 30s, hold 60s, ramp-down 30s
    onboarding_flow: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 10 },
        { duration: '60s', target: 20 },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_duration:    ['p(95)<3000'],  // 95% of requests under 3s
    http_req_failed:      ['rate<0.05'],   // error rate < 5%
    signup_errors:        ['rate<0.1'],
    login_errors:         ['rate<0.05'],
    checklist_latency_ms: ['p(90)<1000'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000/api/v1';

// ── Helpers ────────────────────────────────────────────────────────────────
function randomEmail() {
  const ts = Date.now();
  const rand = Math.floor(Math.random() * 100000);
  return `stress-trial-${ts}-${rand}@leopardo-stress.test`;
}

function randomCompany() {
  const names = ['Beta Corp', 'Delta SA', 'Gamma SARL', 'Epsilon Ltd', 'Zeta Inc'];
  return names[Math.floor(Math.random() * names.length)] + ' ' + Date.now();
}

// ── Main VU scenario ───────────────────────────────────────────────────────
export default function () {
  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  };

  const email   = randomEmail();
  const company = randomCompany();

  // Step 1: Request OTP
  const signupRes = http.post(
    `${BASE_URL}/trial/signup`,
    JSON.stringify({ email, company, country: 'DZ', role: 'founder' }),
    params,
  );

  const signupOk = check(signupRes, {
    'signup status 200': (r) => r.status === 200,
    'signup has pending_verification': (r) => {
      try { return JSON.parse(r.body).data?.status === 'pending_verification'; }
      catch { return false; }
    },
  });
  signupErrorRate.add(!signupOk);

  if (!signupOk) {
    sleep(1);
    return;
  }

  sleep(0.5);

  // Step 2: In a real test we'd need the actual OTP from email.
  // For stress testing against staging, we use a fixed test OTP
  // only if ALLOW_TEST_OTP env var is set.
  if (!__ENV.ALLOW_TEST_OTP) {
    // Smoke-only: test login with existing demo account instead
    const loginRes = http.post(
      `${BASE_URL}/auth/login`,
      JSON.stringify({
        email: __ENV.STRESS_MANAGER_EMAIL || 'manager@techcorp-algerie.dz',
        password: __ENV.STRESS_MANAGER_PASSWORD || 'password123',
        device_name: 'k6-stress',
      }),
      params,
    );

    const loginOk = check(loginRes, {
      'login status 200': (r) => r.status === 200,
      'login has token': (r) => {
        try { return !!JSON.parse(r.body).token; }
        catch { return false; }
      },
    });
    loginErrorRate.add(!loginOk);

    if (loginOk) {
      const token = JSON.parse(loginRes.body).token;
      const authParams = {
        headers: { ...params.headers, Authorization: `Bearer ${token}` },
      };

      // GET /onboarding-setup/checklist
      const start = Date.now();
      const checklistRes = http.get(`${BASE_URL}/onboarding-setup/checklist`, authParams);
      checklistLatency.add(Date.now() - start);
      check(checklistRes, { 'checklist 200': (r) => r.status === 200 });

      sleep(0.3);

      // GET /onboarding-setup/progress
      const progressRes = http.get(`${BASE_URL}/onboarding-setup/progress`, authParams);
      check(progressRes, { 'progress 200': (r) => r.status === 200 });

      sleep(0.2);

      // POST /employees (create one employee)
      const empStart = Date.now();
      const empRes = http.post(
        `${BASE_URL}/employees`,
        JSON.stringify({
          first_name: 'Stress',
          last_name: `Test-${Date.now()}`,
          email: `emp-${Date.now()}@stress.test`,
          password: 'Password123!',
          role: 'employee',
        }),
        authParams,
      );
      createEmpLatency.add(Date.now() - empStart);
      check(empRes, { 'create employee 201': (r) => r.status === 201 });
    }

    sleep(1);
    return;
  }

  // Full OTP flow (requires ALLOW_TEST_OTP=1 and TEST_OTP env)
  const verifyRes = http.post(
    `${BASE_URL}/trial/verify`,
    JSON.stringify({ email, code: __ENV.TEST_OTP || '000000' }),
    params,
  );

  check(verifyRes, {
    'verify status 201': (r) => r.status === 201,
    'verify has company': (r) => {
      try { return !!JSON.parse(r.body).data?.company?.id; }
      catch { return false; }
    },
  });

  sleep(1);
}
