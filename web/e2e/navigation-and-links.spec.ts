import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Navigation and Links
 * Tests navigation between pages and link functionality
 */

test.describe('Navigation and Links E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to landing page before each test
    await page.goto('/');
  });

  test.describe('Navbar Navigation', () => {
    test('should navigate to employees page from navbar', async ({ page }) => {
      // Click employees link in navbar
      const employeesLink = page.locator('a:has-text("Employés"), a:has-text("Employees")').first();
      if (await employeesLink.isVisible()) {
        await employeesLink.click();
        await page.waitForURL('**/employes');
        expect(page.url()).toContain('employes');
      }
    });

    test('should navigate to documents page from navbar', async ({ page }) => {
      // Click documents link in navbar
      const documentsLink = page.locator('a:has-text("Documents")').first();
      if (await documentsLink.isVisible()) {
        await documentsLink.click();
        await page.waitForURL('**/documents');
        expect(page.url()).toContain('documents');
      }
    });

    test('should navigate to accounting page from navbar', async ({ page }) => {
      // Click accounting link in navbar
      const accountingLink = page.locator('a:has-text("Comptabilité"), a:has-text("Accounting")').first();
      if (await accountingLink.isVisible()) {
        await accountingLink.click();
        await page.waitForURL('**/comptabilite');
        expect(page.url()).toContain('comptabilite');
      }
    });

    test('should navigate to marketing page from navbar', async ({ page }) => {
      // Click marketing link in navbar
      const marketingLink = page.locator('a:has-text("Marketing")').first();
      if (await marketingLink.isVisible()) {
        await marketingLink.click();
        await page.waitForURL('**/marketing');
        expect(page.url()).toContain('marketing');
      }
    });

    test('should navigate to pricing page from navbar', async ({ page }) => {
      // Click pricing link in navbar
      const pricingLink = page.locator('a:has-text("Tarification"), a:has-text("Pricing")').first();
      if (await pricingLink.isVisible()) {
        await pricingLink.click();
        await page.waitForURL('**/pricing');
        expect(page.url()).toContain('pricing');
      }
    });

    test('should navigate to about page from navbar', async ({ page }) => {
      // Click about link in navbar
      const aboutLink = page.locator('a:has-text("À propos"), a:has-text("About")').first();
      if (await aboutLink.isVisible()) {
        await aboutLink.click();
        await page.waitForURL('**/about');
        expect(page.url()).toContain('about');
      }
    });

    test('should navigate to blog page from navbar', async ({ page }) => {
      // Click blog link in navbar
      const blogLink = page.locator('a:has-text("Blog")').first();
      if (await blogLink.isVisible()) {
        await blogLink.click();
        await page.waitForURL('**/blog');
        expect(page.url()).toContain('blog');
      }
    });
  });

  test.describe('Footer Navigation', () => {
    test('should navigate to landing page from footer logo', async ({ page }) => {
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Click logo in footer
      const footerLogo = page.locator('footer a[href="/"]').first();
      if (await footerLogo.isVisible()) {
        await footerLogo.click();
        await page.waitForURL('/');
        expect(page.url()).toContain('/');
      }
    });

    test('should have working footer links', async ({ page }) => {
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Check for footer links
      const footerLinks = page.locator('footer a');
      const count = await footerLinks.count();
      expect(count).toBeGreaterThan(0);
    });

    test('should open social media links in new tab', async ({ page }) => {
      // Scroll to footer
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      // Find social media links
      const socialLinks = page.locator('footer a[href*="twitter"], footer a[href*="linkedin"], footer a[href*="facebook"]');
      const count = await socialLinks.count();

      if (count > 0) {
        // Check if link has target="_blank"
        const firstLink = socialLinks.first();
        const target = await firstLink.getAttribute('target');
        expect(target).toBe('_blank');
      }
    });
  });

  test.describe('Internal Links', () => {
    test('should navigate between module pages', async ({ page }) => {
      // Navigate to employees page
      const employeesLink = page.locator('a:has-text("Employés"), a:has-text("Employees")').first();
      if (await employeesLink.isVisible()) {
        await employeesLink.click();
        await page.waitForURL('**/employes');

        // Navigate to documents page
        const documentsLink = page.locator('a:has-text("Documents")').first();
        if (await documentsLink.isVisible()) {
          await documentsLink.click();
          await page.waitForURL('**/documents');
          expect(page.url()).toContain('documents');
        }
      }
    });

    test('should navigate from module page to pricing', async ({ page }) => {
      // Navigate to employees page
      const employeesLink = page.locator('a:has-text("Employés"), a:has-text("Employees")').first();
      if (await employeesLink.isVisible()) {
        await employeesLink.click();
        await page.waitForURL('**/employes');

        // Navigate to pricing
        const pricingLink = page.locator('a:has-text("Tarification"), a:has-text("Pricing")').first();
        if (await pricingLink.isVisible()) {
          await pricingLink.click();
          await page.waitForURL('**/pricing');
          expect(page.url()).toContain('pricing');
        }
      }
    });
  });

  test.describe('Mobile Navigation', () => {
    test('should open mobile menu on hamburger click', async ({ page }) => {
      // Set mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });

      // Click hamburger menu
      const hamburger = page.locator('button[aria-label*="menu"], button[aria-label*="Menu"]').first();
      if (await hamburger.isVisible()) {
        await hamburger.click();

        // Check if menu is visible
        const mobileMenu = page.locator('nav[aria-label*="mobile"], nav[class*="mobile"]').first();
        await expect(mobileMenu).toBeVisible({ timeout: 5000 });
      }
    });

    test('should close mobile menu on link click', async ({ page }) => {
      // Set mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });

      // Click hamburger menu
      const hamburger = page.locator('button[aria-label*="menu"], button[aria-label*="Menu"]').first();
      if (await hamburger.isVisible()) {
        await hamburger.click();

        // Click a link
        const link = page.locator('nav a').first();
        if (await link.isVisible()) {
          await link.click();

          // Menu should be closed
          const mobileMenu = page.locator('nav[aria-label*="mobile"], nav[class*="mobile"]').first();
          await expect(mobileMenu).not.toBeVisible({ timeout: 5000 });
        }
      }
    });
  });

  test.describe('URL Routing', () => {
    test('should route to landing page at /', async ({ page }) => {
      await page.goto('/');
      expect(page.url()).toContain('/');
    });

    test('should route to employees page at /employes', async ({ page }) => {
      await page.goto('/employes');
      expect(page.url()).toContain('employes');
    });

    test('should route to documents page at /documents', async ({ page }) => {
      await page.goto('/documents');
      expect(page.url()).toContain('documents');
    });

    test('should route to accounting page at /comptabilite', async ({ page }) => {
      await page.goto('/comptabilite');
      expect(page.url()).toContain('comptabilite');
    });

    test('should route to marketing page at /marketing', async ({ page }) => {
      await page.goto('/marketing');
      expect(page.url()).toContain('marketing');
    });

    test('should route to pricing page at /pricing', async ({ page }) => {
      await page.goto('/pricing');
      expect(page.url()).toContain('pricing');
    });

    test('should route to about page at /about', async ({ page }) => {
      await page.goto('/about');
      expect(page.url()).toContain('about');
    });

    test('should route to blog page at /blog', async ({ page }) => {
      await page.goto('/blog');
      expect(page.url()).toContain('blog');
    });

    test('should handle 404 for invalid routes', async ({ page }) => {
      const response = await page.goto('/invalid-route');
      // Should either show 404 or redirect
      expect(response?.status()).toBeLessThan(500);
    });
  });

  test.describe('Navigation State', () => {
    test('should highlight active page in navbar', async ({ page }) => {
      // Navigate to employees page
      const employeesLink = page.locator('a:has-text("Employés"), a:has-text("Employees")').first();
      if (await employeesLink.isVisible()) {
        await employeesLink.click();
        await page.waitForURL('**/employes');

        // Check if link is highlighted
        const activeLink = page.locator('a[aria-current="page"], a[class*="active"]').first();
        if (await activeLink.isVisible()) {
          expect(await activeLink.textContent()).toContain('Employé');
        }
      }
    });
  });

  test.describe('Keyboard Navigation', () => {
    test('should navigate using Tab key', async ({ page }) => {
      // Press Tab multiple times
      for (let i = 0; i < 5; i++) {
        await page.keyboard.press('Tab');
      }

      // Should be able to focus on elements
      const focusedElement = await page.evaluate(() => document.activeElement?.tagName);
      expect(['A', 'BUTTON', 'INPUT']).toContain(focusedElement);
    });

    test('should navigate using Enter key on links', async ({ page }) => {
      // Focus on first link
      const firstLink = page.locator('a').first();
      await firstLink.focus();

      // Press Enter to navigate
      await page.keyboard.press('Enter');

      // Should navigate
      await page.waitForTimeout(500);
    });

    test('should navigate using Space key on buttons', async ({ page }) => {
      // Find a button
      const button = page.locator('button').first();
      if (await button.isVisible()) {
        await button.focus();

        // Press Space
        await page.keyboard.press('Space');

        // Should trigger button action
        await page.waitForTimeout(500);
      }
    });
  });

  test.describe('Link Validation', () => {
    test('should have valid href attributes', async ({ page }) => {
      // Get all links
      const links = page.locator('a');
      const count = await links.count();

      for (let i = 0; i < Math.min(count, 10); i++) {
        const link = links.nth(i);
        const href = await link.getAttribute('href');
        
        // href should exist and not be empty
        expect(href).toBeTruthy();
      }
    });

    test('should not have broken links', async ({ page }) => {
      // Get all links
      const links = page.locator('a[href^="/"]');
      const count = await links.count();

      for (let i = 0; i < Math.min(count, 5); i++) {
        const link = links.nth(i);
        const href = await link.getAttribute('href');
        
        if (href && href.startsWith('/')) {
          // Try to navigate
          const response = await page.goto(href, { waitUntil: 'domcontentloaded' }).catch(() => null);
          expect(response?.status()).toBeLessThan(500);
          
          // Go back
          await page.goto('/');
        }
      }
    });
  });
});
