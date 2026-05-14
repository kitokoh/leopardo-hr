import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const tokens = (__ENV.EMPLOYEE_TOKENS || __ENV.EMPLOYEE_TOKEN || '')
  .split(',')
  .map((token) => token.trim())
  .filter(Boolean);

const attendanceLatency = new Trend('employee_100_attendance_latency_ms');
const payrollLatency = new Trend('employee_100_payroll_latency_ms');

export const options = {
  scenarios: {
    employee_100_attendance_payroll: {
      executor: 'constant-vus',
      vus: Number(__ENV.EMPLOYEE_100_VUS || 100),
      duration: __ENV.EMPLOYEE_100_DURATION || '5m',
      gracefulStop: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    employee_100_attendance_latency_ms: ['p(95)<1500'],
    employee_100_payroll_latency_ms: ['p(95)<1800'],
  },
};

function tokenForVu() {
  if (tokens.length === 0) {
    return '';
  }

  return tokens[(__VU - 1) % tokens.length];
}

function headers(token) {
  return {
    headers: {
      Accept: 'application/json',
      Authorization: token ? `Bearer ${token}` : undefined,
    },
  };
}

function expect2xx(response, label) {
  check(response, {
    [`${label} status is 2xx`]: (res) => res.status >= 200 && res.status < 300,
  });
}

export default function () {
  const token = tokenForVu();

  if (!token) {
    const health = http.get(`${baseUrl}/api/v1/health/live`, { headers: { Accept: 'application/json' } });
    expect2xx(health, 'health fallback');
    sleep(1);
    return;
  }

  group('100 employees attendance and payroll reads', () => {
    const today = http.get(`${baseUrl}/api/v1/attendance/today`, headers(token));
    attendanceLatency.add(today.timings.duration);
    expect2xx(today, 'attendance today');

    const history = http.get(`${baseUrl}/api/v1/attendance?per_page=20`, headers(token));
    attendanceLatency.add(history.timings.duration);
    expect2xx(history, 'attendance history');

    const payslips = http.get(`${baseUrl}/api/v1/me/pay-slips?per_page=12`, headers(token));
    payrollLatency.add(payslips.timings.duration);
    expect2xx(payslips, 'pay slips');

    if (__ENV.ALLOW_ATTENDANCE_MUTATIONS === 'true') {
      const punch = http.post(`${baseUrl}/api/v1/attendance/check-in`, null, headers(token));
      attendanceLatency.add(punch.timings.duration);
      check(punch, {
        'optional check-in accepted or business-rejected': (res) => [200, 201, 409, 422].includes(res.status),
      });
    }
  });

  sleep(Number(__ENV.EMPLOYEE_100_SLEEP || 1));
}
