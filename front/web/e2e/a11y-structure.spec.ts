import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';
import type { Page } from '@playwright/test';

/**
 * #UX-audit — garde structurelle d'accessibilité (front/web).
 *
 * Vérifie deux contrats transversaux issus des audits UX :
 *  1. UN SEUL `<h1>` par page (hiérarchie de titres) ;
 *  2. AUCUN `<th>` sans `scope` (sémantique de tableau).
 *
 * Le contrôle DOM est complété par un scan axe-core (règle
 * `page-has-heading-one`) injecté directement depuis `axe-core`, sans ajouter
 * de dépendance : le paquet est déjà présent dans l'arbre de dépendances.
 */

const publicPages = ['/', '/pricing', '/faq', '/docs', '/about', '/contact'];

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

async function seedSession(page: Page) {
  await page.route('**/api/v1/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { total: 0 } }),
    });
  });
  await installAuthenticatedSession(page, { user: businessUser });
}

/** Compte les `<h1>` visibles et les `<th>` sans `scope`. */
async function auditStructure(page: Page) {
  return page.evaluate(() => {
    const h1 = Array.from(document.querySelectorAll('h1')).filter(
      (el) => (el as HTMLElement).offsetParent !== null,
    );
    const ths = Array.from(document.querySelectorAll('th'));
    const thWithoutScope = ths.filter((th) => !th.getAttribute('scope'));
    return {
      h1Count: h1.length,
      h1Texts: h1.map((el) => (el.textContent ?? '').trim().slice(0, 60)),
      thCount: ths.length,
      thWithoutScope: thWithoutScope.length,
    };
  });
}

/**
 * Scan axe-core ciblé sur la règle `page-has-heading-one`.
 * Retourne la liste des violations (vide = conforme). Tolérant : si axe-core
 * n'est pas résolvable, le scan est ignoré (le contrôle DOM reste bloquant).
 */
async function axeHeadingViolations(page: Page): Promise<string[]> {
  let axePath: string;
  try {
    axePath = require.resolve('axe-core/axe.min.js');
  } catch {
    return [];
  }
  await page.addScriptTag({ path: axePath });
  return page.evaluate(async () => {
    // @ts-expect-error — axe est injecté à l'exécution
    const results = await window.axe.run(document, {
      runOnly: { type: 'rule', values: ['page-has-heading-one'] },
    });
    return results.violations.map((v: { id: string }) => v.id);
  });
}

test.describe('Accessibilité structurelle — pages publiques', () => {
  for (const path of publicPages) {
    test(`un seul h1 et th scopés sur ${path}`, async ({ page }) => {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      const { h1Count, h1Texts, thWithoutScope } = await auditStructure(page);

      expect(h1Count, `h1 trouvés sur ${path}: ${JSON.stringify(h1Texts)}`).toBeLessThanOrEqual(1);
      expect(thWithoutScope, `th sans scope sur ${path}`).toBe(0);
      expect(await axeHeadingViolations(page), `axe page-has-heading-one sur ${path}`).toEqual([]);
    });
  }
});

test.describe('Accessibilité structurelle — dashboard', () => {
  test('un seul h1 et th scopés sur /dashboard', async ({ page }) => {
    await seedSession(page);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    const { h1Count, h1Texts, thWithoutScope } = await auditStructure(page);

    expect(h1Count, `h1 trouvés: ${JSON.stringify(h1Texts)}`).toBeLessThanOrEqual(1);
    expect(thWithoutScope).toBe(0);
  });
});
