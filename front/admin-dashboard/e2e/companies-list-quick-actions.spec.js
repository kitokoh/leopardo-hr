import { expect, test } from '@playwright/test'

// #4415 : creds de test via env — jamais de littéral prod dans le dépôt.
const E2E_ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || 'e2e-fixture-password'

const withQuery = (path) => new RegExp(`\\/api\\/v1\\/${path}(?:\\?.*)?$`)
const json = (body) => ({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })

const row = (id, name, status) => ({ id, name, status, country: 'DZ', currency: 'DZD' })
const meta = (currentPage, lastPage, total) => ({
  current_page: currentPage,
  last_page: lastPage,
  per_page: 25,
  total,
})

/**
 * Issue #7431 — liste des entreprises : sans quitter la liste, le super-admin
 * doit pouvoir RECHERCHER, FILTRER par statut, PAGINER et suspendre/activer
 * une société. Le scoring du portefeuille (#7302) est mocké ici : il n'entre
 * pas dans le périmètre de ce test.
 */
async function mockAdminShell(page) {
  await page.route('**/api/v1/platform/auth/login', (route) =>
    route.fulfill(json({
      data: { id: 1, name: 'Super Administrateur', email: 'admin@leopardo-rh.com', role: 'super_admin', two_fa_enabled: false },
      token: 'platform-admin-token',
      token_type: 'Bearer',
    })))

  await page.route(withQuery('platform/auth/me'), (route) =>
    route.fulfill(json({
      data: { id: 1, name: 'Super Administrateur', email: 'admin@leopardo-rh.com', role: 'super_admin', two_fa_enabled: false },
    })))

  // Bruit du layout (DashboardLayout) : mocks neutres.
  for (const path of ['admin/dashboard/stats', 'admin/dashboard/activities', 'admin/dashboard/alerts', 'notifications', 'platform/company-requests']) {
    await page.route(withQuery(path), (route) => route.fulfill(json({ data: [], meta: { total: 0 } })))
  }
  await page.route(withQuery('platform/metrics/overview'), (route) => route.fulfill(json({ data: {} })))
  await page.route(withQuery('platform/companies/health'), (route) =>
    route.fulfill(json({
      data: {
        summary: { companies: 2, active_companies: 1, mrr: 0, risk: { high: 0, medium: 0, low: 0 } },
        items: [],
      },
    })))
}

async function signIn(page) {
  await page.goto('/login')
  await page.locator('#email').fill('admin@leopardo-rh.com')
  await page.locator('#password').fill(E2E_ADMIN_PASSWORD)
  await page.getByRole('button', { name: /^Se connecter$/i }).click()
  await expect(page).toHaveURL(/\/$/)
}

