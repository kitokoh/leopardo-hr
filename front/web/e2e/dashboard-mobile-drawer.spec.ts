import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';
import type { Locator, Page } from '@playwright/test';

/**
 * #7556/#7908 — ergonomie du menu mobile du dashboard client.
 *
 * Depuis la refonte #7908, la navigation vit dans une SIDEBAR gauche unifiée
 * (`dashboard-sidebar`), toujours rendue : colonne fixe sur desktop, tiroir
 * sous `md` piloté par le hamburger de la topbar (`dashboard-nav-toggle`).
 *
 * Contrats couverts ici :
 *  - un SEUL point d'entrée de navigation sous `md` : le tiroir, qui porte le
 *    rail métier, les groupes Entreprise en accordéons, la section Plateforme
 *    et le bloc « Mon compte » (menu vers le haut, langue en sous-menu) ;
 *  - au-dessus de `md`, la sidebar est une colonne permanente (pas de burger)
 *    et les accordéons Entreprise s'ouvrent/se referment au clic ;
 *  - voile + verrouillage du scroll + fermeture par Échap + clic extérieur
 *    sur le panneau de notifications de la topbar, hauteur bornée défilante.
 *
 * Le compte démo standard n'a aucune verticale activée : la session mockée
 * active `restaurant` pour que le rail métier existe.
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

/** Session manager « socle » (aucune verticale) : sert les cas desktop/md. */
async function seedManagerSession(page: Page) {
  await page.route('**/api/v1/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { total: 0 } }),
    });
  });

  await installAuthenticatedSession(page);
}

const drawerToggle = (page: Page) => page.getByTestId('dashboard-nav-toggle');
const sidebar = (page: Page) => page.getByTestId('dashboard-sidebar');
const notificationsToggle = (page: Page) => page.getByTestId('dashboard-notifications-toggle');
const notificationsPanel = (page: Page) => page.getByTestId('dashboard-notifications-panel');
const userMenuToggle = (page: Page) => page.getByTestId('user-menu-toggle');
const userMenu = (page: Page) => page.getByTestId('user-menu');
const panelBackdrop = (page: Page) => page.getByTestId('dashboard-panel-backdrop');

const overflowOfBody = (page: Page) => page.evaluate(() => document.body.style.overflow);

async function panelBox(locator: Locator) {
  return locator.evaluate((element) => {
    const style = window.getComputedStyle(element);
    return { maxHeight: style.maxHeight, overflowY: style.overflowY };
  });
}

test.describe('Dashboard — tiroir mobile unique (#7556/#7908)', () => {
  test('un seul point d\'entrée sous md : le tiroir porte rail, entreprise, plateforme et compte', async ({ page }) => {
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(drawerToggle(page)).toBeVisible();

    await drawerToggle(page).click();
    await expect(sidebar(page)).toBeInViewport();

    // Rail métier dans le tiroir.
    await expect(page.getByTestId('business-rail')).toBeVisible();

    // Modules entreprise (lien direct « Tableau de bord ») dans le tiroir.
    await expect(sidebar(page).locator('a[href="/dashboard"]').first()).toBeVisible();

    // Section Plateforme : Modules / Abonnement & factures / Intégrations.
    await expect(page.getByTestId('sidebar-modules-link')).toBeVisible();
    await expect(page.getByTestId('sidebar-billing-link')).toBeVisible();
    await expect(page.getByTestId('sidebar-integrations-link')).toBeVisible();

    // Bloc « Mon compte » (menu vers le haut) dans le pied du tiroir.
    await userMenuToggle(page).click();
    await expect(userMenu(page)).toBeVisible();
    await expect(userMenu(page).locator('a[href="/settings/account"]')).toBeVisible();
    await expect(userMenu(page).locator('a[href="/settings/encaissements"]')).toBeVisible();
    await expect(userMenu(page).locator('a[href="/settings/branding"]')).toBeVisible();
    await expect(page.getByTestId('user-menu-support')).toBeVisible();
    await expect(page.getByTestId('user-menu-logout')).toBeVisible();

    // Langue : sous-menu du bloc compte (fr/en/tr/ar).
    await page.getByTestId('user-menu-language-toggle').click();
    await expect(page.getByTestId('user-menu-language-panel')).toBeVisible();
    for (const code of ['fr', 'en', 'tr', 'ar']) {
      await expect(page.getByTestId(`user-menu-language-${code}`)).toBeVisible();
    }
  });

  test('Échap referme le panneau des notifications et restitue le scroll', async ({ page }) => {
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await notificationsToggle(page).click();
    await expect(notificationsPanel(page)).toBeVisible();
    await expect(notificationsToggle(page)).toHaveAttribute('aria-expanded', 'true');
    expect(await overflowOfBody(page)).toBe('hidden');

    const box = await panelBox(notificationsPanel(page));
    expect(box.maxHeight).not.toBe('none');
    expect(box.overflowY).toBe('auto');

    await page.keyboard.press('Escape');
    await expect(notificationsPanel(page)).toHaveCount(0);
    await expect(notificationsToggle(page)).toHaveAttribute('aria-expanded', 'false');
    expect(await overflowOfBody(page)).not.toBe('hidden');
  });

  test('Échap referme le menu du compte', async ({ page }) => {
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await drawerToggle(page).click();
    await userMenuToggle(page).click();
    await expect(userMenu(page)).toBeVisible();
    expect((await panelBox(userMenu(page))).maxHeight).not.toBe('none');

    await page.keyboard.press('Escape');
    await expect(userMenu(page)).toHaveCount(0);
    await expect(userMenuToggle(page)).toHaveAttribute('aria-expanded', 'false');
  });
});

test.describe('Dashboard — sidebar desktop (#7908)', () => {
  test('les accordéons Entreprise s\'ouvrent et se referment au clic (état persisté)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await seedManagerSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    // Sidebar en colonne permanente : pas de burger.
    await expect(drawerToggle(page)).toBeHidden();
    await expect(sidebar(page)).toBeVisible();

    const hrToggle = page.getByTestId('dashboard-hr-menu');
    await expect(hrToggle).toBeVisible();
    await hrToggle.click();

    const hrPanel = page.getByTestId('dashboard-hr-menu-panel');
    await expect(hrPanel).toBeVisible();
    await expect(hrToggle).toHaveAttribute('aria-expanded', 'true');

    // L'état ouvert est persisté en localStorage (#7908).
    expect(await page.evaluate(() => window.localStorage.getItem('dashboard_sidebar_groups'))).toContain('"hr":true');

    await hrToggle.click();
    await expect(hrPanel).toHaveCount(0);
    await expect(hrToggle).toHaveAttribute('aria-expanded', 'false');
  });

  test('le clic extérieur referme le panneau des notifications (voile unique)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await seedManagerSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await notificationsToggle(page).click();
    await expect(notificationsPanel(page)).toBeVisible();
    await expect(panelBackdrop(page)).toHaveCount(1);

    await panelBackdrop(page).click();
    await expect(notificationsPanel(page)).toHaveCount(0);
    await expect(panelBackdrop(page)).toHaveCount(0);
  });
});
