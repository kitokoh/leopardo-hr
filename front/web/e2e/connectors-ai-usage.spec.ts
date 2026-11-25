import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';
import type { Page } from '@playwright/test';

/**
 * #9925 (BC-14 INTEGRATION) — panneau Connecteurs côté tenant : un modèle d'IA
 * activé affiche sa consommation de tokens et son budget mensuel.
 *
 * Le panneau vit sur /settings/developer. Les appels API sont mockés (aucun
 * backend requis), sur le pattern des specs existantes (session par
 * sessionStorage.admin_token).
 */

const baseUser: AuthenticatedUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
};

async function seedSession(page: Page) {
  await installAuthenticatedSession(page, {
    user: {
      ...baseUser,
      company: {
        id: 'company-1',
        name: 'TechCorp Algerie SARL',
        metadata: { onboarding_completed: true },
      },
    },
  });
}

test.describe('Panneau Connecteurs tenant — modèles d\'IA', () => {
  test('une connexion IA affiche sa consommation de tokens et son budget', async ({ authenticatedPage: page }) => {
    // Catalogue : le modèle OpenAI est proposé au tenant.
    await page.route('**/api/v1/connectors/catalog', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            items: [
              {
                key: 'ai_openai',
                category: 'ai',
                audience: 'both',
                label: 'Modèle OpenAI',
                provider: 'OpenAI',
                fields: [
                  { key: 'model', label: 'Modèle', type: 'text', required: true },
                  { key: 'api_key', label: 'Clé API', type: 'password', secret: true, required: true },
                  { key: 'monthly_token_budget', label: 'Budget mensuel de tokens', type: 'number', required: false },
                ],
              },
            ],
          },
        }),
      });
    });

    // Connexions : une connexion IA active.
    await page.route('**/api/v1/connectors/connections', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            items: [
              {
                id: 42,
                catalog_key: 'ai_openai',
                label: 'Assistant IA',
                status: 'active',
                config: { model: 'gpt-4o', monthly_token_budget: 100000 },
              },
            ],
          },
        }),
      });
    });

    // Overview : aucune intégration extérieure.
    await page.route('**/api/v1/connectors/overview', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { integrations: [] } }),
      });
    });

    // Usage : 12 500 tokens consommés sur 100 000.
    await page.route('**/api/v1/connectors/connections/42/usage', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            connection_id: 42,
            period: '2026-11',
            used_tokens: 12500,
            budget_tokens: 100000,
            remaining_tokens: 87500,
          },
        }),
      });
    });

    await seedSession(page);
    await page.goto('/settings/developer', { waitUntil: 'domcontentloaded' });

    // Le panneau liste la connexion IA.
    await expect(page.locator('body')).toContainText('Assistant IA');
    await expect(page.locator('body')).toContainText('Modèle OpenAI');

    // La consommation de tokens est affichée, avec le budget.
    await expect(page.locator('body')).toContainText('Consommation de tokens');
    await expect(page.locator('body')).toContainText('12 500 tokens consommés');
    await expect(page.locator('body')).toContainText('sur 100 000');
  });

  test('une connexion IA sans budget affiche « budget illimité »', async ({ authenticatedPage: page }) => {
    await page.route('**/api/v1/connectors/catalog', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            items: [
              {
                key: 'ai_anthropic',
                category: 'ai',
                audience: 'both',
                label: 'Modèle Anthropic',
                provider: 'Anthropic',
                fields: [
                  { key: 'model', label: 'Modèle', type: 'text', required: true },
                  { key: 'api_key', label: 'Clé API', type: 'password', secret: true, required: true },
                ],
              },
            ],
          },
        }),
      });
    });

    await page.route('**/api/v1/connectors/connections', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            items: [
              {
                id: 43,
                catalog_key: 'ai_anthropic',
                label: 'Claude',
                status: 'active',
                config: { model: 'claude-sonnet-4-20250514' },
              },
            ],
          },
        }),
      });
    });

    await page.route('**/api/v1/connectors/overview', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { integrations: [] } }),
      });
    });

    await page.route('**/api/v1/connectors/connections/43/usage', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            connection_id: 43,
            period: '2026-11',
            used_tokens: 500,
            budget_tokens: null,
            remaining_tokens: null,
          },
        }),
      });
    });

    await seedSession(page);
    await page.goto('/settings/developer', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('body')).toContainText('Claude');
    await expect(page.locator('body')).toContainText('Consommation de tokens');
    await expect(page.locator('body')).toContainText('500 tokens consommés');
    await expect(page.locator('body')).toContainText('budget illimité');
  });
});