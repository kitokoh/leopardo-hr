import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const managerToken = __ENV.MANAGER_TOKEN || '';

const dashboardLatency = new Trend('admin_10k_dashboard_latency_ms');
const employeeListLatency = new Trend('admin_10k_employee_list_latency_ms');
const employeeSearchLatency = new Trend('admin_10k_employee_search_latency_ms');

export const options = {
  scenarios: {
    admin_dashboard_10k: {
      executor: 'constant-vus',
      vus: Number(__ENV.ADMIN_10K_VUS || 20),
      duration: __ENV.ADMIN_10K_DURATION || '5m',
      gracefulStop: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    admin_10k_dashboard_latency_ms: ['p(95)<1500'],
    admin_10k_employee_list_latency_ms: ['p(95)<1800'],
    admin_10k_employee_search_latency_ms: ['p(95)<1800'],
  },
};

function headers() {
  return {
    headers: {
      Accept: 'application/json',
      Authorization: managerToken ? `Bearer ${managerToken}` : undefined,
    },
  };
}

function expect2xx(response, label) {
  check(response, {
    [`${label} status is 2xx`]: (res) => res.status >= 200 && res.status < 300,
  });
}

export default function () {
  if (!managerToken) {
    const health = http.get(`${baseUrl}/api/v1/health/live`, { headers: { Accept: 'application/json' } });
    expect2xx(health, 'health fallback');
    sleep(1);
    return;
  }

  group('admin dashboard with 10k employees', () => {
    const dashboard = http.get(`${baseUrl}/api/v1/dashboard/kpi`, headers());
    dashboardLatency.add(dashboard.timings.duration);
    expect2xx(dashboard, 'dashboard kpi');

    const page = ((__ITER % Number(__ENV.ADMIN_10K_MAX_PAGES || 100)) + 1);
    const employees = http.get(`${baseUrl}/api/v1/employees?per_page=100&page=${page}`, headers());
    employeeListLatency.add(employees.timings.duration);
    expect2xx(employees, 'employees paginated list');

    const search = http.get(`${baseUrl}/api/v1/employees?per_page=50&search=${encodeURIComponent(__ENV.ADMIN_10K_SEARCH || 'a')}`, headers());
    employeeSearchLatency.add(search.timings.duration);
    expect2xx(search, 'employees search');
  });

  sleep(Number(__ENV.ADMIN_10K_SLEEP || 1));
}
