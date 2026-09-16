import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';
import type { Locator, Page } from '@playwright/test';

/**
 * #7556 — ergonomie du menu mobile du dashboard client.
 *
 * Sous `md` (768 px), le dashboard exposait DEUX boutons hamburger identiques
 * (rail métier + dropdown de modules), aucun accès direct aux réglages et des
 * panneaux déroulants (notifications, compte, modules) qui ne se fermaient
 * qu'en recliquant leur bouton.
 *
 * Contrats couverts ici :
 *  - un SEUL point d'entrée de navigation sous `md` : le tiroir
 *    (`dashboard-nav-toggle`), qui porte le rail métier, les modules
 *    entreprise/horizontaux et les liens compte/paramètres ;
 *  - entre `md` et `lg`, le panneau de modules (`dashboard-modules-nav-toggle`)
 *    reste le seul accès aux modules (la nav horizontale est `lg:flex`) ;
 *  - voile + verrouillage du scroll + fermeture par Échap + clic extérieur sur
 *    les panneaux de la barre, avec une hauteur bornée et défilante.
 *
 * Le compte démo standard n'a aucune verticale activée : la session mockée
 * active `restaurant` pour que le tiroir (et son rail métier) existent.
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
const rail = (page: Page) => page.getByTestId('business-rail');
const drawerAccount = (page: Page) => page.getByTestId('dashboard-drawer-account');
const modulesToggle = (page: Page) => page.getByTestId('dashboard-modules-nav-toggle');
const modulesPanel = (page: Page) => page.getByTestId('dashboard-modules-panel');
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

test.describe('Dashboard — tiroir mobile unique (#7556)', () => {
  test('un seul point d\'entrée sous md : le tiroir porte rail, modules et compte', async ({ page }) => {
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(drawerToggle(page)).toBeVisible();
    // Un seul hamburger sous `md` : le panneau de modules est réservé à md–lg.
    await expect(modulesToggle(page)).toBeHidden();

    await drawerToggle(page).click();
    await expect(rail(page)).toBeInViewport();

    // Modules entreprise/horizontaux dans le tiroir.
    await expect(rail(page).locator('a[href="/dashboard"]').first()).toBeVisible();

    // Liens compte/paramètres dans le tiroir.
    await expect(drawerAccount(page)).toBeVisible();
    await expect(drawerAccount(page).locator('a[href="/settings/account"]')).toBeVisible();
    await expect(drawerAccount(page).locator('a[href="/settings/team"]')).toBeVisible();
    await expect(drawerAccount(page).locator('a[href="/settings/security/2fa"]')).toBeVisible();
    await expect(drawerAccount(page).locator('a[href="/settings/notifications"]')).toBeVisible();
    await expect(page.getByTestId('dashboard-drawer-logout')).toBeVisible();
  });

  test('entre md et lg, le panneau de modules reste accessible (Échap le referme)', async ({ page }) => {
    await page.setViewportSize({ width: 900, height: 800 });
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(drawerToggle(page)).toBeHidden();
    await expect(modulesToggle(page)).toBeVisible();

    await modulesToggle(page).click();
    await expect(modulesPanel(page)).toBeVisible();
    expect((await panelBox(modulesPanel(page))).maxHeight).not.toBe('none');

    await page.keyboard.press('Escape');
    await expect(modulesPanel(page)).toHaveCount(0);
    await expect(modulesToggle(page)).toHaveAttribute('aria-expanded', 'false');
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

  test('le clic extérieur referme le panneau du compte', async ({ page }) => {
    await seedBusinessSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await userMenuToggle(page).click();
    await expect(userMenu(page)).toBeVisible();
    expect((await panelBox(userMenu(page))).maxHeight).not.toBe('none');

    await panelBackdrop(page).click();
    await expect(userMenu(page)).toHaveCount(0);
    await expect(userMenuToggle(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(panelBackdrop(page)).toHaveCount(0);
  });
});

test.describe('Dashboard — panneaux de la barre (#7556)', () => {
  test('le sous-menu RH se referme au clic extérieur', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await seedManagerSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    const hrToggle = page.getByTestId('dashboard-hr-menu');
    await expect(hrToggle).toBeVisible();
    await hrToggle.click();

    const hrPanel = page.getByTestId('dashboard-hr-menu-panel');
    await expect(hrPanel).toBeVisible();
    expect((await panelBox(hrPanel)).maxHeight).not.toBe('none');

    await panelBackdrop(page).click();
    await expect(hrPanel).toHaveCount(0);
  });

  test('ouvrir un panneau referme le précédent (un seul voile)', async ({ page }) => {
    await seedManagerSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await notificationsToggle(page).click();
    await expect(notificationsPanel(page)).toBeVisible();
    await expect(panelBackdrop(page)).toHaveCount(1);

    await userMenuToggle(page).click();
    await expect(userMenu(page)).toBeVisible();
    await expect(notificationsPanel(page)).toHaveCount(0);
    await expect(panelBackdrop(page)).toHaveCount(1);
  });
});
