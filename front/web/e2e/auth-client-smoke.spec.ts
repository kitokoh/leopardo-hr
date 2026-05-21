import { expect, test, type Page } from '@playwright/test';

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

test.describe('Client web auth smoke', () => {
  test.describe.configure({ mode: 'serial' });

  test('employee or HR manager can sign in and reach a tenant-backed dashboard', async ({ page }) => {
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
    await expect(page.getByText(/Connexion a Leopardo RH|Sign in to Leopardo RH/).first()).toBeVisible();
    await page.getByLabel(/adresse email|email address/i).fill('fatima.meziane@techcorp-algerie.dz');
    await page.getByLabel(/^mot de passe$|^password$/i).fill('password123');
    await page.getByRole('button', { name: /afficher le mot de passe|show password/i }).click();
    await expect(page.getByLabel(/^mot de passe$|^password$/i)).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: /masquer le mot de passe|hide password/i }).click();
    await page.getByRole('button', { name: /sign in|se connecter/i }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('body')).toContainText('Tableau de bord');
    await expect(page.locator('body')).toContainText('6');
    await expect(page.locator('body')).toContainText('7 total');
    await expect(page.locator('body')).toContainText('employee.updated');
  });

  test('invalid credentials stay on login with a readable API error', async ({ page }) => {
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
});
