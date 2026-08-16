import { expect, test, type Page } from '@playwright/test';
import { sessionCookieHeader, setSessionCookie } from './session-helpers';

/**
 * #2167 — Dashboard client : « Actions rapides », « Voir toute l'activité »
 * et carte Leo IA branchés sur de vraies actions.
 *
 * - Les actions rapides sont de vrais liens vers leurs pages.
 * - « Oui, envoyer » POSTe une annonce entreprise sur /api/v1/announcements.
 * - « Plus tard » masque la carte de facon persistante (localStorage).
 */
async function mockManagerSession(page: Page) {
  await page.route('**/api/v1/auth/login', async (route) => {
    await route.fulfill({
        headers: { 'Set-Cookie': sessionCookieHeader },
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 101,
          first_name: 'Fatima',
          last_name: 'Meziane',
          email: 'fatima.meziane@techcorp-algerie.dz',
          role: 'manager',
          manager_role: 'rh',
          language: 'fr',
          is_rtl: false,
        },
        token: 'client-web-token',
        token_type: 'Bearer',
      }),
    });
  });

  await page.route('**/api/v1/auth/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
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
            metadata: {
              onboarding_completed: true,
            },
          },
        },
      }),
    });
  });

  await page.route('**/api/v1/dashboard/summary', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          employees_total: 42,
          employees_active: 39,
          departments: 5,
          today_attendance: 31,
          pending_absences: 4,
        },
      }),
    });
  });

  await page.route('**/api/v1/dashboard/recent-activity**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 1,
            action: 'absence.requested',
            auditable_type: 'App\\Models\\Absence',
            created_at: '2026-05-21T10:00:00Z',
          },
        ],
      }),
    });
  });

  await page.route('**/api/v1/launch-readiness', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          score: 82,
          status: 'ready',
          blockers: [],
          next_actions: [],
        },
      }),
    });
  });

  await page.route('**/api/v1/client-events', async (route) => {
    await route.fulfill({
      status: 202,
      contentType: 'application/json',
      body: JSON.stringify({ accepted: true }),
    });
  });

  await page.route('**/api/v1/notifications**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { total: 0, unread_count: 0 } }),
    });
  });

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
        meta: { total: 42 },
      }),
    });
  });

  await page.route('**/api/v1/absences', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 9001,
            start_date: '2026-05-25',
            end_date: '2026-05-27',
            status: 'pending',
            reason: 'Conges familiaux',
            days_count: 3,
            absence_type: { name: 'Conges payes' },
          },
        ],
      }),
    });
  });
}

async function loginAsManager(page: Page) {
  await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
  // Issue #2746 — poser le cookie de session avant la soumission (middleware serveur).
  await setSessionCookie(page);
  await page.getByLabel(/adresse email|email address/i).fill('fatima.meziane@techcorp-algerie.dz');
  await page.getByLabel(/^mot de passe$|^password$/i).fill('password123');
  await page.getByRole('button', { name: /sign in|se connecter/i }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test.describe('Dashboard client — actions rapides et carte Leo IA', () => {
  test('chaque action rapide navigue vers la bonne page', async ({ page }) => {
    await mockManagerSession(page);
    await loginAsManager(page);

    await expect(page.locator('body')).toContainText('Actions rapides');

    // QA 2026-08-15 (#2655) : les routes réelles sont SANS préfixe
    // /dashboard/… (les anciennes URLs renvoyaient 404). On asserte en plus
    // le contenu de la page cible pour ne pas figer une URL morte.

    // Nouvel employe -> /employees
    // #4095 : 2 liens légitimes (sidebar + action rapide) — strict mode
    // exige un sélecteur unique ; .first() (même pattern que /reports).
    await page.locator('a[href="/employees"]').first().click();
    await expect(page).toHaveURL(/\/employees$/);
    await expect(page.locator('body')).toContainText('Collaborateurs recents');
    await page.goto('/dashboard');

    // Conges -> /absences
    await page.locator('a[href="/absences"]').click();
    await expect(page).toHaveURL(/\/absences$/);
    await expect(page.locator('body')).toContainText('Demandes visibles');
    await page.goto('/dashboard');

    // Rapports et Export -> /reports
    await page.locator('a[href="/reports"]').first().click();
    await expect(page).toHaveURL(/\/reports$/);
    await expect(page.locator('body')).toContainText('Rapports');
    await page.goto('/dashboard');

    await page.locator('a[href="/reports"]').last().click();
    await expect(page).toHaveURL(/\/reports$/);

    // « Voir toute l'activite » pointe aussi sur /reports
    await page.goto('/dashboard');
    await page.getByRole('link', { name: /voir toute l.activite/i }).click();
    await expect(page).toHaveURL(/\/reports$/);
  });

  test('« Oui, envoyer » cree une vraie annonce entreprise et affiche le succes', async ({ page }) => {
    // Wrapper objet : TS ne re-narrowit pas une propriete assignee dans une
    // closure (contrairement a un `let` initialise a null).
    const captured: { request: { method: string; body: Record<string, unknown> } | null } = { request: null };

    await page.route('**/api/v1/announcements', async (route) => {
      const method = route.request().method();
      if (method === 'POST') {
        captured.request = {
          method,
          body: route.request().postDataJSON() as Record<string, unknown>,
        };
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            data: {
              id: 7001,
              title: 'Felicitations equipe',
              body: 'Felicitations a toute l\'equipe pour votre engagement de cette semaine. Continuez sur cette dynamique !',
              priority: 'normal',
              audience_type: 'company',
              created_by: 101,
              status: 'published',
            },
          }),
        });
        return;
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [], meta: { total: 0 } }),
      });
    });

    await mockManagerSession(page);
    await loginAsManager(page);

    await page.getByRole('button', { name: 'Oui, envoyer' }).click();

    await expect(page.getByText(/message envoye a l.equipe/i)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Oui, envoyer' })).toHaveCount(0);

    expect(captured.request).not.toBeNull();
    expect(captured.request?.method).toBe('POST');
    expect(captured.request?.body.title).toBe('Felicitations equipe');
    expect(captured.request?.body.audience_type).toBe('company');
    expect(captured.request?.body.priority).toBe('normal');
    expect(String(captured.request?.body.body)).toContain('equipe');
  });

  test('« Oui, envoyer » affiche une erreur inline si l API echoue et conserve les boutons', async ({ page }) => {
    await page.route('**/api/v1/announcements', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 422,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Only principal/RH managers can broadcast to the whole company.',
            errors: { audience_type: ['Only principal/RH managers can broadcast to the whole company.'] },
          }),
        });
        return;
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [], meta: { total: 0 } }),
      });
    });

    await mockManagerSession(page);
    await loginAsManager(page);

    await page.getByRole('button', { name: 'Oui, envoyer' }).click();

    await expect(page.getByRole('button', { name: 'Oui, envoyer' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Plus tard' })).toBeVisible();
    await expect(page.locator('body')).toContainText('Only principal/RH managers can broadcast');
  });

  test('« Plus tard » masque la carte et elle reste masquee apres rechargement', async ({ page }) => {
    await mockManagerSession(page);
    await loginAsManager(page);

    await expect(page.getByRole('button', { name: 'Plus tard' })).toBeVisible();

    await page.getByRole('button', { name: 'Plus tard' }).click();
    await expect(page.getByRole('button', { name: 'Plus tard' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Oui, envoyer' })).toHaveCount(0);

    // localStorage persiste dans le meme contexte : rechargement -> carte toujours masquee.
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('button', { name: 'Plus tard' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Oui, envoyer' })).toHaveCount(0);
  });
});
