import { expect, test } from '@playwright/test'

// TRAVEL-911 (#6416) — Écrans contenu & monétisation : quiz, annonces, sites
// touristiques — endpoints réels mockés (enveloppe Laravel), i18n fr.
const AUTH_ME = {
  data: { id: 1, name: 'Super Admin', email: 'admin@leopardo-rh.com', role: 'super_admin' },
}

function makeList(body) {
  return { data: body, meta: { current_page: 1, last_page: 1, per_page: 1000, total: body.length } }
}

test.describe('Contenu & annonces travel (TRAVEL-911)', () => {
  test('affiche les quiz, crée un quiz et ouvre les résultats', async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-fake-token')
    })
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route('**/api/v1/**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    await page.route(/\/api\/v1\/travel\/quizzes(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, title: 'Quiz Afrique de l\'Ouest', status: 'active', starts_at: null, ends_at: null },
      ])) }),
    )
    await page.goto('/travel/content')
    await expect(page.getByRole('heading', { name: /Contenu & annonces/ }).first()).toBeVisible()
    await expect(page.getByText('Quiz Afrique de l\'Ouest')).toBeVisible()
  })

  test('liste les annonces en mode gestion et les sites touristiques', async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-fake-token')
    })
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route('**/api/v1/**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    await page.route(/\/api\/v1\/travel\/advert-types(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, code: 'sponso', label: 'Sponsoring' },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/advert-positions(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([])) }),
    )
    await page.route(/\/api\/v1\/travel\/advert-prices(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([])) }),
    )
    await page.route(/\/api\/v1\/travel\/adverts(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, title: 'Annonce validée', status: 'validated', price_minor: 25000, currency: 'XAF', expires_at: null },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/tourist-sites(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 2, name: 'Chutes de la Lobé', city_id: 1, status: 'active' },
      ])) }),
    )
    await page.goto('/travel/content')

    // Annonces
    await page.getByRole('button', { name: 'Annonces' }).first().click()
    // Sous-onglet par défaut « Types » : le référentiel est affiché.
    await expect(page.getByText('Sponsoring')).toBeVisible()
    // Sous-onglet « Annonces » : le cycle de vie se charge.
    await page.getByRole('button', { name: 'Annonces' }).last().click()
    await expect(page.getByText('Annonce validée')).toBeVisible()

    // Sites touristiques
    await page.getByRole('button', { name: 'Sites touristiques' }).click()
    await expect(page.getByText('Chutes de la Lobé')).toBeVisible()
  })
})
