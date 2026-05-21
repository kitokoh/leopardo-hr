import { expect, test } from '@playwright/test';

test.describe('Client web auth smoke', () => {
  test('employee or HR manager can sign in and reach a tenant-backed dashboard', async ({ page }) => {
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
            },
            company: {
              id: 'company-1',
              name: 'TechCorp Algerie SARL',
              language: 'fr',
              timezone: 'Africa/Algiers',
              currency: 'DZD',
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
            employees_total: 7,
            employees_active: 6,
            departments: 3,
            today_attendance: 4,
            pending_absences: 2,
          },
        }),
      });
    });

    await page.route('**/api/v1/dashboard/recent-activity?limit=5', async (route) => {
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

    await page.goto('/auth/login');
    await page.getByPlaceholder(/email/i).fill('fatima.meziane@techcorp-algerie.dz');
    await page.getByPlaceholder(/password|mot de passe/i).fill('password123');
    await page.getByRole('button', { name: /sign in|se connecter/i }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('body')).toContainText('Tableau de bord');
    await expect(page.locator('body')).toContainText('6');
    await expect(page.locator('body')).toContainText('7 total');
    await expect(page.locator('body')).toContainText('employee.updated');
  });
});
