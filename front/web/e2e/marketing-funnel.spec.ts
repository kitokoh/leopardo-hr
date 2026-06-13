import { expect, test } from '@playwright/test';

function uniqueEmail(prefix: string): string {
  return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2, 8)}@example.com`;
}

test.describe('Marketing funnel preview', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(90_000);

  test('captures a one-field trial request from the homepage hero', async ({ page }) => {
    await page.goto('/?lang=en&utm_source=e2e_quick_trial', {
      waitUntil: 'domcontentloaded',
    });

    const quickTrialForm = page.locator('section form').first();
    await quickTrialForm.locator('input[type="email"]').fill(uniqueEmail('quick.trial'));

    const [signupResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/signup')),
      quickTrialForm.locator('button[type="submit"]').click(),
    ]);

    expect(signupResponse.status()).toBe(201);
    await expect(page.locator('body')).toContainText(/request received|demande recue|24 business hours|24h/i);
  });

  test('captures a trial signup request from the public /signup page', async ({ page }) => {
    await page.goto('/signup?lang=en&utm_source=e2e&plan=business', {
      waitUntil: 'domcontentloaded',
    });

    await expect(page.locator('body')).toContainText(/Try Leopardo RH|Testez Leopardo RH/i);

    const signupForm = page.locator('main form').first();
    await signupForm.locator('input[type="email"]').fill(uniqueEmail('trial.lead'));
    await signupForm.locator('input[name="company"]').fill('Leopardo Trial Co');
    await signupForm.locator('select[name="role"]').selectOption('manager');
    await signupForm.locator('select[name="employees"]').selectOption('11-50');
    await signupForm.locator('input[type="checkbox"]').check();
    const [signupResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/signup')),
      signupForm.locator('button[type="submit"]').click(),
    ]);

    expect(signupResponse.status()).toBe(201);
    await expect(page.locator('body')).toContainText(/demande d'essai|trial request|24h|email/i);
  });

  test('captures a localized demo request without leaving the vitrine', async ({ page }) => {
    await page.goto('/demo?lang=fr&utm_source=e2e', { waitUntil: 'domcontentloaded' });

    const demoForm = page.locator('#demo-form form').first();
    await demoForm.locator('input[name="name"]').fill('Fatima Benali');
    await demoForm.locator('input[name="email"]').fill('fatima.benali@example.com');
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
    await page.goto('/blog?lang=en&utm_source=e2e#newsletter', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    const newsletterInput = page.locator('#newsletter input[type="email"]');
    const newsletterButton = page.locator('#newsletter button[type="submit"]');
    const newsletterEmail = uniqueEmail('newsletter.lead');
    await newsletterInput.fill(newsletterEmail);
    await expect(newsletterInput).toHaveValue(newsletterEmail);
    const [newsletterResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().includes('/api/forms/newsletter')),
      newsletterButton.click(),
    ]);

    expect(newsletterResponse.status()).toBe(201);
    await expect(page.locator('#newsletter')).toContainText(/newsletter|success|reussie/i);
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
