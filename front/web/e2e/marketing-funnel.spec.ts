import { expect, test } from '@playwright/test';

function uniqueEmail(prefix: string): string {
  return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2, 8)}@example.com`;
}

test.describe('Marketing funnel preview', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(90_000);

  test.beforeEach(async ({ page }) => {
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

  test('captures a trial signup request from the public /signup page', async ({ page }) => {
    const timestamp = Date.now();
    const email = `trial.lead.${timestamp}@example.com`;

    await page.goto('/signup?lang=en&utm_source=e2e&plan=business', {
      waitUntil: 'networkidle',
    });

    await expect(page.locator('body')).toContainText(/Try Leopardo RH|Testez Leopardo RH/i);

    const signupForm = page.locator('main form').first();
    await signupForm.getByLabel(/email professionnel|email/i).fill(email);
    await signupForm.getByLabel(/entreprise|company/i).fill('Leopardo Trial Co');
    await signupForm.getByLabel(/votre role|your role/i).selectOption('manager');
    await signupForm.getByLabel(/taille equipe|team size/i).selectOption('11-50');
    await signupForm.locator('input[type="checkbox"]').check();
    const submitButton = signupForm.locator('button[type="submit"]');
    await expect(submitButton).toBeVisible();

    const [signupResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/signup'), { timeout: 30000 }),
      submitButton.click(),
    ]);

    expect(signupResponse.status()).toBe(201);
    await expect(page.locator('body')).toContainText(/demande d'essai|trial request|24h|email/i);
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

  test('captures newsletter signup from localized blog content', async ({ page }) => {
    const timestamp = Date.now();
    const email = `newsletter.lead.${timestamp}@example.com`;

    await page.goto('/blog?lang=en&utm_source=e2e#newsletter', { waitUntil: 'networkidle' });

    const newsletterSection = page.locator('#newsletter');
    const newsletterInput = newsletterSection.locator('input[type="email"]');
    const newsletterButton = newsletterSection.locator('button[type="submit"]');

    await newsletterInput.fill(email);
    const [newsletterResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/newsletter')),
      newsletterButton.click(),
    ]);

    expect(newsletterResponse.status()).toBe(201);
    await expect(newsletterSection).toContainText(/newsletter|success|reussie/i);
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
