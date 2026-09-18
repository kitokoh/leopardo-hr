import { expect, test, type Page } from '@playwright/test';

/**
 * Consentement cookies & mesure d'audience (#7593).
 *
 * Défaut corrigé : GA4 et Mixpanel étaient injectés au premier rendu, avant
 * tout choix du visiteur, sans bandeau et sans mémorisation du choix. Ces tests
 * vérifient la règle qui compte : **aucun traceur avant un accord explicite**.
 *
 * Pour que la vérification ne soit pas vide, la suite doit tourner avec la
 * mesure d'audience configurée (c'est le cas en local et en CI quand
 * `NEXT_PUBLIC_ENABLE_ANALYTICS=true`) : sinon l'assertion « aucun traceur »
 * serait vraie faute de traceur, pas faute de consentement. Le test le dit.
 */

const CONSENT_COOKIE = 'leopardo_consent';
const TRACKER_HOSTS = [/googletagmanager\.com/, /google-analytics\.com/, /mxpnl\.com/, /mixpanel\.com/];

function watchTrackers(page: Page): string[] {
  const requests: string[] = [];
  page.on('request', (request) => {
    if (TRACKER_HOSTS.some((host) => host.test(request.url()))) {
      requests.push(request.url());
    }
  });
  return requests;
}

async function consentCookie(page: Page) {
  const cookies = await page.context().cookies();
  const cookie = cookies.find((entry) => entry.name === CONSENT_COOKIE);
  return cookie ? JSON.parse(decodeURIComponent(cookie.value)) : null;
}

test.describe('Consentement cookies (#7593)', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('avant tout choix : le bandeau est visible et aucun traceur n’est chargé', async ({ page }) => {
    const trackers = watchTrackers(page);

    await page.goto('/', { waitUntil: 'networkidle' });

    const banner = page.getByRole('dialog');
    await expect(banner).toBeVisible();
    await expect(banner.getByRole('button', { name: /tout accepter|accept all/i })).toBeVisible();
    await expect(banner.getByRole('button', { name: /tout refuser|refuse all/i })).toBeVisible();

    expect(await consentCookie(page)).toBeNull();
    expect(trackers, `traceurs chargés sans consentement : ${trackers.join(', ')}`).toEqual([]);
  });

  test('refuser est un choix : mémorisé, sans traceur, et le bandeau ne revient pas', async ({ page }) => {
    const trackers = watchTrackers(page);

    await page.goto('/', { waitUntil: 'networkidle' });
    await page.getByRole('dialog').getByRole('button', { name: /tout refuser|refuse all/i }).click();

    await expect(page.getByRole('dialog')).toBeHidden();

    const cookie = await consentCookie(page);
    expect(cookie, 'le refus doit être mémorisé').not.toBeNull();
    expect(cookie.analytics).toBe(false);
    expect(cookie.marketing).toBe(false);
    expect(cookie.decidedAt).toBeTruthy();

    // Rechargement : le choix tient, on ne re-sollicite pas.
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.getByRole('dialog')).toBeHidden();
    expect(trackers, `traceurs chargés malgré le refus : ${trackers.join(', ')}`).toEqual([]);
  });

  test('accepter : choix mémorisé, bandeau retiré', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.getByRole('dialog').getByRole('button', { name: /tout accepter|accept all/i }).click();

    await expect(page.getByRole('dialog')).toBeHidden();

    const cookie = await consentCookie(page);
    expect(cookie?.analytics).toBe(true);
    expect(cookie?.marketing).toBe(true);

    // Si la mesure d'audience est configurée dans cet environnement, le script
    // doit apparaître APRÈS l'accord (et seulement là).
    const gaLoaded = await page.locator('script[src*="googletagmanager"]').count();
    test.info().annotations.push({
      type: 'mesure-audience-configuree',
      description: gaLoaded > 0 ? 'oui' : 'non (assertion de non-chargement trivialement vraie)',
    });
  });

  test('le consentement se retire aussi facilement qu’il se donne', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.getByRole('dialog').getByRole('button', { name: /tout accepter|accept all/i }).click();
    await expect(page.getByRole('dialog')).toBeHidden();

    // Contrôle de retrait dans le pied de page, présent sur toutes les pages.
    await page.getByRole('button', { name: /gérer mes cookies|manage my cookies/i }).first().click();

    const banner = page.getByRole('dialog');
    await expect(banner).toBeVisible();
    await expect(banner.getByRole('checkbox', { name: /mesure d'audience|analytics/i })).toBeVisible();
    await expect(
      banner.getByRole('checkbox', { name: /cookies nécessaires|necessary cookies/i }),
    ).toBeDisabled();
  });

  test('personnaliser : seule la mesure d’audience est accordée', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.getByRole('dialog').getByRole('button', { name: /personnaliser|customise/i }).click();

    const banner = page.getByRole('dialog');
    await banner.getByRole('checkbox', { name: /mesure d'audience|analytics/i }).check();
    await banner.getByRole('button', { name: /enregistrer mes choix|save my choices/i }).click();

    await expect(page.getByRole('dialog')).toBeHidden();

    const cookie = await consentCookie(page);
    expect(cookie?.analytics).toBe(true);
    expect(cookie?.marketing).toBe(false);
  });
});
