import { expect, test } from './fixtures/authenticated';
import type { Page } from '@playwright/test';

async function mockManagerSession(page: Page) {
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
            // La timeline récente a des onglets « Aujourd'hui / Cette semaine » :
            // la donnée mockée doit être datée d'aujourd'hui pour apparaître.
            created_at: new Date().toISOString(),
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

  await page.route('**/api/v1/announcements**', async (route) => {
    // #3027 : le dashboard lit /announcements?per_page=1 — 401 réel → redirect
    // login → mock requis pour tous les tests dashboard.
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { total: 0 } }),
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
  test('HR manager can move through dashboard, team, attendance and absences then logout', async ({ authenticatedPage: page }) => {
    await mockManagerSession(page);

    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('body')).toContainText('Tableau de bord');
    await expect(page.locator('body')).toContainText('TechCorp Algerie SARL');
    await expect(page.locator('body')).toContainText('absence.requested');

    await page.locator('aside a[href="/employees"]').click();
    await expect(page).toHaveURL(/\/employees$/);
    await expect(page.locator('body')).toContainText('Total équipe');
    await expect(page.locator('body')).toContainText('42');
    await expect(page.locator('body')).toContainText('Nadia Kaci');
    await expect(page.locator('body')).toContainText('EMP-501');

    await page.locator('aside a[href="/attendance"]').click();
    await expect(page).toHaveURL(/\/attendance$/);
    await expect(page.locator('body')).toContainText('Manager');
    await expect(page.locator('body')).toContainText('Nadia Kaci');
    await expect(page.locator('body')).toContainText(/Présents|present/i);

    await page.locator('aside a[href="/absences"]').click();
    await expect(page).toHaveURL(/\/absences$/);
    await expect(page.locator('body')).toContainText('Absences');
    await expect(page.locator('body')).toContainText('Conges payes');
    await expect(page.locator('body')).toContainText('pending');

    await page.getByRole('button', { name: /Déconnexion|Deconnexion|Logout/i }).click();
    await expect(page).toHaveURL(/\/auth\/login$/, { timeout: 10000 });
  });
});
