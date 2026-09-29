import { expect, installAuthenticatedSession, test } from './fixtures/authenticated';

/**
 * BOS-035 (#8224) — panneau Assistant Leo IA du portail tenant (`/assistant`).
 *
 * Les 3 parcours des critères d'acceptation :
 *  1. question → réponse avec tool read → `tools_used` affiché ;
 *  2. write proposé → carte de confirmation → confirm → exécution → résultat
 *     affiché, ET parcours reject → état rejeté ;
 *  3. utilisateur employé : l'API ne retourne aucune pending_confirmation ni
 *     write tool → aucune carte ni action interdite visible.
 *
 * L'API est mockée (`page.route`) : le front n'a aucune logique de
 * permission, il affiche uniquement ce que l'API retourne. Le user mocké
 * porte le feature flag tenant `leo_ai` (features racines de /auth/me) :
 * sans lui, le shell rend le panneau « module non inclus » (garde
 * feature-gate du layout — c'est aussi le levier de rollback du module).
 */

type JsonObject = Record<string, unknown>;

async function fulfillJson(route: import('@playwright/test').Route, body: JsonObject, status = 200): Promise<void> {
  await route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

const historyPayload = {
  data: [
    {
      id: 8101,
      title: 'Pointages de la semaine',
      token_count: 1280,
      created_at: '2026-09-27T09:00:00Z',
      updated_at: '2026-09-27T09:30:00Z',
    },
  ],
  meta: { total: 1 },
};

const pendingAction = {
  status: 'confirmation_required',
  pending_action_id: 'e2e-action-1',
  tool: 'absence.approve',
  summary: 'Approuver la demande d’absence de Nadia Kaci (3 jours).',
  arguments: { absence_id: 9001 },
};

async function mockAssistantHistory(page: import('@playwright/test').Page): Promise<void> {
  await page.route('**/api/v1/ai/chat/history**', async (route) => {
    await fulfillJson(route, historyPayload);
  });
}

async function mockAssistantChat(
  page: import('@playwright/test').Page,
  reply: JsonObject,
  captured?: { bodies: JsonObject[] },
): Promise<void> {
  await page.route('**/api/v1/ai/chat', async (route) => {
    if (route.request().method() !== 'POST') {
      await route.fallback();
      return;
    }
    captured?.bodies.push(route.request().postDataJSON() as JsonObject);
    await fulfillJson(route, { data: reply });
  });
}

test.describe('Assistant Leo IA — portail tenant (BOS-035, #8224)', () => {
  test('question → réponse avec tool read → tools_used affiché et conversation reprise', async ({
    page,
  }) => {
    await installAuthenticatedSession(page, { user: { features: { leo_ai: true } } });
    const captured: { bodies: JsonObject[] } = { bodies: [] };

    await mockAssistantHistory(page);
    await mockAssistantChat(
      page,
      {
        conversation_id: 9001,
        response: 'Vous avez 39 employés actifs aujourd’hui.',
        tools_used: ['employees.list'],
        pending_confirmations: [],
        tokens: { input: 12, output: 34 },
      },
      captured,
    );

    await page.goto('/assistant');

    // Historique présent (titre + compteur de tokens).
    await expect(page.getByTestId('assistant-history')).toContainText('Pointages de la semaine');

    await page.getByTestId('assistant-input').fill('Combien d’employés actifs ?');
    await page.getByTestId('assistant-send').click();

    await expect(page.getByTestId('assistant-user-bubble')).toContainText('Combien d’employés actifs');
    await expect(page.getByTestId('assistant-assistant-bubble')).toContainText('39 employés actifs');
    await expect(page.getByTestId('assistant-tools-used')).toContainText('employees.list');

    // 1re question : nouvelle conversation (conversation_id null).
    expect(captured.bodies[0]).toMatchObject({ conversation_id: null });

    // 2e question : le conversation_id renvoyé par l'API est repris.
    await page.getByTestId('assistant-input').fill('Et les absences en attente ?');
    await page.getByTestId('assistant-send').click();
    await expect(page.getByTestId('assistant-user-bubble').nth(1)).toContainText('absences en attente');
    expect(captured.bodies[1]).toMatchObject({ conversation_id: 9001 });
  });

  test('write proposé → carte → Confirmer → exécution → résultat affiché', async ({
    page,
  }) => {
    await installAuthenticatedSession(page, { user: { features: { leo_ai: true } } });

    await mockAssistantHistory(page);
    await mockAssistantChat(page, {
      conversation_id: 9002,
      response: 'Je peux approuver cette absence.',
      tools_used: ['absence.approve'],
      pending_confirmations: [pendingAction],
      tokens: { input: 20, output: 40 },
    });
    await page.route('**/api/v1/ai/actions/*/confirm', async (route) => {
      await fulfillJson(route, {
        data: {
          status: 'executed',
          tool: 'absence.approve',
          result: { absence: { id: 9001, status: 'approved' } },
        },
      });
    });

    await page.goto('/assistant');
    await page.getByTestId('assistant-input').fill('Approuve l’absence de Nadia');
    await page.getByTestId('assistant-send').click();

    const card = page.getByTestId('assistant-confirmation-card');
    await expect(card).toBeVisible();
    await expect(card).toContainText('absence.approve');
    await expect(card).toContainText('Approuver la demande d’absence de Nadia Kaci');

    await card.getByRole('button', { name: 'Confirmer' }).click();

    await expect(page.getByTestId('assistant-card-executed')).toBeVisible();
    await expect(page.getByTestId('assistant-card-executed')).toContainText('approved');
    // One-shot : plus aucun bouton d'action sur la carte.
    await expect(card.getByRole('button', { name: 'Confirmer' })).toHaveCount(0);
    await expect(card.getByRole('button', { name: 'Rejeter' })).toHaveCount(0);
  });

  test('write proposé → carte → Rejeter → état rejeté', async ({ page }) => {
    await installAuthenticatedSession(page, { user: { features: { leo_ai: true } } });

    await mockAssistantHistory(page);
    await mockAssistantChat(page, {
      conversation_id: 9003,
      response: 'Je peux approuver cette absence.',
      tools_used: ['absence.approve'],
      pending_confirmations: [pendingAction],
      tokens: { input: 20, output: 40 },
    });
    await page.route('**/api/v1/ai/actions/*/reject', async (route) => {
      await fulfillJson(route, { data: { status: 'rejected', tool: 'absence.approve' } });
    });

    await page.goto('/assistant');
    await page.getByTestId('assistant-input').fill('Approuve l’absence de Nadia');
    await page.getByTestId('assistant-send').click();

    const card = page.getByTestId('assistant-confirmation-card');
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: 'Rejeter' }).click();

    await expect(page.getByTestId('assistant-card-rejected')).toBeVisible();
    await expect(card.getByRole('button', { name: 'Confirmer' })).toHaveCount(0);
  });

  test('utilisateur employé : aucune carte ni action interdite visible', async ({ page }) => {
    // Session employé (le mock /auth/me porte bien welcome_seen_at — sinon la
    // modale de bienvenue bloque les clics, cf. fixtures/authenticated.ts).
    await installAuthenticatedSession(page, { user: { role: 'employee', features: { leo_ai: true } } });

    await mockAssistantHistory(page);
    // L'API (tools filtrés par rôle côté serveur) ne retourne AUCUNE
    // pending_confirmation ni write tool pour cet employé.
    await mockAssistantChat(page, {
      conversation_id: 9004,
      response: 'Vous avez 12 jours de congés restants.',
      tools_used: ['leave.balance'],
      pending_confirmations: [],
      tokens: { input: 8, output: 16 },
    });

    await page.goto('/assistant');
    await page.getByTestId('assistant-input').fill('Solde de mes congés ?');
    await page.getByTestId('assistant-send').click();

    await expect(page.getByTestId('assistant-assistant-bubble')).toContainText('12 jours de congés restants');
    // Aucune carte de confirmation, aucune action d'écriture proposée.
    await expect(page.getByTestId('assistant-confirmation-card')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Confirmer' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Rejeter' })).toHaveCount(0);
  });
});
