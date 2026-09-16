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

    await page.goto('/?lang=en&utm_source=e2e_quick_trial', {
      waitUntil: 'networkidle',
    });

    const quickTrialForm = page.locator('section form').first();
    await quickTrialForm.locator('input[type="email"]').fill(email);

    const submitButton = quickTrialForm.locator('button[type="submit"]');
    await expect(submitButton).toBeVisible();
    await expect(submitButton).toBeEnabled();

    const [signupResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/signup'), { timeout: 30000 }),
      submitButton.click(),
    ]);

    expect(signupResponse.status()).toBe(201);
    await expect(page.locator('body')).toContainText(/request received|demande recue|24 business hours|24h/i);
  });

  test('le premier écran du tunnel tient sans scroll sur mobile 360 px (#7489)', async ({ page }) => {
    // Critère d'acceptation 1 : « e-mail, nom de l'espace, CTA, bouton Google,
    // liens légaux » doivent être VISIBLES SANS SCROLL sur un écran de 360 px.
    //
    // Mesure retenue : la position réelle de chaque élément exigé dans le
    // premier écran (et non la hauteur totale du document — la page garde
    // légitimement d'autres contenus plus bas). Géométrie mesurée le
    // 2026-09-16 à 360×640 : e-mail 345→387, espace 431→473, liens légaux
    // 490→507, CTA 545→597 — tout tient dans les 640 px.
    const viewportHeight = 640;
    await page.setViewportSize({ width: 360, height: viewportHeight });
    await page.goto('/signup?lang=en', { waitUntil: 'networkidle' });

    const form = page.locator('main form').first();
    const required = [
      { name: 'e-mail', locator: form.getByLabel(/email professionnel|email/i) },
      { name: "nom de l'espace", locator: form.getByLabel(/entreprise|company/i) },
      { name: 'bouton Google', locator: page.getByTestId('google-auth-button') },
      { name: 'CTA', locator: form.locator('button[type="submit"]') },
      { name: 'liens légaux', locator: form.getByRole('link').first() },
    ];

    for (const { name, locator } of required) {
      await expect(locator, `${name} doit être visible sans scroll`).toBeVisible();
      const box = await locator.boundingBox();
      expect(box, `${name} doit avoir une boîte mesurable`).not.toBeNull();
      expect(
        Math.round((box?.y ?? 0) + (box?.height ?? 0)),
        `${name} dépasse le premier écran (${viewportHeight} px)`,
      ).toBeLessThanOrEqual(viewportHeight);
    }
  });

  test('captures a trial signup request from the public /signup page', async ({ page }) => {
    const timestamp = Date.now();
    const email = `trial.lead.${timestamp}@example.com`;

    await page.goto('/signup?lang=en&utm_source=e2e&plan=pilot', {
      waitUntil: 'networkidle',
    });

    // #7489 — le tunnel s'ouvre DIRECTEMENT sur l'écran d'identité : plus de
    // choix de profil avant l'entrée dans l'espace (il devient la première
    // question de l'entretien #7493), donc plus d'écran intermédiaire à
    // traverser — c'est aussi ce qui rend le premier écran tenable sur mobile.
    await expect(page.locator('[data-testid="signup-profile-company"]')).toHaveCount(0);

    const signupForm = page.locator('main form').first();
    await expect(signupForm.getByLabel(/email professionnel|email/i)).toBeVisible();
    await expect(signupForm.getByLabel(/entreprise|company/i)).toBeVisible();
    await signupForm.getByLabel(/email professionnel|email/i).fill(email);
    await signupForm.getByLabel(/entreprise|company/i).fill('Leopardo Trial Co');
    // Le tunnel ne demande plus le rôle (le créateur EST le fondateur), ni la
    // taille d'équipe, ni le téléphone (l'e-mail est vérifié par code), ni le
    // pays (résolu côté serveur par géolocalisation). Seuls e-mail, entreprise
    // et CGU restent.
    await signupForm.locator('input[type="checkbox"]').check();
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

    const [signupResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/signup'), { timeout: 30000 }),
      submitButton.click(),
    ]);

    expect(signupResponse.status()).toBe(201);
    await expect(page.locator('body')).toContainText(/demande d'essai|trial request|24h|email/i);

    // #7249 — le PROFIL reste déclaré à l'API ; les outils et le métier ne sont
    // plus demandés dans le tunnel (le client les choisit ensuite dans
    // « Modules & plan »), donc le payload ne porte plus `modules`.
    const payload = JSON.parse(signupRequests[0]?.postData() ?? '{}');
    expect(payload.company_type).toBe('company');
  });

  test('captures a localized demo request without leaving the vitrine', async ({ page }) => {
    const timestamp = Date.now();
    const email = `fatima.benali.${timestamp}@example.com`;

    await page.goto('/demo?lang=fr&utm_source=e2e', { waitUntil: 'networkidle' });

    const demoForm = page.locator('#demo-form form').first();
    await demoForm.locator('input[name="name"]').fill('Fatima Benali');
    await demoForm.locator('input[name="email"]').fill(email);
    await demoForm.locator('input[name="company"]').fill('Atlas RH');
    await demoForm.locator('input[name="phone"]').fill('+213555111222');
    await demoForm.locator('select[name="employees"]').selectOption('51-200');
    await demoForm.locator('textarea[name="message"]').fill('Nous voulons qualifier Leopardo RH pour une equipe multi-sites.');
    await demoForm.locator('input[name="name"]').fill('Fatima Benali');
    await expect(demoForm.locator('input[name="name"]')).toHaveValue('Fatima Benali');
    const [demoResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/demo')),
      demoForm.locator('button[type="submit"]').click(),
    ]);

    expect(demoResponse.status()).toBe(201);
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
