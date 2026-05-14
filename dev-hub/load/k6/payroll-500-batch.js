import http from 'k6/http';
import { check, group } from 'k6';
import { Trend } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const managerToken = __ENV.MANAGER_TOKEN || '';
const payrollRunId = __ENV.PAYROLL_RUN_ID || '';
const allowMutations = __ENV.ALLOW_PAYROLL_MUTATIONS === 'true';

const payrollBatchDuration = new Trend('payroll_500_batch_duration_ms');
const payrollSummaryDuration = new Trend('payroll_500_summary_duration_ms');

export const options = {
  scenarios: {
    payroll_500_batch: {
      executor: 'shared-iterations',
      vus: Number(__ENV.PAYROLL_500_VUS || 1),
      iterations: Number(__ENV.PAYROLL_500_ITERATIONS || 1),
      maxDuration: __ENV.PAYROLL_500_MAX_DURATION || '2m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    payroll_500_batch_duration_ms: ['p(95)<30000'],
    payroll_500_summary_duration_ms: ['p(95)<3000'],
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

export default function () {
  if (!managerToken || !payrollRunId) {
    const health = http.get(`${baseUrl}/api/v1/health/live`, { headers: { Accept: 'application/json' } });
    check(health, { 'health fallback status is 2xx': (res) => res.status >= 200 && res.status < 300 });
    return;
  }

  group('500 employees payroll benchmark', () => {
    const summary = http.get(`${baseUrl}/api/v1/payroll-runs/${payrollRunId}/summary`, headers());
    payrollSummaryDuration.add(summary.timings.duration);
    check(summary, {
      'payroll summary status is 2xx': (res) => res.status >= 200 && res.status < 300,
    });

    if (allowMutations) {
      const calculate = http.post(`${baseUrl}/api/v1/payroll-runs/${payrollRunId}/calculate`, null, headers());
      payrollBatchDuration.add(calculate.timings.duration);
      check(calculate, {
        'payroll calculate accepted or business-rejected': (res) => [200, 202, 409, 422].includes(res.status),
        'payroll calculate under 30 seconds': (res) => res.timings.duration < 30000,
      });
    }
  });
}
