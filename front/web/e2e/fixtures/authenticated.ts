import { test as base, expect, type Page, type Route } from '@playwright/test';

export const SESSION_COOKIE_NAME = 'leopardo_token';
export const E2E_SESSION_TOKEN = 'e2e-mocked-session-token';

export type JsonObject = Record<string, unknown>;

export type AuthenticatedUser = {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  role: 'employee' | 'manager' | 'admin' | 'super_admin';
  manager_role?: string | null;
  language: 'fr' | 'ar' | 'tr' | 'en';
  is_rtl: boolean;
  capabilities?: JsonObject;
  features?: JsonObject;
  company?: JsonObject & {
    id?: string;
    name?: string;
    features?: JsonObject;
    metadata?: JsonObject;
  };
  plan?: JsonObject & { features?: JsonObject };
};

export type AuthenticatedSessionOptions = {
  user?: Partial<AuthenticatedUser> & {
    company?: AuthenticatedUser['company'];
    capabilities?: JsonObject;
    features?: JsonObject;
    plan?: AuthenticatedUser['plan'];
  };
  token?: string;
  baseURL?: string;
  mockAuthMe?: boolean;
  mockLogout?: boolean;
  mockAnnouncements?: boolean;
  mockNotifications?: boolean;
};

export type AuthenticatedPage = Page & {
  authenticatedUser: AuthenticatedUser;
};

export const managerUser: AuthenticatedUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'rh',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    can_create_employees: true,
    employees: true,
    attendance: true,
    absences: true,
  },
  company: {
    id: 'company-1',
    name: 'TechCorp Algerie SARL',
    language: 'fr',
    timezone: 'Africa/Algiers',
    currency: 'DZD',
    metadata: { onboarding_completed: true },
  },
};

function mergeUser(options: AuthenticatedSessionOptions = {}): AuthenticatedUser {
  const override = options.user ?? {};

  return {
    ...managerUser,
    ...override,
    capabilities: {
      ...managerUser.capabilities,
      ...(override.capabilities ?? {}),
    },
    features: {
      ...(managerUser.features ?? {}),
      ...(override.features ?? {}),
    },
    company: {
      ...managerUser.company,
      ...(override.company ?? {}),
      metadata: {
        ...(managerUser.company?.metadata ?? {}),
        ...(override.company?.metadata ?? {}),
      },
      features: {
        ...(managerUser.company?.features ?? {}),
        ...(override.company?.features ?? {}),
      },
    },
    plan: override.plan
      ? {
          ...(managerUser.plan ?? {}),
          ...override.plan,
          features: {
            ...(managerUser.plan?.features ?? {}),
            ...(override.plan.features ?? {}),
          },
        }
      : managerUser.plan,
  };
}

function resolveBaseURL(explicitBaseURL?: string): string {
  const value = explicitBaseURL ?? process.env.BASE_URL ?? 'http://localhost:3000';
  return new URL(value).origin;
}

async function fulfillJson(route: Route, body: JsonObject, status = 200): Promise<void> {
  await route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

/**
 * Installs the complete mocked session used by protected client-web E2E tests.
 *
 * The helper intentionally mocks every cross-cutting request made by the
 * dashboard shell. A page test may add feature-specific routes afterwards.
 * Playwright evaluates the most recently registered matching route first.
 */
export async function installAuthenticatedSession(
  page: Page,
  options: AuthenticatedSessionOptions = {},
): Promise<AuthenticatedUser> {
  const user = mergeUser(options);
  const token = options.token ?? E2E_SESSION_TOKEN;
  const baseURL = resolveBaseURL(options.baseURL);

  await page.route('**/api/v1/auth/me', async (route) => {
    if (options.mockAuthMe === false) {
      await route.fallback();
      return;
    }

    await fulfillJson(route, { data: user });
  });

  await page.route('**/api/v1/auth/login', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      headers: {
        'Set-Cookie': `${SESSION_COOKIE_NAME}=${token}; Path=/; HttpOnly; SameSite=Lax`,
      },
      body: JSON.stringify({
        data: user,
        token: 'client-web-token',
        token_type: 'Bearer',
      }),
    });
  });

  await page.route('**/api/v1/auth/logout', async (route) => {
    if (options.mockLogout === false) {
      await route.fallback();
      return;
    }

    await fulfillJson(route, { success: true });
  });

  await page.route('**/api/v1/notifications**', async (route) => {
    if (options.mockNotifications === false) {
      await route.fallback();
      return;
    }

    await fulfillJson(route, { data: [], meta: { total: 0, unread_count: 0 } });
  });

  await page.route('**/api/v1/announcements**', async (route) => {
    if (options.mockAnnouncements === false) {
      await route.fallback();
      return;
    }

    await fulfillJson(route, { data: [], meta: { total: 0 } });
  });

  await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });

  await page.context().addCookies([
    {
      name: SESSION_COOKIE_NAME,
      value: token,
      url: baseURL,
      httpOnly: true,
      sameSite: 'Lax',
    },
  ]);

  await page.evaluate((storedUser) => {
    window.localStorage.removeItem('auth_token');
    window.localStorage.setItem('auth_user', JSON.stringify(storedUser));
  }, user);

  return user;
}

/**
 * Installs request-level diagnostics without exposing the session token.
 * Failures are reported only as method, URL and status.
 */
export async function installAuthDiagnostics(page: Page): Promise<void> {
  await page.on('response', (response) => {
    const url = response.url();
    const isApiResponse = /\/api\/v1\//.test(url);

    if (isApiResponse && response.status() === 401) {
      console.warn(`[e2e-auth] 401 ${response.request().method()} ${new URL(url).pathname}`);
    }
  });

  await page.on('framenavigated', (frame) => {
    if (frame === page.mainFrame() && frame.url().includes('/auth/login')) {
      console.warn(`[e2e-auth] redirected to ${new URL(frame.url()).pathname}`);
    }
  });
}

export const test = base.extend<{ authenticatedPage: AuthenticatedPage }>({
  authenticatedPage: async ({ page }, fixture) => {
    await installAuthDiagnostics(page);
    const user = await installAuthenticatedSession(page);
    const authenticatedPage = page as AuthenticatedPage;
    authenticatedPage.authenticatedUser = user;
    await fixture(authenticatedPage);
  },
});

export { expect };
