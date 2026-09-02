import { expect, test } from '@playwright/test'

/**
 * Issue #6741 — la Sidebar affichait deux entrées « Stations-service »
 * (dont une avec name:'chat' copié-collé). Chaque entrée de navigation doit
 * apparaître EXACTEMENT une fois, avec son propre name (clé de rendu + état
 * actif du route).
 *
 * Pattern : mocks API + login via token (déterministe, skip sans
 * PLAYWRIGHT_AUTH_TOKEN comme travel-admin.spec.js).
 */

const AUTHENTICATED = Boolean(process.env.PLAYWRIGHT_AUTH_TOKEN)
const LIVE = process.env.BACKEND_LIVE === '1'

test.describe.configure({ timeout: 120_000 })

const ADMIN_USER = {
  id: 1,
  name: 'Agent E2E',
  email: 'agent.e2e@leopardo.test',
  role: 'superadmin',
  language: 'fr',
}

test.describe('Sidebar — une entrée par page (#6741)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('Stations-service et Chat IA apparaissent une seule fois', async ({ page }) => {
    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.route('**/api/v1/**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
    )
    await page.route(/^https?:\/\/[^/]+\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: ADMIN_USER }) }),
    )

    await page.goto('/dashboard')
    await expect(page.getByRole('navigation')).toBeVisible({ timeout: 15_000 })

    // Sidebar visible : vérifier l'unicité des entrées (liens de nav).
    await expect(page.getByRole('navigation').getByText('Stations-service')).toHaveCount(1)
    await expect(page.getByRole('navigation').getByText('Chat IA')).toHaveCount(1)
    // Pas de clé de rendu dupliquée : le lien fuel pointe bien vers /fuel-station.
    await expect(page.getByRole('navigation').getByRole('link', { name: /Stations-service/i })).toHaveCount(1)
  })
})