test('la liste des sociétés se recherche, se filtre et se pagine côté serveur', async ({ page }) => {
  test.setTimeout(60_000)
  await mockAdminShell(page)

  // Chaque appel d'annuaire est enregistré : on vérifie ce qui part VRAIMENT
  // au serveur (les filtres ne sont pas appliqués en mémoire sur une page).
  const calls = []
  await page.route(withQuery('platform/companies'), (route) => {
    const url = new URL(route.request().url())
    calls.push(url)
    const search = url.searchParams.get('search') || ''
    const status = url.searchParams.get('status') || ''
    const currentPage = url.searchParams.get('page') || '1'

    if (search === 'zzz') {
      return route.fulfill(json({ data: [], meta: meta(1, 1, 0) }))
    }
    if (search === 'beta') {
      return route.fulfill(json({ data: [row('c2', 'Beta SARL', 'trial')], meta: meta(1, 1, 1) }))
    }
    if (status === 'suspended') {
      return route.fulfill(json({ data: [row('c3', 'Gamma SARL', 'suspended')], meta: meta(1, 1, 1) }))
    }
    if (currentPage === '2') {
      return route.fulfill(json({ data: [row('c2', 'Beta SARL', 'trial')], meta: meta(2, 2, 26) }))
    }
    return route.fulfill(json({ data: [row('c1', 'Alpha SARL', 'active'), row('c2', 'Beta SARL', 'trial')], meta: meta(1, 2, 26) }))
  })

  await signIn(page)
  await page.goto('/companies')

  const lastCall = () => calls[calls.length - 1]

  // (a) `meta.last_page > 1` rend la pagination VISIBLE (plus de troncature muette).
  await expect(page.getByText('Alpha SARL')).toBeVisible()
  await expect(page.getByTestId('companies-previous-page')).toBeVisible()
  await expect(page.getByTestId('companies-previous-page')).toBeDisabled()
  await expect(page.getByText(/Page 1 sur 2/)).toBeVisible()
  await expect(page.getByText(/26 sociétés/)).toBeVisible()
  expect(lastCall().searchParams.get('page')).toBe('1')
  expect(lastCall().searchParams.get('per_page')).toBe('25')

  // (b) Suivant / Précédent : la page est demandée au serveur.
  await page.getByTestId('companies-next-page').click()
  await expect(page.getByText(/Page 2 sur 2/)).toBeVisible()
  await expect(page.getByTestId('companies-next-page')).toBeDisabled()
  expect(lastCall().searchParams.get('page')).toBe('2')

  await page.getByTestId('companies-previous-page').click()
  await expect(page.getByText(/Page 1 sur 2/)).toBeVisible()
  expect(lastCall().searchParams.get('page')).toBe('1')

  // (c) Recherche : `search` part au serveur (debounce ~300 ms) et la liste
  // repart de la page 1. Une recherche qui ne renvoie qu'une ligne avec
  // `last_page=1` masque la pagination — preuve que `meta` fait autorité.
  await page.getByTestId('companies-search').fill('beta')
  await expect.poll(() => lastCall().searchParams.get('search')).toBe('beta')
  await expect(page.getByTestId('companies-next-page')).toHaveCount(0)
  expect(lastCall().searchParams.get('page')).toBe('1')

  // (d) Aucun résultat : état vide EXPLICITE, jamais un tableau blanc.
  await page.getByTestId('companies-search').fill('zzz')
  await expect(page.getByText(/Aucune société ne correspond à cette recherche/)).toBeVisible()

  // (e) Réinitialiser : retour à l'annuaire complet.
  await page.getByTestId('companies-reset-filters').click()
  await expect(page.getByText('Alpha SARL')).toBeVisible()
  await expect(page.getByTestId('companies-next-page')).toBeVisible()
  expect(lastCall().searchParams.get('search')).toBeNull()

  // (f) Filtre de statut : `status` part au serveur.
  await page.getByTestId('companies-status').selectOption('suspended')
  await expect(page.getByText('Gamma SARL')).toBeVisible()
  await expect(page.getByText('Alpha SARL')).toHaveCount(0)
  await expect.poll(() => lastCall().searchParams.get('status')).toBe('suspended')
})

