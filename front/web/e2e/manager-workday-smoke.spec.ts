import { expect, test, type Page } from '@playwright/test';

async function mockManagerSession(page: Page) {
  await page.route('**/api/v1/auth/login', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 101,
          first_name: 'Fatima',
          last_name: 'Meziane',
          email: 'fatima.meziane@techcorp-algerie.dz',
          role: 'manager',
          manager_role: 'rh',
          language: 'fr',
          is_rtl: false,
        },
        token: 'client-web-token',
        token_type: 'Bearer',
      }),
    });
  });

  await page.route('**/api/v1/auth/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
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
            employees: true,
            attendance: true,
            absences: true,
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
        },
      }),
    });
  });

  await page.route('**/api/v1/dashboard/summary', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          employees_total: 42,
          employees_active: 39,
          departments: 5,
          today_attendance: 31,
          pending_absences: 4,
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
            action: 'absence.requested',
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
          score: 82,
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

  await page.route('**/api/v1/auth/logout', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true }),
    });
  });

  await page.route('**/api/v1/employees?per_page=12', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 501,
            first_name: 'Nadia',
            last_name: 'Kaci',
            email: 'nadia.kaci@techcorp-algerie.dz',
            role: 'employee',
            status: 'active',
            matricule: 'EMP-501',
          },
          {
            id: 502,
            first_name: 'Karim',
            last_name: 'Aouad',
            email: 'karim.aouad@techcorp-algerie.dz',
            role: 'manager',
            status: 'active',
            matricule: 'EMP-502',
          },
        ],
        meta: {
          total: 42,
        },
      }),
    });
  });

  await page.route('**/api/v1/attendance/today', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          mode: 'collection',
          items: [
            {
              employee_id: 501,
              name: 'Nadia Kaci',
              matricule: 'EMP-501',
              checked_in: true,
              check_in_time: '08:01',
              check_out_time: null,
              status: 'present',
              hours_worked: 6.5,
            },
            {
              employee_id: 502,
              name: 'Karim Aouad',
              matricule: 'EMP-502',
              checked_in: false,
              check_in_time: null,
              check_out_time: null,
              status: 'late',
              hours_worked: 0,
            },
          ],
        },
      }),
    });
  });

  await page.route('**/api/v1/absences', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 9001,
            start_date: '2026-05-25',
            end_date: '2026-05-27',
            status: 'pending',
            reason: 'Conges familiaux',
            days_count: 3,
            absence_type: {
              name: 'Conges payes',
            },
          },
        ],
      }),
    });
  });
}

test.describe('Client web manager workday smoke', () => {
  test('HR manager can move through dashboard, team, attendance and absences then logout', async ({ page }) => {
    await mockManagerSession(page);

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/adresse email|email address/i).fill('fatima.meziane@techcorp-algerie.dz');
    await page.getByLabel(/^mot de passe$|^password$/i).fill('password123');
    await page.getByRole('button', { name: /sign in|se connecter/i }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('body')).toContainText('Tableau de bord');
    await expect(page.locator('body')).toContainText('TechCorp Algerie SARL');
    await expect(page.locator('body')).toContainText('absence.requested');

    await page.locator('aside a[href="/employees"]').click();
    await expect(page).toHaveURL(/\/employees$/);
    await expect(page.locator('body')).toContainText('Total equipe');
    await expect(page.locator('body')).toContainText('42');
    await expect(page.locator('body')).toContainText('Nadia Kaci');
    await expect(page.locator('body')).toContainText('EMP-501');

    await page.locator('aside a[href="/attendance"]').click();
    await expect(page).toHaveURL(/\/attendance$/);
    await expect(page.locator('body')).toContainText('Manager');
    await expect(page.locator('body')).toContainText('Nadia Kaci');
    await expect(page.locator('body')).toContainText('present');

    await page.locator('aside a[href="/absences"]').click();
    await expect(page).toHaveURL(/\/absences$/);
    await expect(page.locator('body')).toContainText('Demandes visibles');
    await expect(page.locator('body')).toContainText('Conges payes');
    await expect(page.locator('body')).toContainText('pending');

    await page.getByRole('button', { name: /Deconnexion|Logout/i }).click();
    await expect(page).toHaveURL(/\/auth\/login$/, { timeout: 10000 });
  });
});
