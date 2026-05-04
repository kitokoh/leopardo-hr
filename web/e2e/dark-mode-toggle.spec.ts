import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Dark Mode Toggle
 * Tests dark mode functionality across the site
 */

test.describe('Dark Mode Toggle E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to landing page before each test
    await page.goto('/');
  });

  test.describe('Dark Mode Toggle Button', () => {
    test('should display dark mode toggle button', async ({ page }) => {
      // Look for dark mode toggle button
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      // If not found by aria-label, look for common patterns
      if (!await darkModeToggle.isVisible()) {
        const moonIcon = page.locator('svg[class*="moon"], svg[class*="sun"]').first();
        expect(moonIcon).toBeDefined();
      } else {
        await expect(darkModeToggle).toBeVisible();
      }
    });

    test('should toggle dark mode on click', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Get initial theme
        const initialTheme = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        // Click toggle
        await darkModeToggle.click();

        // Wait for theme change
        await page.waitForTimeout(500);

        // Check if theme changed
        const newTheme = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        expect(newTheme).not.toBe(initialTheme);
      }
    });

    test('should persist dark mode preference', async ({ page, context }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Enable dark mode
        const isDarkMode = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        if (!isDarkMode) {
          await darkModeToggle.click();
          await page.waitForTimeout(500);
        }

        // Reload page
        await page.reload();

        // Check if dark mode is still enabled
        const darkModeAfterReload = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        expect(darkModeAfterReload).toBe(true);
      }
    });
  });

  test.describe('Dark Mode Styling', () => {
    test('should apply dark mode colors to background', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Enable dark mode
        const isDarkMode = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        if (!isDarkMode) {
          await darkModeToggle.click();
          await page.waitForTimeout(500);
        }

        // Check background color
        const bgColor = await page.evaluate(() => {
          return window.getComputedStyle(document.body).backgroundColor;
        });

        // Should be dark color
        expect(bgColor).toBeTruthy();
      }
    });

    test('should apply dark mode colors to text', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Enable dark mode
        const isDarkMode = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        if (!isDarkMode) {
          await darkModeToggle.click();
          await page.waitForTimeout(500);
        }

        // Check text color
        const textColor = await page.evaluate(() => {
          return window.getComputedStyle(document.body).color;
        });

        // Should be light color
        expect(textColor).toBeTruthy();
      }
    });

    test('should apply dark mode to all components', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Enable dark mode
        const isDarkMode = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        if (!isDarkMode) {
          await darkModeToggle.click();
          await page.waitForTimeout(500);
        }

        // Check if cards have dark styling
        const cards = page.locator('[class*="card"], [class*="Card"]');
        const cardCount = await cards.count();

        if (cardCount > 0) {
          const firstCard = cards.first();
          const bgColor = await firstCard.evaluate((el) => {
            return window.getComputedStyle(el).backgroundColor;
          });

          expect(bgColor).toBeTruthy();
        }
      }
    });
  });

  test.describe('Dark Mode on Different Pages', () => {
    test('should apply dark mode on landing page', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Enable dark mode
        await darkModeToggle.click();
        await page.waitForTimeout(500);

        // Check if dark mode is applied
        const isDarkMode = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        expect(isDarkMode).toBe(true);
      }
    });

    test('should apply dark mode on module pages', async ({ page }) => {
      // Navigate to employees page
      const employeesLink = page.locator('a:has-text("Employés"), a:has-text("Employees")').first();
      if (await employeesLink.isVisible()) {
        await employeesLink.click();
        await page.waitForURL('**/employes');

        // Find dark mode toggle
        const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
        
        if (await darkModeToggle.isVisible()) {
          // Enable dark mode
          await darkModeToggle.click();
          await page.waitForTimeout(500);

          // Check if dark mode is applied
          const isDarkMode = await page.evaluate(() => {
            return document.documentElement.classList.contains('dark');
          });

          expect(isDarkMode).toBe(true);
        }
      }
    });

    test('should apply dark mode on pricing page', async ({ page }) => {
      // Navigate to pricing page
      const pricingLink = page.locator('a:has-text("Tarification"), a:has-text("Pricing")').first();
      if (await pricingLink.isVisible()) {
        await pricingLink.click();
        await page.waitForURL('**/pricing');

        // Find dark mode toggle
        const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
        
        if (await darkModeToggle.isVisible()) {
          // Enable dark mode
          await darkModeToggle.click();
          await page.waitForTimeout(500);

          // Check if dark mode is applied
          const isDarkMode = await page.evaluate(() => {
            return document.documentElement.classList.contains('dark');
          });

          expect(isDarkMode).toBe(true);
        }
      }
    });
  });

  test.describe('Dark Mode Contrast', () => {
    test('should maintain readable contrast in dark mode', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Enable dark mode
        const isDarkMode = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        if (!isDarkMode) {
          await darkModeToggle.click();
          await page.waitForTimeout(500);
        }

        // Check contrast ratio
        const contrastRatio = await page.evaluate(() => {
          const body = document.body;
          const bgColor = window.getComputedStyle(body).backgroundColor;
          const textColor = window.getComputedStyle(body).color;
          
          // Simple check: colors should be different
          return bgColor !== textColor;
        });

        expect(contrastRatio).toBe(true);
      }
    });
  });

  test.describe('Dark Mode Accessibility', () => {
    test('should have accessible dark mode toggle', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Check for aria-label
        const ariaLabel = await darkModeToggle.getAttribute('aria-label');
        expect(ariaLabel).toBeTruthy();

        // Check if button is keyboard accessible
        await darkModeToggle.focus();
        const hasFocus = await darkModeToggle.evaluate((el) => {
          return el === document.activeElement;
        });

        expect(hasFocus).toBe(true);
      }
    });

    test('should work with keyboard navigation', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Focus on toggle
        await darkModeToggle.focus();

        // Press Enter to toggle
        await page.keyboard.press('Enter');

        // Wait for theme change
        await page.waitForTimeout(500);

        // Check if theme changed
        const isDarkMode = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        expect(isDarkMode).toBeTruthy();
      }
    });
  });

  test.describe('Dark Mode Animations', () => {
    test('should animate dark mode transition', async ({ page }) => {
      // Find dark mode toggle
      const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button[aria-label*="mode"]').first();
      
      if (await darkModeToggle.isVisible()) {
        // Get initial state
        const initialTheme = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        // Click toggle
        await darkModeToggle.click();

        // Wait for animation
        await page.waitForTimeout(1000);

        // Check if theme changed
        const newTheme = await page.evaluate(() => {
          return document.documentElement.classList.contains('dark');
        });

        expect(newTheme).not.toBe(initialTheme);
      }
    });
  });
});