test('suspendre demande confirmation, activer non — et le PATCH ne perd pas l’abonnement', async ({ page }) => {
  test.setTimeout(60_000)
  await mockAdminShell(page)

  // État serveur mutable : la liste relue après une action doit refléter le
  // nouveau statut (sinon le test ne prouverait qu'un toast).
  const statuses = { c1: 'active', c2: 'suspended' }
  await page.route(withQuery('platform/companies'), (route) =>
    route.fulfill(json({
      data: [row('c1', 'Alpha SARL', statuses.c1), row('c2', 'Beta SARL', statuses.c2)],
      meta: meta(1, 1, 2),
    })))

  /**
   * PIÈGE #7431 : `PATCH /platform/companies/{id}/subscription` exige
   * `plan_id` + `status` et écrit `subscription_start`/`subscription_end`/
   * `notes` à `null` quand ils ne sont pas envoyés. On vérifie donc que la vue
   * relit l'abonnement (GET) et renvoie TOUT l'état courant.
   */
  const patches = []
  for (const id of ['c1', 'c2']) {
    await page.route(withQuery(`platform/companies/${id}/subscription`), (route) => {
      if (route.request().method() === 'PATCH') {
        const body = JSON.parse(route.request().postData() || '{}')
        patches.push({ id, body })
        statuses[id] = body.status
        return route.fulfill(json({ data: { company_id: id, status: body.status } }))
      }
      return route.fulfill(json({
        data: {
          company_id: id,
          status: statuses[id],
          plan: { id: 7, name: 'Pilot' },
          subscription_start: '2026-01-01T00:00:00.000000Z',
          subscription_end: '2026-12-31T23:59:59.000000Z',
          currency: 'EUR',
          notes: 'Client pilote',
        },
      }))
    })
  }

  await signIn(page)
  await page.goto('/companies')
  await expect(page.getByText('Alpha SARL')).toBeVisible()

  // (a) Suspendre : confirmation OBLIGATOIRE avant l'appel API (#7433/#7431).
  await page.getByTestId('companies-suspend-c1').click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  await expect(dialog.getByText(/Suspendre cette société/)).toBeVisible()
  expect(patches, 'aucun PATCH avant confirmation').toHaveLength(0)

  await dialog.getByRole('button', { name: 'Annuler' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  expect(patches, 'annuler ne doit rien écrire').toHaveLength(0)

  await page.getByTestId('companies-suspend-c1').click()
  await page.getByRole('dialog').getByRole('button', { name: 'Suspendre' }).click()

  await expect(page.getByText(/Société suspendue/)).toBeVisible()
  expect(patches).toHaveLength(1)
  expect(patches[0].id).toBe('c1')
  expect(patches[0].body).toEqual({
    plan_id: 7,
    status: 'suspended',
    subscription_start: '2026-01-01T00:00:00.000000Z',
    subscription_end: '2026-12-31T23:59:59.000000Z',
    notes: 'Client pilote',
  })
  // La ligne relue depuis le serveur propose désormais d'ACTIVER.
  await expect(page.getByTestId('companies-activate-c1')).toBeVisible()

  // (b) Activer : action directe, sans dialogue de confirmation.
  await page.getByTestId('companies-activate-c2').click()
  await expect(page.getByText(/Société activée/)).toBeVisible()
  expect(patches).toHaveLength(2)
  expect(patches[1].id).toBe('c2')
  expect(patches[1].body.status).toBe('active')
  expect(patches[1].body.subscription_end).toBe('2026-12-31T23:59:59.000000Z')
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByTestId('companies-suspend-c2')).toBeVisible()
})

test('un échec de mise à jour du statut est visible (jamais silencieux)', async ({ page }) => {
  test.setTimeout(60_000)
  await mockAdminShell(page)

  await page.route(withQuery('platform/companies'), (route) =>
    route.fulfill(json({ data: [row('c1', 'Alpha SARL', 'active')], meta: meta(1, 1, 1) })))

  await page.route(withQuery('platform/companies/c1/subscription'), (route) => {
    if (route.request().method() === 'PATCH') {
      // 422 : le plan a disparu entre la lecture et l'écriture, par exemple.
      return route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Le plan selectionne est invalide.' }),
      })
    }
    return route.fulfill(json({
      data: {
        company_id: 'c1',
        status: 'active',
        plan: { id: 7, name: 'Pilot' },
        subscription_start: null,
        subscription_end: null,
        currency: 'EUR',
        notes: null,
      },
    }))
  })

  await signIn(page)
  await page.goto('/companies')
  await expect(page.getByText('Alpha SARL')).toBeVisible()

  await page.getByTestId('companies-suspend-c1').click()
  await page.getByRole('dialog').getByRole('button', { name: 'Suspendre' }).click()

  // Le message du serveur est affiché tel quel : l'échec n'est pas silencieux.
  await expect(page.getByText(/Le plan selectionne est invalide/).first()).toBeVisible()
  // La ligne reste sur son statut réel (aucune bascule optimiste mensongère).
  await expect(page.getByTestId('companies-suspend-c1')).toBeVisible()
})
