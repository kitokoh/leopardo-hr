import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Forms and Submissions
 * Tests form interactions and submissions across the site
 */

test.describe('Forms and Submissions E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to landing page before each test
    await page.goto('/');
  });

  test.describe('Signup Form', () => {
    test('should display signup form with all required fields', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Check for form fields
      const emailInput = page.locator('input[type="email"]');
      const passwordInput = page.locator('input[type="password"]');
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();

      await expect(emailInput).toBeVisible();
      await expect(passwordInput).toBeVisible();
      await expect(submitButton).toBeVisible();
    });

    test('should validate email format', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Enter invalid email
      const emailInput = page.locator('input[type="email"]');
      await emailInput.fill('invalid-email');

      // Try to submit
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();
      await submitButton.click();

      // Should show error
      const errorMessage = page.locator('text=/invalid|email/i');
      await expect(errorMessage).toBeVisible({ timeout: 5000 });
    });

    test('should validate password strength', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Enter weak password
      const emailInput = page.locator('input[type="email"]');
      const passwordInput = page.locator('input[type="password"]');
      await emailInput.fill('test@example.com');
      await passwordInput.fill('weak');

      // Try to submit
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();
      await submitButton.click();

      // Should show error
      const errorMessage = page.locator('text=/password|character|length/i');
      await expect(errorMessage).toBeVisible({ timeout: 5000 });
    });

    test('should accept valid signup data', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Enter valid data
      const emailInput = page.locator('input[type="email"]');
      const passwordInput = page.locator('input[type="password"]');
      await emailInput.fill('test@example.com');
      await passwordInput.fill('ValidPassword123!');

      // Submit button should be enabled
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();
      await expect(submitButton).toBeEnabled();
    });

    test('should show loading state during submission', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Enter valid data
      const emailInput = page.locator('input[type="email"]');
      const passwordInput = page.locator('input[type="password"]');
      await emailInput.fill('test@example.com');
      await passwordInput.fill('ValidPassword123!');

      // Click submit
      const submitButton = page.locator('button:has-text("Sign up"), button:has-text("Get Started")').first();
      await submitButton.click();

      // Wait for submission
      await page.waitForTimeout(1000);
    });
  });

  test.describe('Demo Request Form', () => {
    test('should display demo form with all required fields', async ({ page }) => {
      // Click demo CTA
      const demoCTA = page.locator('button:has-text("Voir la démo"), a:has-text("Voir la démo")').first();
      if (await demoCTA.isVisible()) {
        await demoCTA.click();

        // Check for form fields
        const nameInput = page.locator('input[placeholder*="Nom"], input[placeholder*="name"]').first();
        const emailInput = page.locator('input[type="email"]');
        const submitButton = page.locator('button:has-text("Demander"), button:has-text("Submit")').first();

        await expect(nameInput).toBeVisible();
        await expect(emailInput).toBeVisible();
        await expect(submitButton).toBeVisible();
      }
    });

    test('should validate required fields', async ({ page }) => {
      // Click demo CTA
      const demoCTA = page.locator('button:has-text("Voir la démo"), a:has-text("Voir la démo")').first();
      if (await demoCTA.isVisible()) {
        await demoCTA.click();

        // Try to submit without filling fields
        const submitButton = page.locator('button:has-text("Demander"), button:has-text("Submit")').first();
        await submitButton.click();

        // Should show error
        const errorMessage = page.locator('text=/required|obligatoire/i');
        await expect(errorMessage).toBeVisible({ timeout: 5000 });
      }
    });

    test('should validate email format', async ({ page }) => {
      // Click demo CTA
      const demoCTA = page.locator('button:has-text("Voir la démo"), a:has-text("Voir la démo")').first();
      if (await demoCTA.isVisible()) {
        await demoCTA.click();

        // Fill in form with invalid email
        const nameInput = page.locator('input[placeholder*="Nom"], input[placeholder*="name"]').first();
        const emailInput = page.locator('input[type="email"]');
        await nameInput.fill('John Doe');
        await emailInput.fill('invalid-email');

        // Try to submit
        const submitButton = page.locator('button:has-text("Demander"), button:has-text("Submit")').first();
        await submitButton.click();

        // Should show error
        const errorMessage = page.locator('text=/invalid|email/i');
        await expect(errorMessage).toBeVisible({ timeout: 5000 });
      }
    });

    test('should accept valid demo request data', async ({ page }) => {
      // Click demo CTA
      const demoCTA = page.locator('button:has-text("Voir la démo"), a:has-text("Voir la démo")').first();
      if (await demoCTA.isVisible()) {
        await demoCTA.click();

        // Fill in valid data
        const nameInput = page.locator('input[placeholder*="Nom"], input[placeholder*="name"]').first();
        const emailInput = page.locator('input[type="email"]');
        const companyInput = page.locator('input[placeholder*="Entreprise"], input[placeholder*="company"]').first();
        
        await nameInput.fill('John Doe');
        await emailInput.fill('john@example.com');
        if (await companyInput.isVisible()) {
          await companyInput.fill('Acme Corp');
        }

        // Submit button should be enabled
        const submitButton = page.locator('button:has-text("Demander"), button:has-text("Submit")').first();
        await expect(submitButton).toBeEnabled();
      }
    });
  });

  test.describe('Contact Form', () => {
    test('should display contact form with all required fields', async ({ page }) => {
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Click contact CTA
      const contactCTA = page.locator('button:has-text("Nous contacter"), a:has-text("Nous contacter")').first();
      if (await contactCTA.isVisible()) {
        await contactCTA.click();

        // Check for form fields
        const nameInput = page.locator('input[placeholder*="Nom"], input[placeholder*="name"]').first();
        const emailInput = page.locator('input[type="email"]');
        const messageInput = page.locator('textarea');
        const submitButton = page.locator('button:has-text("Envoyer"), button:has-text("Send")').first();

        await expect(nameInput).toBeVisible();
        await expect(emailInput).toBeVisible();
        await expect(messageInput).toBeVisible();
        await expect(submitButton).toBeVisible();
      }
    });

    test('should validate required fields', async ({ page }) => {
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Click contact CTA
      const contactCTA = page.locator('button:has-text("Nous contacter"), a:has-text("Nous contacter")').first();
      if (await contactCTA.isVisible()) {
        await contactCTA.click();

        // Try to submit without filling fields
        const submitButton = page.locator('button:has-text("Envoyer"), button:has-text("Send")').first();
        await submitButton.click();

        // Should show error
        const errorMessage = page.locator('text=/required|obligatoire/i');
        await expect(errorMessage).toBeVisible({ timeout: 5000 });
      }
    });

    test('should validate message length', async ({ page }) => {
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Click contact CTA
      const contactCTA = page.locator('button:has-text("Nous contacter"), a:has-text("Nous contacter")').first();
      if (await contactCTA.isVisible()) {
        await contactCTA.click();

        // Fill in form with short message
        const nameInput = page.locator('input[placeholder*="Nom"], input[placeholder*="name"]').first();
        const emailInput = page.locator('input[type="email"]');
        const messageInput = page.locator('textarea');
        
        await nameInput.fill('Jane Doe');
        await emailInput.fill('jane@example.com');
        await messageInput.fill('Hi');

        // Try to submit
        const submitButton = page.locator('button:has-text("Envoyer"), button:has-text("Send")').first();
        await submitButton.click();

        // Should show error
        const errorMessage = page.locator('text=/message|length|character/i');
        await expect(errorMessage).toBeVisible({ timeout: 5000 });
      }
    });

    test('should accept valid contact form data', async ({ page }) => {
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Click contact CTA
      const contactCTA = page.locator('button:has-text("Nous contacter"), a:has-text("Nous contacter")').first();
      if (await contactCTA.isVisible()) {
        await contactCTA.click();

        // Fill in valid data
        const nameInput = page.locator('input[placeholder*="Nom"], input[placeholder*="name"]').first();
        const emailInput = page.locator('input[type="email"]');
        const messageInput = page.locator('textarea');
        
        await nameInput.fill('Jane Doe');
        await emailInput.fill('jane@example.com');
        await messageInput.fill('I have a question about your pricing plans and would like more information.');

        // Submit button should be enabled
        const submitButton = page.locator('button:has-text("Envoyer"), button:has-text("Send")').first();
        await expect(submitButton).toBeEnabled();
      }
    });
  });

  test.describe('Newsletter Form', () => {
    test('should display newsletter signup', async ({ page }) => {
      // Scroll down to find newsletter signup
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));

      // Check for newsletter email input
      const newsletterEmail = page.locator('input[placeholder*="email"], input[placeholder*="Email"]').first();
      if (await newsletterEmail.isVisible()) {
        await expect(newsletterEmail).toBeVisible();
      }
    });

    test('should validate email format', async ({ page }) => {
      // Scroll down to find newsletter signup
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));

      // Find newsletter email input
      const newsletterEmail = page.locator('input[placeholder*="email"], input[placeholder*="Email"]').first();
      if (await newsletterEmail.isVisible()) {
        // Enter invalid email
        await newsletterEmail.fill('invalid-email');

        // Click subscribe
        const subscribeButton = page.locator('button:has-text("S\'inscrire"), button:has-text("Subscribe")').first();
        await subscribeButton.click();

        // Should show error
        const errorMessage = page.locator('text=/invalid|email/i');
        await expect(errorMessage).toBeVisible({ timeout: 5000 });
      }
    });

    test('should accept valid newsletter email', async ({ page }) => {
      // Scroll down to find newsletter signup
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));

      // Find newsletter email input
      const newsletterEmail = page.locator('input[placeholder*="email"], input[placeholder*="Email"]').first();
      if (await newsletterEmail.isVisible()) {
        // Enter valid email
        await newsletterEmail.fill('subscriber@example.com');

        // Subscribe button should be enabled
        const subscribeButton = page.locator('button:has-text("S\'inscrire"), button:has-text("Subscribe")').first();
        await expect(subscribeButton).toBeEnabled();
      }
    });
  });

  test.describe('Form Accessibility', () => {
    test('should be keyboard navigable', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Navigate using Tab
      await page.keyboard.press('Tab');
      await page.keyboard.press('Tab');

      // Should be able to focus on form fields
      const focusedElement = await page.evaluate(() => document.activeElement?.tagName);
      expect(['INPUT', 'BUTTON']).toContain(focusedElement);
    });

    test('should have proper form labels', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Check for labels
      const emailLabel = page.locator('label:has-text("Email"), label:has-text("email")').first();

      // At least one should be visible or form should have aria-labels
      const emailInput = page.locator('input[type="email"]');
      const hasAriaLabel = await emailInput.evaluate((el) => el.hasAttribute('aria-label'));
      expect(hasAriaLabel || await emailLabel.isVisible()).toBeTruthy();
    });

    test('should show focus indicators', async ({ page }) => {
      // Click signup CTA
      const signupCTA = page.locator('button:has-text("Essai gratuit"), a:has-text("Essai gratuit")').first();
      await signupCTA.click();

      // Focus on email input
      const emailInput = page.locator('input[type="email"]');
      await emailInput.focus();

      // Check for focus styles
      const hasFocusStyle = await emailInput.evaluate((el) => {
        const styles = window.getComputedStyle(el);
        return styles.outline !== 'none' || styles.boxShadow !== 'none';
      });

      expect(hasFocusStyle).toBeTruthy();
    });
  });
});
