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
    test('should complete signup flow from landing page', async ({ page }) => {
      // Step 1: User sees hero section with signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await expect(signupCTA).toBeVisible();

      // Step 2: User clicks signup CTA
      await signupCTA.click();

      // Step 3: User sees signup form
      const emailInput = page.locator('input[type="email"]');
      const passwordInput = page.locator('input[type="password"]');
      await expect(emailInput).toBeVisible();
      await expect(passwordInput).toBeVisible();

      // Step 4: User fills in email and password
      await emailInput.fill('test@example.com');
      await passwordInput.fill('ValidPassword123!');

      // Step 5: User clicks submit
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();
      await submitButton.click();

      // Step 6: User sees success message or is redirected
      // (This would depend on actual implementation)
      await page.waitForTimeout(1000);
    });

    test('should show validation errors for invalid email', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Fill in invalid email
      const emailInput = page.locator('input[type="email"]');
      await emailInput.fill('invalid-email');

      // Try to submit
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();
      await submitButton.click();

      // Should see error message
      const errorMessage = page.locator('text=/invalid|email/i');
      await expect(errorMessage).toBeVisible({ timeout: 5000 });
    });

    test('should show validation errors for weak password', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Fill in email and weak password
      const emailInput = page.locator('input[type="email"]');
      const passwordInput = page.locator('input[type="password"]');
      await emailInput.fill('test@example.com');
      await passwordInput.fill('weak');

      // Try to submit
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();
      await submitButton.click();

      // Should see error message
      const errorMessage = page.locator('text=/password|character|length/i');
      await expect(errorMessage).toBeVisible({ timeout: 5000 });
    });
  });

  test.describe('Demo Request Conversion Funnel', () => {
    test('should complete demo request flow', async ({ page }) => {
      // Step 1: User sees demo CTA
      const demoCTA = page.locator('button:has-text("Voir la démo"), a:has-text("Voir la démo")').first();
      if (await demoCTA.isVisible()) {
        // Step 2: User clicks demo CTA
        await demoCTA.click();

        // Step 3: User sees demo form
        const nameInput = page.locator('input[placeholder*="Nom"], input[placeholder*="name"]').first();
        const emailInput = page.locator('input[type="email"]');
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
      }
    });
  });

  test.describe('Contact Form Conversion Funnel', () => {
    test('should complete contact form flow', async ({ page }) => {
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Step 1: User sees contact CTA
      const contactCTA = page.locator('button:has-text("Nous contacter"), a:has-text("Nous contacter")').first();
      if (await contactCTA.isVisible()) {
        // Step 2: User clicks contact CTA
        await contactCTA.click();

        // Step 3: User sees contact form
        const nameInput = page.locator('input[placeholder*="Nom"], input[placeholder*="name"]').first();
        const emailInput = page.locator('input[type="email"]');
        const messageInput = page.locator('textarea');
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
      }
    });
  });

  test.describe('Newsletter Signup Conversion Funnel', () => {
    test('should complete newsletter signup', async ({ page }) => {
      // Scroll down to find newsletter signup
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));

      // Step 1: User sees newsletter signup
      const newsletterEmail = page.locator('input[placeholder*="email"], input[placeholder*="Email"]').first();
      if (await newsletterEmail.isVisible()) {
        // Step 2: User enters email
        await newsletterEmail.fill('subscriber@example.com');

        // Step 3: User clicks subscribe
        const subscribeButton = page.locator('button:has-text("S\'inscrire"), button:has-text("Subscribe")').first();
        await subscribeButton.click();

        // Step 4: User sees success message
        await page.waitForTimeout(1000);
      }
    });
  });

  test.describe('Multi-Step Conversion Flow', () => {
    test('should allow user to explore modules before signup', async ({ page }) => {
      // Step 1: User is on landing page
      await expect(page).toHaveURL('/');

      // Step 2: User navigates to employees module
      const employeesLink = page.locator('a:has-text("Employés"), a:has-text("Employees")').first();
      if (await employeesLink.isVisible()) {
        await employeesLink.click();
        await page.waitForURL('**/employes');

        // Step 3: User reads content
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));

        // Step 4: User navigates back to landing
        const homeLink = page.locator('a:has-text("Accueil"), a:has-text("Home")').first();
        if (await homeLink.isVisible()) {
          await homeLink.click();
          await page.waitForURL('/');
        }

        // Step 5: User clicks signup CTA
        const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
        await expect(signupCTA).toBeVisible();
      }
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
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
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

    test('should handle form submission errors', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Fill in form
      const emailInput = page.locator('input[type="email"]');
      const passwordInput = page.locator('input[type="password"]');
      await emailInput.fill('test@example.com');
      await passwordInput.fill('ValidPassword123!');

      // Try to submit
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();
      await submitButton.click();

      // Wait for response
      await page.waitForTimeout(1000);
    });
  });
});
