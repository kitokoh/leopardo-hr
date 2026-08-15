import { expect, test, type Page } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

const visualUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    employees: true,
    attendance: true,
    absences: true,
    payroll: true,
    reports: true,
  },
  company: {
    id: 'company-1',
    name: 'TechCorp Algerie SARL',
    language: 'fr',
    timezone: 'Africa/Algiers',
    currency: 'DZD',
    features: {
      employees: true,
      attendance: true,
      absences: true,
      payroll: true,
      reports: true,
    },
  },
};

async function mockDashboard(page: Page) {
  await page.route('**/api/v1/dashboard/summary', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          employees_total: 32,
          employees_active: 29,
          departments: 5,
          today_attendance: 24,
          pending_absences: 3,
        },
      }),
    });
  });

  await page.route('**/api/v1/dashboard/recent-activity**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 1,
            action: 'absence.approved',
            auditable_type: 'App\\Models\\Absence',
            created_at: '2026-05-21T10:00:00Z',
          },
        ],
      }),
    });
  });

  await page.route('**/api/v1/launch-readiness', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          score: 86,
          status: 'ready',
          blockers: [],
          next_actions: [],
        },
      }),
    });
  });

  await page.route('**/api/v1/client-events', async (route) => {
    await route.fulfill({
      status: 202,
      contentType: 'application/json',
      body: JSON.stringify({ accepted: true }),
    });
  });

  await page.route('**/api/v1/notifications**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { total: 0 } }),
    });
  });

  // Le dashboard hydrate la session de démo via /demo-users (même contrat
  // que auth-client-smoke) : sans mock, la requête part vers le backend réel
  // → 404 → redirection /auth/login.
  await page.route('**/api/v1/demo-users', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          companies: [
            {
              name: 'TechCorp Algerie SARL',
              slug: 'techcorp-algerie',
              country: 'DZ',
              users: [
                {
                  email: 'fatima.meziane@techcorp-algerie.dz',
                  name: 'Fatima Meziane',
                  role: 'manager',
                  manager_role: 'rh',
                  password: 'password123',
                },
              ],
            },
          ],
        },
      }),
    });
  });
}

test.describe('Client visual smoke attachments', () => {
  test('captures login and dashboard reference screenshots', async ({ page }, testInfo) => {
    await mockDashboard(page);

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await testInfo.attach('client-login-page', {
      body: await page.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });

    await page.evaluate((user) => {
      window.localStorage.setItem('auth_token', 'visual-smoke-token');
      window.localStorage.setItem('auth_user', JSON.stringify(user));
    }, visualUser);

    await setSessionCookie(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText('TechCorp Algerie SARL');
    await expect(page.locator('body')).toContainText('absence.approved');

    await testInfo.attach('client-dashboard-page', {
      body: await page.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });
  });
});
