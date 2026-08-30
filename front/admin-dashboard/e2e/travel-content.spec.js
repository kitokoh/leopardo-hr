import { expect, test } from '@playwright/test'

// TRAVEL-911 (#6416) — Écrans contenu & monétisation : annonces (catalogue +
// cycle de vie), quiz (questions/résultats), sites touristiques. Endpoints
// réels mockés (enveloppe Laravel), i18n fr.
const AUTH_ME = {
  data: { id: 1, name: 'Super Admin', email: 'admin@leopardo-rh.com', role: 'super_admin' },
}

function makeList(body) {
  return {
    data: body,
    meta: { current_page: 1, last_page: 1, per_page: 1000, total: body.length },
  }
}

test.describe('Contenu & monétisation travel (TRAVEL-911)', () => {
  test.beforeEach(async ({ page }) => {
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    await page.route(/\/api\/v1\/travel\/advert-types(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, code: 'image_banner', name: 'Bannière image', description: null },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/advert-positions(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, code: 'home_top', name: 'Accueil haut', description: null },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/advert-prices(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, advert_type_id: 1, advert_position_id: 1, price_image_minor: 50000, price_character_minor: 25, currency: 'XAF' },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/adverts(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 10, title: 'Promo Douala', status: 'paid', total_minor: 50325, currency: 'XAF', expires_at: null },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/quizzes(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 2, title: 'Quiz Cameroun', status: 'published', max_attempts: 1 },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/quizzes\/2(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
        data: { id: 2, title: 'Quiz Cameroun', status: 'published', max_attempts: 1, questions: [
          { id: 5, question: 'Capitale ?', options: ['Douala', 'Yaoundé'], points: 10, sort_order: 1 },
        ] },
      }) }),
    )
    await page.route(/\/api\/v1\/travel\/quizzes\/2\/participations(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 9, quiz_id: 2, participant_id: 3, score: 10, status: 'completed', completed_at: '2026-08-30T10:00:00Z' },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/tourist-sites(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 7, name: 'Chutes de la Lobé', city_id: 1, status: 'published' },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/cities(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, name: 'Yaoundé' },
      ])) }),
    )
  })

  test('annonces : liste le catalogue et les annonces', async ({ page }) => {
    await page.goto('/travel/adverts')
    await expect(page.getByText('Annonces payantes')).toBeVisible()
    await expect(page.getByText('Promo Douala')).toBeVisible()
    await expect(page.getByText('Payer')).toBeVisible()
    await page.getByRole('button', { name: 'Types' }).click()
    await expect(page.getByText('Bannière image')).toBeVisible()
    await page.getByRole('button', { name: 'Tarifs' }).click()
    await expect(page.getByText('50000')).toBeVisible()
  })

  test('quiz : gère les questions et affiche les résultats', async ({ page }) => {
    await page.goto('/travel/quizzes')
    await expect(page.getByText('Quiz Cameroun')).toBeVisible()
    await page.getByRole('button', { name: 'Gérer' }).click()
    await expect(page.getByText('Capitale ?')).toBeVisible()
    await expect(page.getByText('Résultats')).toBeVisible()
    await expect(page.getByText('10')).toBeVisible()
  })

  test('sites : liste l’annuaire par ville', async ({ page }) => {
    await page.goto('/travel/sites')
    await expect(page.getByText('Chutes de la Lobé')).toBeVisible()
    await expect(page.getByText('Yaoundé')).toBeVisible()
  })
})
