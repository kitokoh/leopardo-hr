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

/**
 * #7553/#7554 — la session plateforme n'est plus validée sur `role` (qui reste
 * `'super_admin'`, y compris pour les rôles internes) mais sur `platform_role`
 * et `permissions` : le menu latéral est filtré sur ces permissions. Les specs
 * simulent donc une session `super_admin` complète avec sa matrice, sinon la
 * plupart des entrées sont (à juste titre) masquées.
 */
const SUPER_ADMIN_USER = {
  id: 1,
  name: 'Agent E2E',
  email: 'agent.e2e@leopardo.test',
  role: 'super_admin',
  platform_role: 'super_admin',
  permissions: [
    'companies.view', 'companies.manage', 'companies.provision', 'billing.view', 'billing.manage',
    'plans.view', 'users.view', 'users.manage', 'impersonate', 'killswitch.manage',
    'observability.view', 'metrics.view', 'support.manage', 'announcements.manage', 'crm.view',
    'showcase.manage', 'edge.manage', 'team.manage',
  ],
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
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: SUPER_ADMIN_USER }) }),
    )

    // La route « dashboard » est servie sur `/` (`src/router/index.js`) :
    // `/dashboard` tombe sur la route `not-found`, hors du layout — la spec
    // n'y trouvait aucun menu et échouait dès la première assertion.
    await page.goto('/')
    await expect(page.getByRole('navigation')).toBeVisible({ timeout: 15_000 })

    // Sidebar visible : vérifier l'unicité des entrées (liens de nav).
    // #7725 : « Stations-service » est désormais UNE entrée à sous-menu
    // (bouton parent + enfants Hub/Opérations) — le libellé exact
    // n'apparaît qu'une fois, sur le déclencheur du sous-menu.
    await expect(page.getByRole('navigation').getByText('Stations-service', { exact: true })).toHaveCount(1)
    await expect(page.getByRole('navigation').getByText('Chat IA', { exact: true })).toHaveCount(1)
    // Pas de clé de rendu dupliquée : le sous-menu fuel expose bien le hub
    // (`/fuel-station`) et les opérations en UN exemplaire chacun.
    await expect(page.getByRole('navigation').getByRole('button', { name: /^Stations-service$/i })).toHaveCount(1)
    await expect(page.getByRole('navigation').getByRole('link', { name: /^Hub stations-service$/i })).toHaveCount(1)
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

  test('les modules clients sont sous « Entreprises », section dépliée par défaut (#7855 : Formations vit sous RH & Paie)', async ({ page }) => {
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

    // Les écrans du périmètre client restent atteignables en un clic.
    // #7725 : « Stations-service » devient un sous-menu (Hub / Opérations).
    // #7855/#7898 : « Formations » quitte « Modules clients » pour le groupe
    // « RH & Paie » (même route /training) — vérifié plus bas.
    const modules = [
      /^Flotte véhicules$/i,
      /^Agence de voyage$/i,
    ]
    for (const label of modules) {
      await expect(nav.getByRole('link', { name: label })).toBeVisible()
    }
    const fuelSubmenu = nav.getByRole('button', { name: /^Stations-service$/i })
    await expect(fuelSubmenu).toBeVisible()
    await expect(fuelSubmenu).toHaveAttribute('aria-expanded', 'true')
    await expect(nav.getByRole('link', { name: /^Hub stations-service$/i })).toBeVisible()

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
    await expect(nav.getByRole('link', { name: /^Flotte véhicules$/i })).toBeHidden()
    await header.click()
    await expect(header).toHaveAttribute('aria-expanded', 'true')
    await expect(nav.getByRole('link', { name: /^Flotte véhicules$/i })).toBeVisible()

    // #7855/#7898 — « Formations » vit désormais sous « RH & Paie » : toujours
    // atteignable, mais rattaché à ce groupe (repli du groupe = lien masqué).
    const rhPaie = nav.getByRole('button', { name: /^RH & Paie$/i })
    await expect(rhPaie).toBeVisible()
    await expect(rhPaie).toHaveAttribute('aria-expanded', 'true')
    await expect(nav.getByRole('link', { name: /^Formations$/i })).toBeVisible()
    await rhPaie.click()
    await expect(rhPaie).toHaveAttribute('aria-expanded', 'false')
    await expect(nav.getByRole('link', { name: /^Formations$/i })).toBeHidden()
    await rhPaie.click()
    await expect(nav.getByRole('link', { name: /^Formations$/i })).toBeVisible()
  })
})

/**
 * #7554 — cohérence du menu et de la palette : les deux consomment la même
 * source de vérité (`src/navigation/navigation.js`). Ce bloc pinne les
 * régressions constatées sur la branche :
 *  - des pages routées mais INATTEIGNABLES depuis le menu (paramétrage paie,
 *    surveys de solutions, opérations stations-service) ;
 *  - un pied de sidebar redondant avec l'entrée « Mon compte » ;
 *  - un surlignage actif jamais appliqué à la vitrine (l'entrée portait
 *    `name: 'showcase'` alors que la route s'appelle `showcase-editor`) ;
 *  - une palette de commandes décrite en dur (12 entrées sur ~30 du menu).
 */
const SUPER_ADMIN_ME = {
  data: {
    id: 1,
    name: 'Super Administrateur',
    email: 'admin@leopardo-rh.com',
    role: 'super_admin',
    platform_role: 'super_admin',
    permissions: [
      'companies.view', 'companies.manage', 'companies.provision', 'billing.view', 'billing.manage',
      'plans.view', 'users.view', 'users.manage', 'impersonate', 'killswitch.manage',
      'observability.view', 'metrics.view', 'support.manage', 'announcements.manage', 'crm.view',
      'showcase.manage', 'edge.manage', 'team.manage',
    ],
  },
}

