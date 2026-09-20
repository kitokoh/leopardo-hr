import { expect, test } from '@playwright/test';

function uniqueEmail(prefix: string): string {
  return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2, 8)}@example.com`;
}

test.describe('Marketing funnel preview', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(90_000);

  test.beforeEach(async ({ page }) => {
    await page.route('**/api/v1/supported-countries', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            { country: 'DZ', label: 'Algérie', available: true },
            { country: 'FR', label: 'France', available: true },
          ],
        }),
      });
    });

    // Mock marketing form APIs to avoid dependency on live backend and provisioning delays
    await page.route('**/api/forms/signup', async (route) => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          provisioned: false,
          message: "Demande d'essai recue. Notre equipe vous contacte sous 24h ouvrables avec l'acces le plus adapte.",
          data: { id: 'lead-123', email: 'e2e@example.com' },
        }),
      });
    });

    await page.route('**/api/forms/demo', async (route) => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Demande envoyee avec succes.',
        }),
      });
    });

    await page.route('**/api/forms/newsletter', async (route) => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Inscription reussie.',
        }),
      });
    });
  });

  test('captures a one-field trial request from the homepage hero', async ({ page }) => {
    const timestamp = Date.now();
    const email = `quick.trial.${timestamp}@example.com`;

    // #7618 — jamais `networkidle` sur la home : la vidéo produit
    // (`product-demo.webm`) garde une connexion de streaming ouverte (206)
    // que Chromium met en pause → l'idle n'arrive jamais (timeout 90 s
    // constaté 4× en CI, toutes les requêtes pourtant terminées en ~1,6 s).
    // Les attentes déterministes qui suivent (visibilité/activation du
    // formulaire) portent la synchronisation.
    await page.goto('/?lang=en&utm_source=e2e_quick_trial', {
      waitUntil: 'domcontentloaded',
    });

    const quickTrialForm = page.locator('section form').first();
    const submitButton = quickTrialForm.locator('button[type="submit"]');
    await expect(submitButton).toBeVisible();

    // Sans `networkidle`, fill/clic peuvent précéder l'hydratation React :
    // l'input est CONTRÔLÉ, un fill pré-hydratation laisse l'état React vide
    // et le submit échoue en validation locale sans jamais émettre de fetch.
    // On rejoue donc fill + clic ensemble jusqu'à observer la réponse (la
    // route est mockée, un double POST est sans effet).
    await expect(async () => {
      await quickTrialForm.locator('input[type="email"]').fill(email);
      const [signupResponse] = await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/forms/signup'), { timeout: 5000 }),
        submitButton.click(),
      ]);
      expect(signupResponse.status()).toBe(201);
    }).toPass({ timeout: 30000 });
    await expect(page.locator('body')).toContainText(/request received|demande recue|24 business hours|24h/i);
  });

  test('captures a trial signup request from the public /signup page', async ({ page }) => {
    const timestamp = Date.now();
    const email = `trial.lead.${timestamp}@example.com`;

    await page.goto('/signup?lang=en&utm_source=e2e&plan=pilot', {
      waitUntil: 'domcontentloaded',
    });

    // QA onboarding 2026-09-14 : la page /signup n'affiche plus de hero
    // marketing (le formulaire EST l'écran, plus de récit à gauche/droite).
    // L'assertion porte donc sur le tunnel réellement présenté.
    // #7489 — le tunnel s'ouvre DIRECTEMENT sur les coordonnées : le choix du
    // profil (entreprise / indépendant) a été déplacé dans l'entretien de
    // préparation (#7493), et l'écran « outils + métier » avait déjà été
    // retiré (#7249).
    const signupForm = page.locator('main form').first();
    await expect(signupForm.getByLabel(/email professionnel|email/i)).toBeVisible();
    // Le tunnel ne demande plus le rôle (le créateur EST le fondateur), ni la
    // taille d'équipe, ni le téléphone (l'e-mail est vérifié par code), ni le
    // pays (résolu côté serveur par géolocalisation). #7853 — le nom
    // d'entreprise a été retiré à son tour (dérivé de l'e-mail côté serveur,
    // affiné dans l'entretien de préparation) : seuls e-mail et CGU restent.
    const submitButton = signupForm.locator('button[type="submit"]');
    await expect(submitButton).toBeVisible();

    // Tableau (et non variable réassignée) : TypeScript réduirait un `let`
    // affecté seulement dans une closure à `never` à la lecture.
    const signupRequests: import('@playwright/test').Request[] = [];
    page.on('request', (request) => {
      if (request.url().includes('/api/forms/signup')) {
        signupRequests.push(request);
      }
    });

    // Champs contrôlés : rejouer les fills avec le clic (cf. test du hero).
    await expect(async () => {
      await signupForm.getByLabel(/email professionnel|email/i).fill(email);
      await signupForm.locator('input[type="checkbox"]').check();
      const [signupResponse] = await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/forms/signup'), { timeout: 5000 }),
        submitButton.click(),
      ]);
      expect(signupResponse.status()).toBe(201);
    }).toPass({ timeout: 30000 });
    await expect(page.locator('body')).toContainText(/demande d'essai|trial request|24h|email/i);

    // #7489 — le profil (entreprise/indépendant) n'est PLUS déclaré par le
    // tunnel : `company_type` est absent du payload (nullable côté API, la
    // première question de l'entretien de préparation #7493 fera autorité).
    // Cette assertion verrouille le contrat dans les deux sens : le payload
    // existe, et il ne porte ni profil ni outils.
    const payload = JSON.parse(signupRequests[0]?.postData() ?? '{}');
    expect(payload.email).toBe(email);
    // #7853 — e-mail seul : pas de nom d'entreprise dans le payload non plus.
    expect(payload.company).toBeUndefined();
    expect(payload.company_type).toBeUndefined();
    expect(payload.modules).toBeUndefined();
  });

  test('captures a localized demo request without leaving the vitrine', async ({ page }) => {
    const timestamp = Date.now();
    const email = `fatima.benali.${timestamp}@example.com`;

    await page.goto('/demo?lang=fr&utm_source=e2e', { waitUntil: 'domcontentloaded' });

    const demoForm = page.locator('#demo-form form').first();
    // Champs contrôlés : rejouer les fills avec le clic (cf. test du hero).
    await expect(async () => {
      await demoForm.locator('input[name="name"]').fill('Fatima Benali');
      await demoForm.locator('input[name="email"]').fill(email);
      await demoForm.locator('input[name="company"]').fill('Atlas RH');
      await demoForm.locator('input[name="phone"]').fill('+213555111222');
      await demoForm.locator('select[name="employees"]').selectOption('51-200');
      await demoForm.locator('textarea[name="message"]').fill('Nous voulons qualifier Leopardo RH pour une equipe multi-sites.');
      await expect(demoForm.locator('input[name="name"]')).toHaveValue('Fatima Benali');
      const [demoResponse] = await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/forms/demo'), { timeout: 5000 }),
        demoForm.locator('button[type="submit"]').click(),
      ]);
      expect(demoResponse.status()).toBe(201);
    }).toPass({ timeout: 30000 });
    await expect(page.locator('body')).toContainText(/Demande envoyee|Request sent|Talep gonderildi/i);
  });

  test('captures newsletter signup from the footer on the homepage', async ({ page }) => {
    const timestamp = Date.now();
    const email = `newsletter.lead.${timestamp}@example.com`;

    // The blog route 404s unless NEXT_PUBLIC_ENABLE_BLOG=true (see
    // blog/layout.tsx, issue #1305), so the original /blog#newsletter target
    // made this test time out in CI. The footer NewsletterForm renders on
    // every landing page including the homepage — always available, no
    // feature flag involved (issue #1463).
    await page.goto('/?utm_source=e2e', { waitUntil: 'domcontentloaded' });

    const footer = page.locator('footer');
    const newsletterInput = footer.locator('input[type="email"]');
    const newsletterButton = footer.locator('button[type="submit"]');

    // Wait for the input to be visible, then laisse le re-rendu post-
    // hydratation se stabiliser (les navigateurs headless n'envoient pas
    // d'Accept-Language → SSR fr → bascule fr→en côté client → le <main>
    // est remplacé une fois ; scrollIntoViewIfNeeded sur l'ancien nœud
    // lève « Element is not attached to the DOM »).
    await newsletterInput.waitFor({ state: 'visible', timeout: 15_000 });
    await page.waitForTimeout(1500);

    await newsletterInput.fill(email);
    const [newsletterResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/newsletter')),
      newsletterButton.click(),
    ]);

    expect(newsletterResponse.status()).toBe(201);
    await expect(footer).toContainText(/newsletter|inscription|success|reussie/i);
  });

  test('keeps guide trial CTAs on the public guided signup flow', async ({ page }) => {
    const guideRoutes = [
      '/guides/rh-startup',
      '/guides/planning-employes',
      '/guides/checklist-paie',
    ];

    for (const route of guideRoutes) {
      await page.goto(route, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('a[href*="/auth/signup"]')).toHaveCount(0);
      await expect(page.locator('a[href^="/signup"]')).not.toHaveCount(0);
    }
  });

  test('keeps form API contracts explicit for invalid payloads', async ({ request }) => {
    const response = await request.post('/api/forms/demo', {
      data: {
        email: 'not-an-email',
        locale: 'en',
      },
    });
    const body = await response.json();

    expect(response.status()).toBe(400);
    expect(body).toMatchObject({
      success: false,
      error: 'VALIDATION_ERROR',
    });
    expect(Array.isArray(body.details)).toBe(true);
  });
});
