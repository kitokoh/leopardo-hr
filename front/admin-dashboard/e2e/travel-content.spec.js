import { expect, test } from '@playwright/test'

/**
 * E2E BC-24 TRAVEL — écrans admin « Contenu & Monétisation » (TRAVEL-911,
 * #6416) : quiz, annonces payantes (types/positions/grille/cycle de vie)
 * et sites touristiques.
 *
 * Stratégie : mocks API (page.route) — déterministe en CI sans backend ;
 * utilisable contre staging via PLAYWRIGHT_AUTH_TOKEN (+ BACKEND_LIVE=1).
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

async function mockContentApi(page) {
  await page.route('**/api/v1/travel/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
  )
  await page.route('**/api/v1/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: ADMIN_USER }) }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { status: 'ok' } }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/cities(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [{ id: 1, country_iso2: 'CM', name: 'Douala', region: 'Littoral', status: 'active' }],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/advert-types(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [{ id: 1, code: 'sponso', label: 'Sponsoring' }],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/advert-positions(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [{ id: 1, code: 'hero', label: 'Bannière héro' }],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/advert-prices(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 1,
            advert_type_id: 1,
            advert_position_id: 1,
            advert_type: 'sponso',
            advert_position: 'hero',
            price_per_image_minor: 5000,
            price_per_character_minor: 100,
            currency: 'XAF',
          },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/adverts\/manage(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 10,
            advert_type_id: 1,
            advert_position_id: 1,
            title: 'Annonce soumise test',
            content: 'Contenu mocké',
            status: 'submitted',
            price_minor: 6000,
            currency: 'XAF',
            paid_at: null,
            validated_at: null,
            expires_at: null,
            visible: false,
          },
          {
            id: 11,
            advert_type_id: 1,
            advert_position_id: 1,
            title: 'Annonce validée test',
            content: 'Contenu mocké',
            status: 'validated',
            price_minor: 6000,
            currency: 'XAF',
            paid_at: '2026-08-29T10:00:00+00:00',
            validated_at: '2026-08-30T10:00:00+00:00',
            expires_at: '2026-09-29T10:00:00+00:00',
            visible: true,
          },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/quizzes(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [{ id: 1, title: 'Quiz test', status: 'active', starts_at: null, ends_at: null }],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/quizzes\/1\/questions(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 5,
            question: 'Capitale du Cameroun ?',
            options: ['Douala', 'Yaoundé'],
            correct_option_index: 1,
            points: 2,
            position: 0,
          },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/quizzes\/1\/results(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 9,
            participant_email: 'joueur@example.com',
            participant_name: 'Joueur Test',
            score: 2,
            bonus: 0,
            submitted_at: '2026-08-30T08:00:00+00:00',
          },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/tourist-sites(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [{ id: 1, name: 'Chutes de la Lobé', city_id: 1, status: 'active' }],
      }),
    }),
  )
}

async function loginViaToken(page) {
  await page.addInitScript((token) => {
    sessionStorage.setItem('admin_token', token)
  }, process.env.PLAYWRIGHT_AUTH_TOKEN)
}

test.describe('TravelAgency — garde non authentifié', () => {
  test('la route /travel redirige vers /login sans session', async ({ page }) => {
    await page.goto('/travel', { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/\/login/, { timeout: 45_000 })
  })
})

test.describe('TravelAgency — Contenu & Monétisation (TRAVEL-911)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('onglet Contenu : sections quiz, annonces et sites rendues', async ({ page }) => {
    await loginViaToken(page)
    await mockContentApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contenu & Monétisation/i }).click()
    await expect(page.getByRole('heading', { name: /Quiz & jeux-concours/i })).toBeVisible({
      timeout: 15_000,
    })
    await expect(page.getByRole('heading', { name: /Annonces payantes/i })).toBeVisible()
    await expect(page.getByRole('heading', { name: /Sites touristiques/i })).toBeVisible()
  })

  test('quiz : liste, gestion des questions et résultats (bons endpoints mockés)', async ({ page }) => {
    await loginViaToken(page)
    await mockContentApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contenu & Monétisation/i }).click()
    await expect(page.getByText('Quiz test')).toBeVisible({ timeout: 15_000 })

    // Modal questions : la bonne réponse n'est visible que côté gestion.
    await page.getByRole('button', { name: /Questions/i }).first().click()
    await expect(page.getByText('Capitale du Cameroun ?')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText(/Bonne réponse : 1/)).toBeVisible()
    await page.getByRole('button', { name: /Fermer/i }).first().click()

    // Modal résultats : tri serveur.
    await page.getByRole('button', { name: /Résultats/i }).first().click()
    await expect(page.getByText('Joueur Test')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('joueur@example.com')).toBeVisible()
    await page.getByRole('button', { name: /Fermer/i }).first().click()
  })

  test('annonces : liste admin avec statuts et actions de cycle de vie', async ({ page }) => {
    await loginViaToken(page)
    await mockContentApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contenu & Monétisation/i }).click()
    await expect(page.getByText('Annonce soumise test')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Annonce validée test')).toBeVisible()

    // Soumise → action « Payer » ; validée → action « Renouveler ».
    const soumiseRow = page.locator('tr', { hasText: 'Annonce soumise test' })
    await expect(soumiseRow.getByRole('button', { name: /Payer/i })).toBeVisible()

    const valideeRow = page.locator('tr', { hasText: 'Annonce validée test' })
    await expect(valideeRow.getByRole('button', { name: /Renouveler/i })).toBeVisible()
  })

  test('sites touristiques : CRUD rendu avec filtre ville', async ({ page }) => {
    await loginViaToken(page)
    await mockContentApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contenu & Monétisation/i }).click()
    await expect(page.getByText('Chutes de la Lobé')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByRole('button', { name: /Toutes les villes/i })).toBeVisible()
  })
})
