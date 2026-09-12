import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';
import type { Page } from '@playwright/test';

/**
 * #7225 — le rail métier (« Mon métier », verticales du tenant) est en
 * `hidden md:flex` : sous 768 px il n'était atteignable par aucun déclencheur.
 * Il devient un tiroir piloté par un bouton hamburger.
 *
 * Le compte démo standard n'a aucune verticale activée : la session mockée
 * active `restaurant` pour que le rail existe réellement.
 */

test.use({ viewport: { width: 390, height: 844 } });

const businessUser: Partial<AuthenticatedUser> = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: { restaurant: true, can_view_dashboard: true },
  company: {
    id: 'company-1',
    name: 'TechCorp Algerie SARL',
    language: 'fr',
    timezone: 'Africa/Algiers',
    currency: 'DZD',
    metadata: { onboarding_completed: true },
  },
};

async function seedBusinessSession(page: Page) {
  // Filet pour les endpoints non mockés par la fixture (dernier routeur
  // enregistré gagne : la fixture enregistrée après reste prioritaire).
  await page.route('**/api/v1/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { total: 0 } }),
    });
  });

  await installAuthenticatedSession(page, { user: businessUser });
}

const toggle = (page: Page) => page.getByTestId('dashboard-nav-toggle');
const rail = (page: Page) => page.getByTestId('business-rail');
const backdrop = (page: Page) => page.getByTestId('dashboard-nav-backdrop');

test.describe('Dashboard — navigation mobile du rail métier (#7225)', () => {
  test('le burger ouvre le rail en tiroir, verrouille le scroll, Échap referme', async ({ page }) => {
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(toggle(page)).toBeVisible();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(rail(page)).toHaveCSS('position', 'fixed');

    await toggle(page).click();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(backdrop(page)).toBeVisible();
    await expect(rail(page)).toBeInViewport();
    expect(await page.evaluate(() => document.body.style.overflow)).toBe('hidden');

    await page.keyboard.press('Escape');
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(backdrop(page)).toHaveCount(0);
    expect(await page.evaluate(() => document.body.style.overflow)).not.toBe('hidden');
  });

  test('le voile referme le tiroir', async ({ page }) => {
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await toggle(page).click();
    await expect(backdrop(page)).toBeVisible();

    const size = page.viewportSize()!;
    await backdrop(page).click({ position: { x: size.width - 20, y: Math.round(size.height / 2) } });

    await expect(backdrop(page)).toHaveCount(0);
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');
  });

  test('en desktop le rail redevient une colonne et le burger disparaît', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(toggle(page)).toBeHidden();
    await expect(rail(page)).toBeVisible();
    await expect.poll(async () => Math.round((await rail(page).boundingBox())?.x ?? -999)).toBe(0);
  });

  test('le dashboard mobile ne déborde pas horizontalement', async ({ page }) => {
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(toggle(page)).toBeVisible();

    const overflow = await page.evaluate(() => ({
      sw: document.documentElement.scrollWidth,
      cw: document.documentElement.clientWidth,
    }));
    expect(overflow.sw).toBeLessThanOrEqual(overflow.cw + 1);
  });
});
