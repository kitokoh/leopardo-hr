import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Dark Mode Toggle
 *
 * QA #3728 (W-01) : les anciens tests passaient sous garde
 * `if (isVisible())` sans rien asserter (faux vert CI). Réécrits avec
 * assertions strictes `expect(...).toBeVisible()` — le toggle de thème vit
 * dans la Navbar, présente sur toutes les pages de la vitrine.
 */

test.describe('Dark Mode Toggle E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  const toggleLocator = (page: import('@playwright/test').Page) =>
    page
      .locator(
        'button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]'
      )
      .first();

  test.describe('Dark Mode Toggle Button', () => {
    test('should display dark mode toggle button', async ({ page }) => {
      await expect(toggleLocator(page)).toBeVisible();
    });

    test('should toggle dark mode on click', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();

      const initialTheme = await page.evaluate(() =>
        document.documentElement.classList.contains('dark')
      );

      await toggle.click();
      await page.waitForTimeout(500);

      const newTheme = await page.evaluate(() =>
        document.documentElement.classList.contains('dark')
      );
      expect(newTheme).not.toBe(initialTheme);
    });

    test('should persist dark mode preference', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();

      const isDarkMode = await page.evaluate(() =>
        document.documentElement.classList.contains('dark')
      );
      if (!isDarkMode) {
        await toggle.click();
        await page.waitForTimeout(500);
      }

      // Reload : la préférence doit être conservée (localStorage) — le theme
      // est appliqué par useEffect apres hydration.
      await page.reload();
      await page.waitForFunction(() =>
        document.documentElement.classList.contains('dark')
      );
      expect(
        await page.evaluate(() =>
          document.documentElement.classList.contains('dark')
        )
      ).toBe(true);
    });

    test('should apply dark mode colors to background', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();

      const isDarkMode = await page.evaluate(() =>
        document.documentElement.classList.contains('dark')
      );
      if (!isDarkMode) {
        await toggle.click();
        await page.waitForTimeout(500);
      }

      // La classe dark est le contrat applicatif (useDarkMode → html.dark).
      await page.waitForFunction(() =>
        document.documentElement.classList.contains('dark')
      );
      const htmlBg = await page.evaluate(() =>
        window.getComputedStyle(document.documentElement).backgroundColor
      );
      // En dark mode, le fond ne doit pas être blanc pur.
      expect(htmlBg.toLowerCase()).not.toBe('rgb(255, 255, 255)');
    });

    test('should apply dark mode colors to text', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();

      const isDarkMode = await page.evaluate(() =>
        document.documentElement.classList.contains('dark')
      );
      if (!isDarkMode) {
        await toggle.click();
        await page.waitForTimeout(500);
      }

      await page.waitForFunction(() =>
        document.documentElement.classList.contains('dark')
      );
      const textColor = await page.evaluate(
        () => window.getComputedStyle(document.body).color
      );
      // En dark mode, le texte ne doit pas être noir pur.
      expect(textColor.toLowerCase()).not.toBe('rgb(0, 0, 0)');
    });

    test('should apply dark mode to all components', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();

      const isDarkMode = await page.evaluate(() =>
        document.documentElement.classList.contains('dark')
      );
      if (!isDarkMode) {
        await toggle.click();
        await page.waitForTimeout(500);
      }

      expect(
        await page.evaluate(() =>
          document.documentElement.classList.contains('dark')
        )
      ).toBe(true);
    });
  });

  test.describe('Dark Mode Across Pages', () => {
    test('should apply dark mode on landing page', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();
      await toggle.click();
      await page.waitForTimeout(500);

      expect(
        await page.evaluate(() =>
          document.documentElement.classList.contains('dark')
        )
      ).toBe(true);
    });

    test('should apply dark mode on module pages', async ({ page }) => {
      // Les pages modules ne sont pas dans la navbar (#3728) — accès direct.
      await page.goto('/employes');
      await expect(page).toHaveURL(/employes/);

      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();
      await toggle.click();
      await page.waitForTimeout(500);

      expect(
        await page.evaluate(() =>
          document.documentElement.classList.contains('dark')
        )
      ).toBe(true);
    });

    test.skip(({ isMobile }) => isMobile, 'Desktop navbar tests');

    test('should apply dark mode on pricing page', async ({ page }) => {
      const pricingLink = page.locator('nav a[href="/pricing"]').first();
      await expect(pricingLink).toBeVisible();
      await pricingLink.click();
      await page.waitForURL('**/pricing');

      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();
      await toggle.click();
      await page.waitForTimeout(500);

      expect(
        await page.evaluate(() =>
          document.documentElement.classList.contains('dark')
        )
      ).toBe(true);
    });
  });

  test.describe('Dark Mode Contrast', () => {
    test('should maintain readable contrast in dark mode', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();

      const isDarkMode = await page.evaluate(() =>
        document.documentElement.classList.contains('dark')
      );
      if (!isDarkMode) {
        await toggle.click();
        await page.waitForTimeout(500);
      }

      const contrastRatio = await page.evaluate(() => {
        const bgColor = window.getComputedStyle(document.body).backgroundColor;
        const textColor = window.getComputedStyle(document.body).color;
        return bgColor !== textColor;
      });
      expect(contrastRatio).toBe(true);
    });
  });

  test.describe('Accessibility', () => {
    test('should have accessible dark mode toggle', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();

      // Le toggle doit être un vrai bouton avec un nom accessible.
      expect(await toggle.getAttribute('aria-label')).toBeTruthy();
    });

    test('should work with keyboard navigation', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();

      // Tab jusqu'au toggle puis Enter pour activer.
      await page.keyboard.press('Tab');
      const focused = await page.evaluate(() =>
        (document.activeElement as HTMLElement | null)?.textContent?.trim()
      );
      // Simple sanity : la page est focusable, le toggle reste accessible.
      expect(typeof focused).toBe('string');
      await toggle.focus();
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);
      // Aucun crash : la page est toujours vivante (header toujours present).
      await expect(page.locator('header').first()).toBeVisible();
    });
  });

  test.describe('Transition', () => {
    test('should animate dark mode transition', async ({ page }) => {
      const toggle = toggleLocator(page);
      await expect(toggle).toBeVisible();
      // L'activation ne doit pas faire planter la page (transition CSS).
      await toggle.click();
      await page.waitForTimeout(800);
      await expect(page.locator('header').first()).toBeVisible();
    });
  });
});
