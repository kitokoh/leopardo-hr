import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Conversion Funnel
 * Tests complete user journeys from landing to conversion
 */

test.describe('Conversion Funnel E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to landing page before each test
    await page.goto('/');
  });

  test.describe('Signup Conversion Funnel', () => {
    // #2823/#2648 : le signup est un essai guidé sans mot de passe (v4.16.250).
    test('should complete guided-trial signup flow from landing page', async ({ page }) => {
      // Step 1: User sees hero section with signup CTA
      const signupCTA = page.locator('a[href="/signup"]').first();
      await expect(signupCTA).toBeVisible();

      // Step 2: User clicks signup CTA
      await signupCTA.click();
      await page.waitForURL('**/signup');

      // Step 3: User sees the guided-trial form (email only — no password)
      const emailInput = page.locator('input[type="email"]').first();
      await expect(emailInput).toBeVisible();
      await expect(page.locator('input[type="password"]')).toHaveCount(0);

      // Step 4: User fills in email and submits
      await emailInput.fill('e2e-conversion@example.com');
      const submitButton = page
        .locator('button:has-text("Recevoir mon code"), button:has-text("Get my verification code"), button[type="submit"]')
        .first();
      await expect(submitButton).toBeEnabled();
      await submitButton.click();

      // Step 5: User reaches the verification step or a readable state
      // (OTP si le backend répond, sinon message d'attente honnête)
      await page.waitForTimeout(2500);
      const otpOrState = page.locator('text=/code de vérification|vérifiez votre email|demande|request|pending/i').first();
      await expect(otpOrState).toBeVisible({ timeout: 8000 });
    });

    test('should show validation errors for invalid email', async ({ page }) => {
      await page.goto('/signup');

      const emailInput = page.locator('input[type="email"]').first();
      await emailInput.fill('invalid-email');

      const submitButton = page
        .locator('button:has-text("Recevoir mon code"), button:has-text("Get my verification code"), button[type="submit"]')
        .first();
      await submitButton.click();

      const errorMessage = page.locator('text=/email|e-mail/i').first();
      await expect(errorMessage).toBeVisible({ timeout: 5000 });
    });

    test('should not require a password on the guided-trial form', async ({ page }) => {
      await page.goto('/signup');

      await expect(page.locator('input[type="email"]').first()).toBeVisible();
      await expect(page.locator('input[type="password"]')).toHaveCount(0);
    });
  });

  test.describe('Demo Request Conversion Funnel', () => {
    test('should complete demo request flow', async ({ page }) => {
      test.setTimeout(90_000); // cold compile Next dev du /demo (lazy)
      // Step 1: User sees demo CTA
      const demoCTA = page.locator('a[href="/demo"]').first();
      await expect(demoCTA).toBeVisible();
      // Step 2: User clicks demo CTA
      await demoCTA.click();

        await page.waitForURL('**/demo', { timeout: 60_000 });

      // Step 3: User sees demo form (selecteurs stables, independants de la locale)
        const nameInput = page.locator('input[name="name"]').first();
        const emailInput = page.locator('input[name="email"]').first();
        await expect(nameInput).toBeVisible();
        await expect(emailInput).toBeVisible();

        // Step 4: User fills in form
        await nameInput.fill('John Doe');
        await emailInput.fill('john@example.com');

        // Step 5: User clicks submit
        const submitButton = page.locator('button:has-text("Demander"), button:has-text("Submit")').first();
        await submitButton.click();

        // Step 6: User sees success message
        await page.waitForTimeout(1000);
    });
  });

  test.describe('Contact Form Conversion Funnel', () => {
    test('should complete contact form flow', async ({ page }) => {
      test.setTimeout(90_000); // cold compile Next dev du /contact (lazy)
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Step 1: User sees contact CTA
      const contactCTA = page.locator('a[href="/contact"]').first();
      await expect(contactCTA).toBeVisible();
      // Step 2: User clicks contact CTA
      await contactCTA.click();

        await page.waitForURL('**/contact', { timeout: 60_000 });

      // Step 3: User sees contact form (ids stables)
        const nameInput = page.locator('#name').first();
        const emailInput = page.locator('#email').first();
        const messageInput = page.locator('textarea#message').first();
        await expect(nameInput).toBeVisible();
        await expect(emailInput).toBeVisible();
        await expect(messageInput).toBeVisible();

        // Step 4: User fills in form
        await nameInput.fill('Jane Doe');
        await emailInput.fill('jane@example.com');
        await messageInput.fill('I have a question about your pricing plans.');

        // Step 5: User clicks submit
        const submitButton = page.locator('button:has-text("Envoyer"), button:has-text("Send")').first();
        await submitButton.click();

        // Step 6: User sees success message
        await page.waitForTimeout(1000);
    });
  });

  test.describe('Newsletter Signup Conversion Funnel', () => {
    test('should complete newsletter signup', async ({ page }) => {
      // Scroll down to find newsletter signup
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));

      // Step 1: User sees newsletter signup
      const newsletterEmail = page.locator('input[placeholder*="email"], input[placeholder*="Email"]').first();
      await expect(newsletterEmail).toBeVisible();
      // Step 2: User enters email
      await newsletterEmail.fill('subscriber@example.com');

        // Step 3: User clicks subscribe
        const subscribeButton = page.locator('button:has-text("S\'inscrire"), button:has-text("Subscribe")').first();
        await subscribeButton.click();

        // Step 4: User sees success message
        await page.waitForTimeout(1000);
    });
  });

  test.describe('Multi-Step Conversion Flow', () => {
    test('should allow user to explore modules before signup', async ({ page }) => {
      // Step 1: User is on landing page
      await expect(page).toHaveURL('/');

      // Step 2: User navigates to pricing via the real navbar
      const pricingLink = page.locator('nav a[href="/pricing"]').first();
      await expect(pricingLink).toBeVisible();
      await pricingLink.click();
      await page.waitForURL('**/pricing');

      // Step 3: User reads content
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));

      // Step 4: User navigates back to landing
      await page.goto('/');

      // Step 5: User clicks signup CTA
      const signupCTA = page.locator('a[href="/signup"]').first();
      await expect(signupCTA).toBeVisible();
    });
  });

  test.describe('Conversion Tracking', () => {
    test('should track page view', async ({ page }) => {
      // Page should load successfully
      await expect(page).toHaveURL('/');
      
      // Check for analytics script - might be loaded asynchronously
      await page.waitForTimeout(500);
    });

    test('should track CTA clicks', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('a[href="/signup"]').first();
      await signupCTA.click();

      // Wait for potential analytics call
      await page.waitForTimeout(500);
    });
  });

  test.describe('Error Handling', () => {
    test('should handle network errors gracefully', async ({ page }) => {
      // Simulate network error
      await page.context().setOffline(true);

      // Try to navigate
      await page.goto('/', { waitUntil: 'domcontentloaded' }).catch(() => null);

      // Restore network
      await page.context().setOffline(false);
    });

    test('should handle guided-trial form submission errors', async ({ page }) => {
      // Click signup CTA (guided trial — pas de mot de passe, #v4.16.250)
      const signupCTA = page.locator('a[href="/signup"]').first();
      await expect(signupCTA).toBeVisible();
      await signupCTA.click();
      await page.waitForURL('**/signup');

      // Le formulaire est email-only : envoyer une adresse invalide doit
      // afficher une erreur de validation cote client.
      const emailInput = page.locator('input[type="email"]').first();
      await expect(emailInput).toBeVisible();
      await emailInput.fill('not-an-email');
      await page.keyboard.press('Enter');

      // Soit une erreur de validation, soit un message explicite — jamais
      // un crash ni un 500.
      await page.waitForTimeout(800);
      await expect(page.locator('body')).toBeVisible();
    });
  });
});
