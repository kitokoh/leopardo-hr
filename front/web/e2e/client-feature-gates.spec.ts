import { expect, test, type Page } from '@playwright/test';

type AnalyticsWindow = Window & {
  __LEOPARDO_ANALYTICS_EVENTS__?: Array<{
    name: string;
    timestamp: string;
    properties: Record<string, string | number | boolean | null>;
  }>;
};

const baseUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
};

async function seedSession(page: Page, overrides: Record<string, unknown> = {}) {
  await page.addInitScript(() => {
    (window as AnalyticsWindow).__LEOPARDO_ANALYTICS_EVENTS__ = [];
  });

  await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
  const companyMetadata = { onboarding_completed: true };
  const userToStore = {
    ...baseUser,
    ...overrides,
    company: overrides.company
      ? {
          ...(overrides.company as object),
          metadata: {
            ...((overrides.company as any)?.metadata ?? {}),
            ...companyMetadata,
          },
        }
      : {
          metadata: companyMetadata,
        },
  };

  await page.evaluate((user) => {
    window.localStorage.setItem('auth_token', 'feature-gate-token');
    window.localStorage.setItem('auth_user', JSON.stringify(user));
  }, userToStore);
}

async function analyticsEvents(page: Page) {
  return page.evaluate(() => (window as AnalyticsWindow).__LEOPARDO_ANALYTICS_EVENTS__ ?? []);
}

test.describe('Client web feature gates', () => {
  test('included module stays accessible from the client workspace', async ({ page }) => {
    await page.route('**/api/v1/employees?per_page=12', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 501,
              first_name: 'Nadia',
              last_name: 'Kaci',
              email: 'nadia.kaci@techcorp-algerie.dz',
              role: 'employee',
              status: 'active',
              matricule: 'EMP-501',
            },
          ],
          meta: { total: 1 },
        }),
      });
    });

    await seedSession(page, {
      capabilities: {
        employees: true,
        payroll: false,
      },
      company: {
        id: 'company-1',
        name: 'TechCorp Algerie SARL',
        features: {
          employees: true,
          payroll: false,
        },
      },
    });

    await page.goto('/employees', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveURL(/\/employees$/);
    await expect(page.locator('body')).toContainText('Total equipe');
    await expect(page.locator('body')).toContainText('Nadia Kaci');
  });

  test('locked module shows an upgrade message instead of rendering a broken page', async ({ page }) => {
    await seedSession(page, {
      capabilities: {
        employees: true,
        payroll: false,
      },
      company: {
        id: 'company-1',
        name: 'TechCorp Algerie SARL',
        features: {
          employees: true,
          payroll: false,
        },
      },
    });

    await page.goto('/payroll', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveURL(/\/payroll$/);
    await expect(page.getByText('Module non inclus').first()).toBeVisible();
    await expect(page.locator('body')).toContainText('Ce module n est pas inclus dans votre plan actuel.');
    await expect(page.locator('body')).toContainText('Demander l activation');
    await expect(page.locator('body')).not.toContainText('Gestion des bulletins de paie et cycles de paie');

    const events = await analyticsEvents(page);
    const blockedEvent = events.find((event) => event.name === 'feature_blocked');
    expect(blockedEvent?.properties.module).toBe('payroll');
    expect(blockedEvent?.properties.reason).toBe('feature_locked');
  });

  test('trial module is visible as trial and remains usable', async ({ page }) => {
    await seedSession(page, {
      capabilities: {
        reports: 'trial',
      },
      company: {
        id: 'company-1',
        name: 'TechCorp Algerie SARL',
        features: {
          reports: 'trial',
        },
      },
    });

    await page.goto('/reports', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveURL(/\/reports$/);
    await expect(page.getByText('Trial').first()).toBeVisible();
    await expect(page.locator('body')).toContainText('Generez et telechargez vos rapports RH');
  });

  test('employee role cannot open manager payroll even if the feature exists', async ({ page }) => {
    await seedSession(page, {
      role: 'employee',
      manager_role: null,
      capabilities: {
        payroll: true,
      },
      company: {
        id: 'company-1',
        name: 'TechCorp Algerie SARL',
        features: {
          payroll: true,
        },
      },
    });

    await page.goto('/payroll', { waitUntil: 'domcontentloaded' });

    await expect(page.getByText('Module non inclus').first()).toBeVisible();
    await expect(page.locator('body')).toContainText('Votre role actuel ne permet pas d acceder a ce module.');
    const events = await analyticsEvents(page);
    expect(events.find((event) => event.name === 'feature_blocked')?.properties.reason).toBe('role_locked');
  });
});
