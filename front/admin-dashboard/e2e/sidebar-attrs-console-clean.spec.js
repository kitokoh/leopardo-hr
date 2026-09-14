import { expect, test } from '@playwright/test'

// #4415 : creds de test via env — jamais de littéral prod dans le dépôt.
const E2E_ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || 'e2e-fixture-password'

const withQuery = (path) => new RegExp(`\\/api\\/v1\\/${path}(?:\\?.*)?$`)
const json = (body) => ({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })

/**
 * #7305 — garde de non-régression : le back-office ne doit plus produire
 * l'avertissement Vue
 *
 *   [Vue warn]: Extraneous non-props attributes (class) were passed to
 *   component but could not be automatically inherited because component
 *   renders fragment or text or teleport root nodes. at <Sidebar …>
 *
 * Cause : `Sidebar.vue` a une racine FRAGMENTAIRE (l'overlay mobile
 * `<transition>` et la sidebar sont deux nœuds frères), et le layout lui
 * passait un `class` — attribut qui était donc silencieusement perdu.
 * Correctif : `inheritAttrs: false` + `v-bind="$attrs"` sur la racine
 * « sidebar » dans le composant, et suppression du `class` redondant du layout
 * (ces classes sont déjà portées par la racine du composant).
 *
 * Ce test échoue si l'avertissement réapparaît sur une page du back-office.
 */

test('le back-office ne produit plus d’avertissement Vue sur <Sidebar> (attributs perdus)', async ({ page }) => {
  test.setTimeout(60_000)

  const vueWarnings = []
  page.on('console', (message) => {
    const text = message.text()
    if (
      message.type() === 'warning' &&
      (text.includes('Extraneous non-props attributes') ||
        text.includes('could not be automatically inherited'))
    ) {
      vueWarnings.push(text)
    }
  })

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
  await page.route(withQuery('platform/companies'), (route) => route.fulfill(json({ data: [] })))
  await page.route(withQuery('platform/companies/health'), (route) =>
    route.fulfill(json({ data: { summary: { companies: 0 }, items: [] } })))

  await page.goto('/login')
  await page.locator('#email').fill('admin@leopardo-rh.com')
  await page.locator('#password').fill(E2E_ADMIN_PASSWORD)
  await page.getByRole('button', { name: /^Se connecter$/i }).click()
  await expect(page).toHaveURL(/\/$/)

  // La sidebar est un élément structurel du layout : on vérifie qu'elle est bien
  // rendue (sinon le test passerait pour de mauvaises raisons — page vide)…
  const sidebar = page.locator('aside, nav[role="navigation"]').first()
  await expect(sidebar).toBeVisible()

  // …et que le retrait du `class` redondant du layout n'a PAS cassé le
  // positionnement : ces classes (`fixed inset-y-0 left-0 z-50`) vivent dans la
  // racine du composant, la sidebar doit donc rester en position fixe, calée à
  // gauche, et au-dessus du contenu.
  const sidebarRoot = page.locator('div.fixed.inset-y-0.left-0').first()
  await expect(sidebarRoot).toBeAttached()
  const box = await sidebarRoot.evaluate((el) => {
    const style = getComputedStyle(el)
    return { position: style.position, left: style.left, zIndex: Number(style.zIndex) }
  })
  expect(box.position).toBe('fixed')
  expect(box.left).toBe('0px')
  expect(box.zIndex).toBeGreaterThanOrEqual(50)

  expect(
    vueWarnings,
    `Avertissement Vue « attributs perdus » détecté ${vueWarnings.length} fois :\n${vueWarnings.join('\n')}`
  ).toHaveLength(0)
})
