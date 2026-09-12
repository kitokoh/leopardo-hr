import { expect, test, type Page } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

/**
 * #7235 — Le portail client s'adapte au PROFIL déclaré à l'inscription.
 *
 * - profil `solo` (indépendant) : ni pointage ni gestion d'employés dans le
 *   menu, et le bandeau d'essai annonce les jours restants + le passage à Pro ;
 * - profil `company` avec sélection explicite : seuls les outils cochés sont
 *   dans le menu (le repli `rh` ne doit plus tout déverrouiller) ;
 * - aucune sélection (tenant historique) : comportement inchangé.
 */
function isoDaysFromNow(days: number): string {
  return new Date(Date.now() + days * 86_400_000).toISOString().slice(0, 10);
}

const baseUser = {
  id: 101,
  first_name: 'Sami',
  last_name: 'Bouzid',
  email: 'sami.bouzid@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: { can_view_dashboard: true },
  // Carte plateforme telle que la renvoie EmployeeResource : `rh` actif.
  features: { rh: true, accounting: true, cameras: false },
};

async function mockDashboard(page: Page) {
  await page.route('**/api/v1/dashboard/summary', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { employees_total: 3, employees_active: 3, departments: 1, today_attendance: 0, pending_absences: 0 } }),
    }),
  );
  await page.route('**/api/v1/dashboard/recent-activity**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
  );
  await page.route('**/api/v1/launch-readiness', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { score: 40, status: 'in_progress', blockers: [], next_actions: [] } }) }),
  );
  await page.route('**/api/v1/announcements**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { total: 0 } }) }),
  );
  await page.route('**/api/v1/client-events', (route) =>
    route.fulfill({ status: 202, contentType: 'application/json', body: JSON.stringify({ accepted: true }) }),
  );
  await page.route('**/api/v1/notifications**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { total: 0 } }) }),
  );
  await page.route('**/api/v1/demo-users', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { companies: [] } }) }),
  );
  await page.route('**/api/v1/onboarding-setup/checklist', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { steps: [] } }) }),
  );
  await page.route('**/api/v1/onboarding/checklist', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { steps: [] } }) }),
  );
}

async function bootDashboard(page: Page, user: unknown) {
  await mockDashboard(page);
  await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
  await page.evaluate((payload) => {
    window.localStorage.setItem('auth_token', 'company-profile-token');
    window.localStorage.setItem('auth_user', JSON.stringify(payload));
  }, user);
  await setSessionCookie(page);
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
  // Le bandeau d'essai est rendu dès que l'utilisateur est hydraté.
  await expect(page.locator('[data-testid="trial-banner"]')).toBeVisible();
}

test.describe('Profil d’entreprise — navigation et essai (#7235)', () => {
  test('un indépendant ne voit ni pointage ni employés, et son essai est compté', async ({ page }) => {
    await bootDashboard(page, {
      ...baseUser,
      company: {
        id: 'company-1',
        name: 'Solo Compta',
        language: 'fr',
        type: 'solo',
        modules: { accounting: true, reports: true, employees: false, attendance: false, payroll: false },
        status: 'trial',
        subscription_end: isoDaysFromNow(5),
      },
    });

    await expect(page.locator('header a[href="/attendance"]')).toHaveCount(0);
    await expect(page.locator('header a[href="/employees"]')).toHaveCount(0);

    const banner = page.locator('[data-testid="trial-banner"]');
    await expect(banner).toBeVisible();
    await expect(banner).toHaveAttribute('data-trial-days-left', '5');
    await expect(banner).toContainText(/5 jours/i);
    await expect(page.locator('[data-testid="trial-upgrade"]')).toBeVisible();
  });

  test('une entreprise ne voit que les outils qu’elle a cochés', async ({ page }) => {
    await bootDashboard(page, {
      ...baseUser,
      company: {
        id: 'company-2',
        name: 'Resto SARL',
        language: 'fr',
        type: 'company',
        modules: { employees: true, attendance: false, absences: true, payroll: false, accounting: true },
        status: 'trial',
        subscription_end: isoDaysFromNow(9),
      },
    });

    await expect(page.locator('header a[href="/employees"]')).toHaveCount(1);
    await expect(page.locator('header a[href="/absences"]')).toHaveCount(1);
    // Décochés : hors du menu, malgré `rh` actif dans la carte plateforme.
    await expect(page.locator('header a[href="/attendance"]')).toHaveCount(0);
    await expect(page.locator('header a[href="/payroll"]')).toHaveCount(0);
  });

  test('sans sélection déclarée, le menu reste celui d’avant', async ({ page }) => {
    await bootDashboard(page, {
      ...baseUser,
      company: {
        id: 'company-3',
        name: 'Legacy SARL',
        language: 'fr',
        status: 'trial',
        subscription_end: isoDaysFromNow(2),
      },
    });

    await expect(page.locator('header a[href="/attendance"]')).toHaveCount(1);
    await expect(page.locator('header a[href="/employees"]')).toHaveCount(1);
  });

  test('aucun bandeau d’essai hors période d’essai', async ({ page }) => {
    await mockDashboard(page);
    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await page.evaluate((payload) => {
      window.localStorage.setItem('auth_token', 'company-profile-token');
      window.localStorage.setItem('auth_user', JSON.stringify(payload));
    }, {
      ...baseUser,
      company: { id: 'company-4', name: 'Client SARL', language: 'fr', type: 'company', status: 'active', subscription_end: isoDaysFromNow(30) },
    });
    await setSessionCookie(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('[data-testid="trial-banner"]')).toHaveCount(0);
  });
});
