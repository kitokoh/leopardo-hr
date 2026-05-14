import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const managerToken = __ENV.MANAGER_TOKEN || '';
const employeeToken = __ENV.EMPLOYEE_TOKEN || managerToken;

const dashboardLatency = new Trend('dashboard_latency_ms');
const attendanceLatency = new Trend('attendance_latency_ms');
const payrollLatency = new Trend('payroll_latency_ms');

export const options = {
  scenarios: {
    health: {
      executor: 'constant-vus',
      vus: Number(__ENV.HEALTH_VUS || 5),
      duration: __ENV.HEALTH_DURATION || '1m',
      exec: 'healthScenario',
    },
    manager_read_paths: {
      executor: 'constant-vus',
      vus: Number(__ENV.MANAGER_VUS || 5),
      duration: __ENV.MANAGER_DURATION || '1m',
      exec: 'managerReadScenario',
      startTime: '5s',
    },
    employee_read_paths: {
      executor: 'constant-vus',
      vus: Number(__ENV.EMPLOYEE_VUS || 5),
      duration: __ENV.EMPLOYEE_DURATION || '1m',
      exec: 'employeeReadScenario',
      startTime: '10s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1200'],
    dashboard_latency_ms: ['p(95)<1000'],
    attendance_latency_ms: ['p(95)<1200'],
    payroll_latency_ms: ['p(95)<1500'],
  },
};

function authHeaders(token) {
  return token
    ? {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      }
    : {
        headers: {
          Accept: 'application/json',
        },
      };
}

function expectOk(response, label) {
  check(response, {
    [`${label} status is 2xx`]: (res) => res.status >= 200 && res.status < 300,
    [`${label} json response`]: (res) => String(res.headers['Content-Type'] || '').includes('application/json'),
  });
}

export function healthScenario() {
  group('health probes', () => {
    const live = http.get(`${baseUrl}/api/v1/health/live`, authHeaders(''));
    expectOk(live, 'health live');

    const ready = http.get(`${baseUrl}/api/v1/health/ready`, authHeaders(''));
    check(ready, {
      'health ready is not server error': (res) => res.status < 500,
    });
  });

  sleep(1);
}

export function managerReadScenario() {
  if (!managerToken) {
    healthScenario();
    return;
  }

  group('manager dashboard and HR reads', () => {
    const dashboard = http.get(`${baseUrl}/api/v1/dashboard/kpi`, authHeaders(managerToken));
    dashboardLatency.add(dashboard.timings.duration);
    expectOk(dashboard, 'dashboard kpi');

    const employees = http.get(`${baseUrl}/api/v1/employees?per_page=20`, authHeaders(managerToken));
    expectOk(employees, 'employees list');

    const anomalies = http.get(`${baseUrl}/api/v1/attendance/anomalies`, authHeaders(managerToken));
    attendanceLatency.add(anomalies.timings.duration);
    expectOk(anomalies, 'attendance anomalies');

    const payrollRuns = http.get(`${baseUrl}/api/v1/payroll-runs?per_page=10`, authHeaders(managerToken));
    payrollLatency.add(payrollRuns.timings.duration);
    expectOk(payrollRuns, 'payroll runs');
  });

  sleep(1);
}

export function employeeReadScenario() {
  if (!employeeToken) {
    healthScenario();
    return;
  }

  group('employee self-service reads', () => {
    const me = http.get(`${baseUrl}/api/v1/auth/me`, authHeaders(employeeToken));
    expectOk(me, 'auth me');

    const today = http.get(`${baseUrl}/api/v1/attendance/today`, authHeaders(employeeToken));
    attendanceLatency.add(today.timings.duration);
    expectOk(today, 'attendance today');

    const payslips = http.get(`${baseUrl}/api/v1/me/pay-slips`, authHeaders(employeeToken));
    payrollLatency.add(payslips.timings.duration);
    expectOk(payslips, 'my pay slips');

    const monthly = http.get(`${baseUrl}/api/v1/me/monthly-summary`, authHeaders(employeeToken));
    expectOk(monthly, 'monthly summary');
  });

  sleep(1);
}
