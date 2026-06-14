import { expect, test, type Page } from '@playwright/test';

type AnalyticsWindow = Window & {
  __LEOPARDO_ANALYTICS_EVENTS__?: Array<{
    name: string;
    timestamp: string;
    properties: Record<string, string | number | boolean | null>;
  }>;
};

const managerUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'rh',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    can_create_employees: true,
  },
  company: {
    id: 'company-1',
    name: 'TechCorp Algerie SARL',
    language: 'fr',
    timezone: 'Africa/Algiers',
    currency: 'DZD',
    metadata: {
      onboarding_completed: true,
    },
  },
};

async function mockDashboardApis(page: Page) {
  await page.route('**/api/v1/dashboard/summary', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          employees_total: 7,
          employees_active: 6,
          departments: 3,
          today_attendance: 4,
          pending_absences: 2,
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
            action: 'employee.updated',
            auditable_type: 'App\\Models\\Employee',
            created_at: '2026-05-21T10:00:00Z',
          },
        ],
      }),
    });
  });
}

async function mockDemoUsers(page: Page) {
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
                  email: 'ahmed.benali@techcorp-algerie.dz',
                  name: 'Ahmed Benali',
                  role: 'manager',
                  manager_role: 'principal',
                  password: 'password123',
                },
              ],
            },
          ],
        },
      }),
    });
  });

  await page.route('**/api/v1/launch-readiness', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          score: 80,
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
}

async function captureAnalytics(page: Page) {
  await page.addInitScript(() => {
    (window as AnalyticsWindow).__LEOPARDO_ANALYTICS_EVENTS__ = [];
  });
}

async function analyticsEvents(page: Page) {
  return page.evaluate(() => (window as AnalyticsWindow).__LEOPARDO_ANALYTICS_EVENTS__ ?? []);
}

test.describe('Client web auth smoke', () => {
  test.describe.configure({ mode: 'serial' });

  test('employee or HR manager can sign in and reach a tenant-backed dashboard', async ({ page }) => {
    await captureAnalytics(page);
    await mockDemoUsers(page);

    await page.route('**/api/v1/auth/login', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: managerUser,
          token: 'client-web-token',
          token_type: 'Bearer',
        }),
      });
    });

    await page.route('**/api/v1/auth/me', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: managerUser }),
      });
    });

    await mockDashboardApis(page);

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('button', { name: /sign in|se connecter/i })).toBeVisible();
    await page.getByLabel(/adresse email|email address/i).fill('fatima.meziane@techcorp-algerie.dz');
    await page.getByLabel(/^mot de passe$|^password$/i).fill('password123');
    await page.getByRole('button', { name: /afficher le mot de passe|show password/i }).click();
    await expect(page.getByLabel(/^mot de passe$|^password$/i)).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: /masquer le mot de passe|hide password/i }).click();
    await page.getByRole('button', { name: /sign in|se connecter/i }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('body')).toContainText('Tableau de bord');
    await expect(page.locator('body')).toContainText('TechCorp Algerie SARL');
    await expect(page.locator('body')).toContainText('Actions prioritaires');

  });

  test('invalid credentials stay on login with a readable API error', async ({ page }) => {
    await captureAnalytics(page);
    await mockDemoUsers(page);

    await page.route('**/api/v1/auth/login', async (route) => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Identifiants invalides.' }),
      });
    });

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/adresse email|email address/i).fill('wrong@example.com');
    await page.getByLabel(/^mot de passe$|^password$/i).fill('bad-password');
    await page.getByRole('button', { name: /sign in|se connecter/i }).click();

    await expect(page).toHaveURL(/\/auth\/login$/);
    await expect(page.getByText('Identifiants invalides.')).toBeVisible();
    const events = await analyticsEvents(page);
    expect(events.find((event) => event.name === 'login_failed')?.properties.status).toBe(401);
  });

  test('expired dashboard session clears storage and returns to login', async ({ page }) => {
    await page.route('**/api/v1/dashboard/summary', async (route) => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Unauthenticated.' }),
      });
    });

    await page.route('**/api/v1/dashboard/recent-activity**', async (route) => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Unauthenticated.' }),
      });
    });

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      window.localStorage.setItem('auth_token', 'expired-token');
      window.localStorage.setItem('auth_user', JSON.stringify({
        id: 101,
        first_name: 'Fatima',
        last_name: 'Meziane',
        email: 'fatima.meziane@techcorp-algerie.dz',
        role: 'manager',
        manager_role: 'rh',
        language: 'fr',
        is_rtl: false,
      }));
    });

    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveURL(/\/auth\/login$/);
    await expect(page.locator('body')).toContainText(/Connexion a Leopardo RH|Sign in to Leopardo RH/);
    expect(await page.evaluate(() => window.localStorage.getItem('auth_token'))).toBeNull();
  });

  test('employee reaches a focused employee dashboard after session hydration', async ({ page }) => {
    await captureAnalytics(page);

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      window.localStorage.setItem('auth_token', 'employee-web-token');
      window.localStorage.setItem('auth_user', JSON.stringify({
        id: 301,
        first_name: 'Karim',
        last_name: 'Aouad',
        email: 'karim.aouad@techcorp-algerie.dz',
        role: 'employee',
        manager_role: null,
        language: 'fr',
        is_rtl: false,
        capabilities: {
          attendance: true,
          absences: true,
        },
        company: {
          id: 'company-1',
          name: 'TechCorp Algerie SARL',
          features: {
            attendance: true,
            absences: true,
          },
        },
      }));
    });

    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('body')).toContainText('Espace employe');
    await expect(page.locator('body')).toContainText('Pointage');
    await expect(page.locator('body')).toContainText('Bulletins');

    await expect.poll(async () => {
      const events = await analyticsEvents(page);
      return events.map((event) => event.name);
    }).toContain('dashboard_loaded');
  });

  test('demo account selection emits an analytics event and hydrates credentials', async ({ page }) => {
    await captureAnalytics(page);

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /try a demo account|acces demo|accès démo|demo access/i }).click();
    await page.getByRole('button', { name: /Ahmed Benali/i }).click();

    await expect(page.getByLabel(/adresse email|email address/i)).toHaveValue('ahmed.benali@techcorp-algerie.dz');
    await expect(page.getByLabel(/^mot de passe$|^password$/i)).toHaveValue('password123');

    const events = await analyticsEvents(page);
    expect(events.find((event) => event.name === 'demo_user_selected')?.properties.country).toBe('DZ');
  });
});
