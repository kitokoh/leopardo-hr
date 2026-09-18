import { expect, test, type Page } from '@playwright/test';

/**
 * #7496 — E2E BLOQUANTS du tracking du funnel d'acquisition.
 *
 * Vérifie, en local mocké (aucun backend réel), que chaque étape du tunnel
 * émet son beacon first-party vers `/api/forms/funnel-event` :
 *   signup_view → signup_email_submitted → signup_otp_sent,
 * avec un correlation_id stable et l'attribution (source/utm) captée au
 * premier écran — et qu'AUCUN beacon ne part sans consentement (#7593).
 *
 * Exécutée en CI par `.github/workflows/funnel-e2e-gate.yml` sur toute PR
 * touchant les fichiers du tunnel (SignupForm, proxy.ts, OnboardingWizard,
 * funnel.ts, cette spec). Le parcours complet contre la vraie API (OTP réel,
 * provisioning) reste couvert par `funnel.spec.ts` (suite staging-funnel).
 */

type FunnelBeacon = {
  event: string;
  correlation_id: string;
  attribution?: Record<string, string>;
  context?: Record<string, unknown>;
};

/** Consentement mesure d'audience accordé (version 1, #7593). */
async function grantAnalyticsConsent(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const state = JSON.stringify({
      version: 1,
      necessary: true,
      analytics: true,
      marketing: false,
      decidedAt: new Date().toISOString(),
    });
    document.cookie = `leopardo_consent=${encodeURIComponent(state)}; Path=/; SameSite=Lax`;
  });
}

async function collectBeacons(page: Page, sink: FunnelBeacon[]): Promise<void> {
  await page.route('**/api/forms/funnel-event', async (route) => {
    try {
      sink.push(route.request().postDataJSON() as FunnelBeacon);
    } catch {
      // corps illisible : le test échouera sur les assertions
    }
    await route.fulfill({
      status: 202,
      contentType: 'application/json',
      body: JSON.stringify({ success: true }),
    });
  });
}

async function mockSignupBackend(page: Page): Promise<void> {
  await page.route('**/api/v1/supported-countries', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [{ country: 'FR', label: 'France', available: true }],
      }),
    });
  });

  await page.route('**/api/forms/signup', async (route) => {
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: { id: 'lead-e2e-7496' },
      }),
    });
  });
}

test.describe('Tracking du funnel (#7496)', () => {
  test.setTimeout(120_000);

  test('émet signup_view → signup_email_submitted → signup_otp_sent avec corrélation et attribution stables', async ({ page }) => {
    const beacons: FunnelBeacon[] = [];
    await grantAnalyticsConsent(page);
    await collectBeacons(page, beacons);
    await mockSignupBackend(page);

    await page.goto('/signup?source=e2e_campaign&utm_source=facebook');

    // Étape 1 : la vue de /signup est mesurée.
    await expect.poll(() => beacons.map((b) => b.event)).toContain('signup_view');

    // Étape 2 : soumission du formulaire minimal (e-mail + espace + CGU).
    await page.locator('input[type="email"]').first().fill('e2e-funnel@example.com');
    await page.locator('input[name="company"], #company').first().fill('Acme E2E');
    await page.locator('input[type="checkbox"]').first().check();
    await page
      .locator('button[type="submit"]')
      .first()
      .click();

    // L'écran OTP confirme la transition réelle…
    await expect(
      page.locator('text=/vérifiez votre email|verify your email/i').first()
    ).toBeVisible({ timeout: 15_000 });

    // …et les jalons sont partis, dans l'ordre du plan de tracking.
    // (Dédupliqués : en dev, React StrictMode double-monte le composant — la
    // garde par ref empêche les doublons de re-render, pas de remontage.)
    await expect
      .poll(() => [...new Set(beacons.map((b) => b.event))])
      .toEqual(['signup_view', 'signup_email_submitted', 'signup_otp_sent']);

    // Corrélation : un seul parcours = un seul identifiant.
    const ids = new Set(beacons.map((b) => b.correlation_id));
    expect(ids.size).toBe(1);
    expect([...ids][0]?.length).toBeGreaterThanOrEqual(4);

    // Attribution captée au premier écran et conservée à chaque étape.
    for (const beacon of beacons) {
      expect(beacon.attribution?.source).toBe('e2e_campaign');
      expect(beacon.attribution?.utm_source).toBe('facebook');
    }

    // Critère 4 de #7496 : AUCUNE PII dans les beacons (ni e-mail, ni nom
    // d'espace, ni code).
    const serialized = JSON.stringify(beacons);
    expect(serialized).not.toContain('e2e-funnel@example.com');
    expect(serialized).not.toContain('Acme E2E');
    expect(serialized).not.toMatch(/password|otp_code/i);
  });

  test('n’émet AUCUN beacon sans consentement à la mesure d’audience (#7593)', async ({ page }) => {
    const beacons: FunnelBeacon[] = [];
    await collectBeacons(page, beacons);
    await mockSignupBackend(page);

    await page.goto('/signup');
    await expect(page.locator('input[type="email"]').first()).toBeVisible();

    // Laisse le temps à un éventuel beacon fautif de partir.
    await page.waitForTimeout(1500);
    expect(beacons).toHaveLength(0);
  });
});
