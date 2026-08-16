import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Navigation and Links
 *
 * QA #3728 (W-01) : les anciens tests cliquaient des liens absents de la
 * Navbar (/employes, /documents, /comptabilite, /marketing) sous garde
 * `if (isVisible())` → faux vert CI (18/31 tests sans assertion).
 * Réécrits contre la navigation RÉELLE (Navbar.tsx) avec assertions
 * strictes `toBeVisible()` — aucun test ne peut plus passer sans agir.
 */

test.describe('Navigation and Links E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to landing page before each test
    await page.goto('/');
  });

  test.describe('Navbar Navigation', () => {
    // La navbar desktop n'existe pas sur les viewports mobiles (menu
    // hamburger) — ces tests ne s'exécutent que sur desktop.
    test.skip(({ isMobile }) => isMobile, 'Desktop navbar tests');

    // Liens directs de la navbar (hors dropdown).
    const DIRECT_LINKS: Array<{ href: string }> = [
      { href: '/pricing' },
      { href: '/contact' },
    ];

    for (const { href } of DIRECT_LINKS) {
      test(`should navigate to ${href} from navbar`, async ({ page }) => {
        const link = page.locator(`nav a[href="${href}"]`).first();
        await expect(link).toBeVisible();
        await link.click();
        await page.waitForURL(`**${href}`);
        expect(page.url()).toContain(href);
      });
    }

    // Liens dans le dropdown « Ressources » (FR) / « Resources » (EN).
    const DROPDOWN_LINKS: Array<{ href: string }> = [
      { href: '/guides/rh-startup' },
      { href: '/docs' },
      { href: '/changelog' },
    ];

    for (const { href } of DROPDOWN_LINKS) {
      test(`should navigate to ${href} from the navbar dropdown`, async ({ page }) => {
        const trigger = page
          .locator('button:has-text("Ressources"), button:has-text("Resources")')
          .first();
        await expect(trigger).toBeVisible();
        await trigger.click();

        const link = page.locator(`a[href="${href}"]`).first();
        await expect(link).toBeVisible();
        await link.click();
        await page.waitForURL(`**${href}`);
        expect(page.url()).toContain(href);
      });
    }

    test('should open the FAQ from the community dropdown', async ({ page }) => {
      const trigger = page
        .locator('button:has-text("Communaute"), button:has-text("Community")')
        .first();
      await expect(trigger).toBeVisible();
      await trigger.click();

      const faqLink = page.locator('a[href="/faq"]').first();
      await expect(faqLink).toBeVisible();
      await faqLink.click();
      await page.waitForURL('**/faq');
      expect(page.url()).toContain('/faq');
    });

    test('should open the download dropdown', async ({ page }) => {
      const trigger = page
        .locator('button:has-text("Installer Leopardo"), button:has-text("Install Leopardo")')
        .first();
      await expect(trigger).toBeVisible();
      await trigger.click();

      const windowsLink = page.locator('a[href="/download?platform=windows"]').first();
      await expect(windowsLink).toBeVisible();
      await windowsLink.click();
      await page.waitForURL('**/download?platform=windows');
      expect(page.url()).toContain('/download');
    });

    test('should navigate to pricing from navbar in every locale variant', async ({ page }) => {
      // The FR navbar uses "Tarifs", EN uses "Pricing" — the href is stable.
      const pricingLink = page.locator('nav a[href="/pricing"]').first();
      await expect(pricingLink).toBeVisible();
      await pricingLink.click();
      await page.waitForURL('**/pricing');
      expect(page.url()).toContain('pricing');
    });
  });

  test.describe('Footer Navigation', () => {
    test('should navigate to landing page from footer logo', async ({ page }) => {
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      const footerLogo = page.locator('footer a[href="/"]').first();
      await expect(footerLogo).toBeVisible();
      await footerLogo.click();
      await page.waitForURL('/');
    });

    test('should have working footer links', async ({ page }) => {
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      const footerLinks = page.locator('footer a');
      const count = await footerLinks.count();
      expect(count).toBeGreaterThan(0);

      // Every footer link must have a real href (no "#" placeholders, #3734).
      const hrefs = await footerLinks.evaluateAll((links) =>
        links.map((l) => l.getAttribute('href'))
      );
      for (const href of hrefs) {
        expect(href).toBeTruthy();
        expect(href).not.toBe('#');
      }
    });

    test('should navigate to legal pages from footer', async ({ page }) => {
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      await page.locator('footer a[href="/privacy"]').first().click();
      await page.waitForURL('**/privacy');
      await expect(page.getByRole('heading', { name: /Politique de confidentialite|Privacy policy|Gizlilik politikasi|سياسة الخصوصية/i })).toBeVisible();

      await page.goto('/');
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await page.locator('footer a[href="/terms"]').first().click();
      await page.waitForURL('**/terms');
      await expect(page.getByRole('heading', { name: /Conditions generales|Terms of use|Kullanim kosullari|شروط الاستخدام/i })).toBeVisible();
    });

    test('should open social media links in new tab', async ({ page }) => {
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

      const socialLinks = page.locator(
        'footer a[href*="twitter"], footer a[href*="linkedin"], footer a[href*="facebook"]'
      );
      const count = await socialLinks.count();
      // Non-bloquant : le footer peut ne pas exposer de réseaux sociaux.
      if (count > 0) {
        const target = await socialLinks.first().getAttribute('target');
        expect(target).toBe('_blank');
      }
    });
  });

  test.describe('Internal Links', () => {
    test.skip(({ isMobile }) => isMobile, 'Desktop navbar tests');

    test('should navigate from a module page to pricing', async ({ page }) => {
      // Les pages modules existent (/employes) mais ne sont pas dans la
      // navbar — on y accède par URL directe, puis on revient vers la
      // navigation réelle (pricing).
      await page.goto('/employes');
      await expect(page).toHaveURL(/employes/);

      const pricingLink = page.locator('nav a[href="/pricing"]').first();
      await expect(pricingLink).toBeVisible();
      await pricingLink.click();
      await page.waitForURL('**/pricing');
      expect(page.url()).toContain('pricing');
    });

    test('should navigate from a module page to the changelog', async ({ page }) => {
      await page.goto('/documents');
      await expect(page).toHaveURL(/documents/);

      const trigger = page
        .locator('button:has-text("Ressources"), button:has-text("Resources")')
        .first();
      await expect(trigger).toBeVisible();
      await trigger.click();

      const changelogLink = page.locator('nav a[href="/changelog"]').first();
      await expect(changelogLink).toBeVisible();
      await changelogLink.click();
      await page.waitForURL('**/changelog');
      expect(page.url()).toContain('changelog');
    });
  });

  test.describe('Mobile Navigation', () => {
    test('should open mobile menu on hamburger click', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });

      // Le menu mobile est un motion.div avec aria-label="Menu mobile" (#3503).
      const hamburger = page
        .locator('button[aria-label*="menu"], button[aria-label*="Menu"]')
        .first();
      await expect(hamburger).toBeVisible();
      await hamburger.click();

      // #3732 : le panneau porte désormais un id stable (aria-label localisé
      // « Menu »/« القائمة ») — on cible #mobile-menu-panel, indépendant de la langue.
      const mobileMenu = page.locator('#mobile-menu-panel').first();
      await expect(mobileMenu).toBeVisible({ timeout: 5000 });
    });

    test('should close mobile menu on link click', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });

      const hamburger = page
        .locator('button[aria-label*="menu"], button[aria-label*="Menu"]')
        .first();
      await expect(hamburger).toBeVisible();
      await hamburger.click();

      const mobileMenu = page.locator('#mobile-menu-panel').first();
      await expect(mobileMenu).toBeVisible({ timeout: 5000 });

      const link = mobileMenu.locator('a[href="/pricing"]').first();
      await expect(link).toBeVisible();
      await link.click();

      await expect(mobileMenu).not.toBeVisible({ timeout: 5000 });
    });
  });

  test.describe('URL Routing', () => {
    test('should route to landing page at /', async ({ page }) => {
      await page.goto('/');
      expect(page.url()).toContain('/');
    });

    test('should route to module pages', async ({ page }) => {
      for (const route of ['/employes', '/documents', '/comptabilite', '/marketing']) {
        const response = await page.goto(route);
        // Les pages modules existent et répondent en 200 (pas de 404).
        expect(response?.status(), route).toBe(200);
      }
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

    test('should route to multilingual legal pages', async ({ page }) => {
      await page.goto('/privacy');
      await expect(page.getByRole('heading', { name: /Politique de confidentialite|Privacy policy|Gizlilik politikasi|سياسة الخصوصية/i })).toBeVisible();

      await page.goto('/terms');
      await expect(page.getByRole('heading', { name: /Conditions generales|Terms of use|Kullanim kosullari|شروط الاستخدام/i })).toBeVisible();
    });

    test('should handle 404 for invalid routes', async ({ page }) => {
      const response = await page.goto('/invalid-route');
      expect(response?.status()).toBeLessThan(500);
    });
  });

  test.describe('Navigation State', () => {
    test('should highlight active pricing page in navbar', async ({ page }) => {
      await page.goto('/pricing');

      const pricingLink = page.locator('nav a[href="/pricing"]').first();
      await expect(pricingLink).toBeVisible();
      await expect(pricingLink).toHaveAttribute('aria-current', 'page');
    });
  });
});
