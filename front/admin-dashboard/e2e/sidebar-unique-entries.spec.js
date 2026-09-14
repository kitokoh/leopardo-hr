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

/**
 * #7329 — le guard du router (`authStore.checkAuth`) exige explicitement
 * `role === 'super_admin'` : une session sans ce rôle est détruite et l'admin
 * est renvoyé sur `/login`. Le bloc ci-dessous simule donc une vraie session
 * super-admin (l'ancien `ADMIN_USER` du bloc #6741 porte 'superadmin', valeur
 * qui ne satisfait pas la garde).
 */
const SUPER_ADMIN_USER = {
  id: 1,
  name: 'Agent E2E',
  email: 'agent.e2e@leopardo.test',
  role: 'super_admin',
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

/**
 * #7329 — « fuel station », « training », « vehicle » (flotte) et « travel »
 * sont des écrans du périmètre d'une entreprise CLIENTE : ce ne sont plus des
 * entrées de premier niveau du menu plateforme, mais une section rattachée à
 * « Entreprises ».
 *
 * Ce bloc est volontairement NON conditionné à PLAYWRIGHT_AUTH_TOKEN (le job
 * `web-ci.yml` ne le fournit pas) : la session est simulée comme dans
 * travel-navigation.spec.js, sinon la garde ne s'exécuterait jamais en CI.
 */
test.describe('Sidebar — modules d’entreprise cliente regroupés (#7329)', () => {
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('les 4 modules clients sont sous « Entreprises », section dépliée par défaut', async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-sidebar-token')
    })
    await page.route('**/api/v1/**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
    )
    await page.route(/^https?:\/\/[^/]+\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: SUPER_ADMIN_USER }) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )

    // La route « dashboard » de l'admin est servie sur `/` (cf.
    // `src/router/index.js`) : `/dashboard` renvoie une page 404.
    await page.goto('/')
    const nav = page.getByRole('navigation')
    await expect(nav).toBeVisible({ timeout: 15_000 })

    // Un titre de section annonce le regroupement, déplié par défaut.
    const header = nav.getByRole('button', { name: /Modules des entreprises clientes/i })
    await expect(header).toBeVisible()
    await expect(header).toHaveAttribute('aria-expanded', 'true')

    // Les 4 écrans du périmètre client restent atteignables en un clic.
    const modules = [/Formations/i, /Flotte véhicules/i, /Stations-service/i, /Agence de voyage/i]
    for (const label of modules) {
      await expect(nav.getByRole('link', { name: label })).toBeVisible()
    }

    // Géométrie : les 4 écrans sont SOUS le titre de section, lui-même sous
    // « Entreprises ». C'est ce qui en fait des sous-entrées, plus des rails.
    const companiesBox = await nav.getByRole('link', { name: /^Entreprises$/i }).boundingBox()
    const headerBox = await header.boundingBox()
    expect(companiesBox).not.toBeNull()
    expect(headerBox).not.toBeNull()
    expect(headerBox.y).toBeGreaterThan(companiesBox.y)
    for (const label of modules) {
      const box = await nav.getByRole('link', { name: label }).boundingBox()
      expect(box, `entrée ${label} absente du menu`).not.toBeNull()
      expect(box.y).toBeGreaterThan(headerBox.y)
    }

    // Le repli est réellement fonctionnel (et réversible).
    await header.click()
    await expect(header).toHaveAttribute('aria-expanded', 'false')
    await expect(nav.getByRole('link', { name: /Formations/i })).toBeHidden()
    await header.click()
    await expect(header).toHaveAttribute('aria-expanded', 'true')
    await expect(nav.getByRole('link', { name: /Formations/i })).toBeVisible()
  })
})