const MOCKED_JSON = (body) => ({
  status: 200,
  contentType: 'application/json',
  body: JSON.stringify(body),
})

async function stubAuthenticatedSuperAdmin(page) {
  await page.addInitScript(() => {
    sessionStorage.setItem('admin_token', 'e2e-sidebar-truth-token')
    localStorage.setItem('admin_locale', 'fr')
  })

  await page.route('**/api/v1/**', (route) => route.fulfill(MOCKED_JSON({ data: {} })))
  await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) => route.fulfill(MOCKED_JSON(SUPER_ADMIN_ME)))
  await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
    route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({}) }))

  // Cockpit plateforme (rendu sur `/`) : les KPI de StatsCard exigent ces
  // shapes, sinon le rendu échoue et casse la navigation suivante.
  await page.route(/\/api\/v1\/platform\/metrics\/overview(\?.*)?$/, (route) =>
    route.fulfill(MOCKED_JSON({
      data: {
        revenue: { currency: 'EUR', mrr: 12345, arr: 148140 },
        companies: { total: 3, active: 2, trial: 1, suspended: 0, expired: 0 },
        subscriptions: { total: 2, active: 2 },
      },
    })))
  await page.route(/\/api\/v1\/platform\/companies\/health(\?.*)?$/, (route) =>
    route.fulfill(MOCKED_JSON({
      data: { summary: { active_companies: 2, companies: 3, mrr: 12345, risk: { high: 0, medium: 0, low: 0 } }, items: [] },
    })))
  await page.route(/\/api\/v1\/platform\/company-requests(\?.*)?$/, (route) =>
    route.fulfill(MOCKED_JSON({ data: [], meta: { total: 0 } })))
  await page.route(/\/api\/v1\/admin\/dashboard\/stats(\?.*)?$/, (route) =>
    route.fulfill(MOCKED_JSON({
      totalUsers: 4, totalCompanies: 3, activeSubscriptions: 2, monthlyRevenue: 12345,
      newUsersToday: 0, newCompaniesToday: 0, supportTickets: 0, systemHealth: 'good',
    })))
  await page.route(/\/api\/v1\/admin\/dashboard\/(activities|alerts)(\?.*)?$/, (route) =>
    route.fulfill(MOCKED_JSON({ data: [] })))
  await page.route(/\/api\/v1\/notifications(\?.*)?$/, (route) =>
    route.fulfill(MOCKED_JSON({ data: [], meta: { total: 0 } })))
}

test.describe('Sidebar — source de vérité unique (#7554)', () => {
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('les pages orphelines sont atteignables et le surlignage suit le nom de route', async ({ page }) => {
    await stubAuthenticatedSuperAdmin(page)
    await page.goto('/')

    const nav = page.getByRole('navigation')
    await expect(nav).toBeVisible({ timeout: 15_000 })

    // Pages routées mais inatteignables avant #7554 : une entrée, visible.
    // #7725 : accents fr corrigés (« Barèmes fiscaux », « Taux légaux ») et
    // les opérations stations-service vivent dans le sous-menu (« Opérations »).
    const orphanPages = [
      /^Surveys de solutions$/i,
      /^Opérations$/i,
      /^Cotisations sociales$/i,
      /^Barèmes fiscaux$/i,
      /^Taux légaux$/i,
      /^Jours fériés$/i,
    ]
    for (const label of orphanPages) {
      await expect(nav.getByRole('link', { name: label })).toHaveCount(1)
      await expect(nav.getByRole('link', { name: label })).toBeVisible()
    }

    // #7725 — « Mon compte » et « Déconnexion » ont quitté la sidebar : ils
    // vivent dans le menu utilisateur du header.
    await expect(nav.getByRole('link', { name: /^Mon compte$/i })).toHaveCount(0)
    await expect(nav.getByRole('link', { name: /^Déconnexion$/i })).toHaveCount(0)
    await page.getByTestId('admin-user-menu-toggle').click()
    const userMenu = page.getByTestId('admin-user-menu')
    await expect(userMenu).toBeVisible()
    await expect(userMenu.getByRole('menuitem', { name: /Mon compte/i })).toBeVisible()
    await expect(userMenu.getByTestId('admin-user-menu-logout')).toBeVisible()

    // Surlignage actif : la vitrine porte le `name` de sa route
    // (`showcase-editor`) — avec `showcase`, le lien n'était jamais actif.
    await page.goto('/showcase')
    const showcaseLink = nav.getByRole('link', { name: /^Site vitrine$/i })
    await expect(showcaseLink).toHaveCount(1)
    await expect(showcaseLink).toHaveAttribute('aria-current', 'page')
  })

  test('la palette de commandes propose les mêmes destinations que le menu', async ({ page }) => {
    await stubAuthenticatedSuperAdmin(page)
    await page.goto('/')
    await expect(page.getByRole('navigation')).toBeVisible({ timeout: 15_000 })

    await page.keyboard.press('Control+k')
    const palette = page.locator('[data-testid="command-palette"]')
    await expect(palette).toBeVisible({ timeout: 10_000 })
    const paletteInput = palette.locator('input[type="text"]')
    await expect(paletteInput).toBeVisible()

    // Entrées absentes de l'ancienne liste `itemDefs` en dur : elles ne
    // peuvent apparaître que si la palette consomme la source de vérité.
    await paletteInput.fill('Équipe plateforme')
    await expect(palette.getByText('Équipe plateforme', { exact: true })).toBeVisible()

    await paletteInput.fill('Jours fériés')
    await expect(palette.getByText('Jours fériés', { exact: true })).toBeVisible()
  })
})
