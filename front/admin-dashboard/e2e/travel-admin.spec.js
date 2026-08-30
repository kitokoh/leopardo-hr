import { expect, test } from '@playwright/test'

/**
 * E2E BC-24 TRAVEL — écrans admin-dashboard (TRAVEL-601..609, lot #6078..#6087)
 * + non-régression UI (TRAVEL-1008, #6121).
 *
 * Stratégie :
 *  - Garde non authentifié : toujours exécuté (pas de backend requis).
 *  - Tests authentifiés : mock API (page.route) pour être déterministes en CI
 *    sans dépendre du backend ni du flag travelagency ; ils sont aussi
 *    utilisables contre staging (PLAYWRIGHT_AUTH_TOKEN) où les mocks ne sont
 *    pas posés si BACKEND_LIVE=1.
 */

const AUTHENTICATED = Boolean(process.env.PLAYWRIGHT_AUTH_TOKEN)
const LIVE = process.env.BACKEND_LIVE === '1'

// Premier test après démarrage du serveur vite : compilation à froid — marge
// large sur les timeouts de la suite.
test.describe.configure({ timeout: 120_000 })

const ADMIN_USER = {
  id: 1,
  name: 'Agent E2E',
  email: 'agent.e2e@leopardo.test',
  role: 'superadmin',
  language: 'fr',
}

/** Pose les mocks API nécessaires pour naviguer dans /travel sans backend. */
async function mockTravelApi(page, { pingStatus = 200 } = {}) {
  // Catch-alls D'ABORD — la dernière route enregistrée est prioritaire dans
  // cette configuration Playwright (précédence inverse) : les mocks
  // spécifiques, enregistrés ensuite, écrasent les catch-alls.
  // NB : l'app ajoute un cache-buster `?_t=` aux GET — les patterns
  // spécifiques sont des regex ancrées qui tolèrent la query string.
  await page.route('**/api/v1/travel/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
  )
  // Sans le catch-all API, les appels réels au backend (fetchDashboardData,
  // …) renverraient 401 avec un token de test → l'intercepteur détruit la
  // session → /login.
  await page.route('**/api/v1/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: ADMIN_USER }) }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
    route.fulfill({
      status: pingStatus,
      contentType: 'application/json',
      body: JSON.stringify({ data: { status: pingStatus === 200 ? 'ok' : 'disabled' } }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/countries(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          { id: 1, iso2: 'CM', iso3: 'CMR', name: 'Cameroun', phone_code: 237, status: 'active' },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/cities(\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          { id: 1, country_iso2: 'CM', name: 'Douala', region: 'Littoral', status: 'active' },
        ],
      }),
    }),
  )
  // TRAVEL-911/912 (#6416/#6417) : mocks des écrans contenu & contacts
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/quizzes(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          { id: 1, title: 'Quiz découverte Cameroun', status: 'active', starts_at: '2026-08-01T00:00:00+00:00', ends_at: '2026-09-01T00:00:00+00:00' },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/quizzes\/1(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 1,
          title: 'Quiz découverte Cameroun',
          description: 'Testez vos connaissances',
          status: 'active',
          questions: [
            { id: 11, question: 'Quelle est la capitale ?', options: ['Yaoundé', 'Douala'], points: 2 },
          ],
        },
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/quizzes\/1\/results(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          { id: 101, participant_email: 'marie@test.com', participant_name: 'Marie', score: 8, bonus: 1, submitted_at: '2026-08-10T09:00:00+00:00' },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/advert-types(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [{ id: 1, code: 'banner', label: 'Bannière' }] }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/advert-positions(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [{ id: 1, code: 'header', label: 'En-tête' }] }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/advert-prices(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [] }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/adverts(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          { id: 1, title: 'Annonce test', content: 'Contenu', status: 'validated', price_minor: 5000, currency: 'XAF', paid_at: '2026-08-10T09:00:00+00:00', expires_at: '2026-09-10T09:00:00+00:00' },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/tourist-sites(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          { id: 1, name: 'Chutes de la Lobé', city_id: 1, latitude: 2.88, longitude: 9.9, status: 'active' },
        ],
      }),
    }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/contacts(\\?.*)?$/, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          { id: 1, first_name: 'Jean', last_name: 'Dupont', email: 'jean.dupont@test.com', phone: '+237600000000', email_consent_given: true, sms_consent_given: false, whatsapp_consent_given: true, created_at: '2026-08-12T09:00:00+00:00' },
        ],
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

test.describe('TravelAgency — gate feature flag (TRAVEL-601)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('module non activé → état 403 honnête (navigation masquée)', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page, { pingStatus: 403 })
    await page.goto('/travel')

    await expect(
      page.getByRole('heading', { name: /Module Agence de voyage non activé/i }),
    ).toBeVisible({ timeout: 15_000 })
    await expect(page.getByRole('button', { name: /Réessayer/i })).toBeVisible()
  })

  test('module activé → section et onglets rendus', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page, { pingStatus: 200 })
    await page.goto('/travel')

    await expect(page.getByRole('heading', { name: /Agence de voyage/i }).first()).toBeVisible({
      timeout: 15_000,
    })
    for (const tab of ['Référentiel', 'Réservations', 'Check-in', 'Billets', 'Rapports', 'Locations & Hôtels', 'Contenu & Monétisation', 'Contacts']) {
      await expect(page.getByRole('tab', { name: new RegExp(tab, 'i') })).toBeVisible()
    }
  })

  test('entrée de navigation latérale « Agence de voyage »', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page, { pingStatus: 200 })
    await page.goto('/travel')

    const link = page.locator('a[href="/travel"]')
    await expect(link).toBeVisible({ timeout: 15_000 })
    await expect(link).toContainText(/Agence de voyage/i)
  })
})

test.describe('TravelAgency — écrans (TRAVEL-602..608)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('onglet Référentiel : pays & villes + sections CRUD', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    // Sous-sections du référentiel (TRAVEL-602)
    for (const sub of ['Pays & Villes', 'Stations', 'Bureaux', 'Compagnies', 'Classes', 'Véhicules']) {
      await expect(page.getByRole('tab', { name: new RegExp(sub, 'i') })).toBeVisible()
    }
    // Données mockées du référentiel géographique
    await expect(page.getByText('Cameroun')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Douala')).toBeVisible()
  })

  test('onglet Routes & Trajets : publications et tarifs', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Routes & Trajets/i }).click()
    await expect(page.getByRole('heading', { name: /Lignes & itinéraires/i })).toBeVisible({
      timeout: 15_000,
    })
    await expect(page.getByRole('heading', { name: /Trajets datés/i })).toBeVisible()
  })

  test('onglet Réservations : filtres + actions', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Réservations/i }).click()
    await expect(page.locator('#booking-status')).toBeVisible({ timeout: 15_000 })
    await expect(page.locator('#booking-trip')).toBeVisible()
    await expect(page.getByRole('button', { name: /Réinitialiser/i })).toBeVisible()
  })

  test('onglet Check-in : sélection trajet + manifeste', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Check-in/i }).click()
    await expect(page.locator('#checkin-trip')).toBeVisible({ timeout: 15_000 })
  })

  test('onglet Rapports : filtres période + export CSV', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Rapports/i }).click()
    await expect(page.locator('#report-from')).toBeVisible({ timeout: 15_000 })
    await expect(page.locator('#report-to')).toBeVisible()
    await expect(page.getByRole('button', { name: /Exporter CSV/i })).toBeVisible()
    // Onglets des rapports
    for (const rep of ['Ventes', 'Occupation', 'Recettes', 'Annulations']) {
      await expect(page.getByRole('tab', { name: new RegExp(rep, 'i') })).toBeVisible()
    }
  })

  test('onglet Locations & Hôtels : véhicules, réservations, hôtels', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Locations & Hôtels/i }).click()
    await expect(page.getByRole('heading', { name: /Véhicules de location/i })).toBeVisible({
      timeout: 15_000,
    })
    await expect(page.getByRole('heading', { name: /Réservations de location/i })).toBeVisible()
    await expect(page.getByRole('heading', { name: /Hôtels/i })).toBeVisible()
  })
})

test.describe('TravelAgency — contenu & monétisation (TRAVEL-911, #6416)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('onglet Contenu & Monétisation : sous-sections Quiz / Annonces / Sites', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contenu & Monétisation/i }).click()
    await expect(page.getByRole('heading', { name: /Quiz & jeu-concours/i })).toBeVisible({
      timeout: 15_000,
    })
    for (const sub of ['Quiz', 'Annonces', 'Sites touristiques']) {
      await expect(page.getByRole('tab', { name: new RegExp(sub, 'i') })).toBeVisible()
    }
    // Donnée mockée du quiz
    await expect(page.getByText('Quiz découverte Cameroun')).toBeVisible({ timeout: 15_000 })
  })

  test('quiz : gestion (questions) et résultats', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contenu & Monétisation/i }).click()
    await page.getByRole('button', { name: /Gérer/i }).click()
    await expect(page.getByText('Quelle est la capitale ?')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText(/Bonne réponse gérée côté serveur/i)).toBeVisible()
    // Fermer puis ouvrir les résultats
    await page.getByRole('button', { name: /Fermer/i }).first().click()
    await page.getByRole('button', { name: /Résultats/i }).click()
    await expect(page.getByText('marie@test.com')).toBeVisible({ timeout: 15_000 })
  })

  test('annonces : référentiels, grille tarifaire et cycle de vie', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contenu & Monétisation/i }).click()
    await page.getByRole('tab', { name: /Annonces/i }).click()
    await expect(page.getByText("Types d'annonces")).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Emplacements')).toBeVisible()
    // Grille tarifaire
    await page.getByRole('tab', { name: /Grille tarifaire/i }).click()
    await expect(page.getByRole('heading', { name: /Grille tarifaire/i })).toBeVisible({ timeout: 15_000 })
    // Liste des annonces + statut mocké (l'onglet externe « Annonces » existe aussi → .last())
    await page.getByRole('tab', { name: /^Annonces$/i }).last().click()
    await expect(page.getByText('Annonce test')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText(/Validée/i)).toBeVisible()
  })

  test('sites touristiques : CRUD + filtre par ville', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contenu & Monétisation/i }).click()
    await page.getByRole('tab', { name: /Sites touristiques/i }).click()
    await expect(page.getByText('Chutes de la Lobé')).toBeVisible({ timeout: 15_000 })
    await expect(page.locator('#travel-sites-city-filter')).toBeVisible()
  })
})

test.describe('TravelAgency — contacts voyageurs (TRAVEL-912, #6417)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('onglet Contacts : liste, consentements et actions', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /^Contacts$/i }).click()
    await expect(page.getByRole('heading', { name: /Contacts voyageurs/i })).toBeVisible({
      timeout: 15_000,
    })
    await expect(page.getByText('jean.dupont@test.com')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByRole('button', { name: /Consentements/i })).toBeVisible()
    await expect(page.getByRole('button', { name: /Notifier/i })).toBeVisible()
    // Badges de consentement (email oui, sms non)
    await expect(page.getByText(/Consenti/i).first()).toBeVisible()
    await expect(page.getByText(/Refusé/i).first()).toBeVisible()
  })

  test('nouvelle demande : soumission du formulaire (POST /travel/contact)', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /^Contacts$/i }).click()
    await page.getByRole('button', { name: /Nouvelle demande/i }).click()
    await page.locator('#travel-contact-email').fill('visiteur@test.com')
    await page.locator('#travel-contact-message').fill('Je souhaite des informations.')
    await page.locator('#travel-contact-consent-email').check()
    await page.getByRole('button', { name: /Enregistrer/i }).click()
    // La modale se ferme après le POST mocké (200)
    await expect(page.locator('#travel-contact-email')).not.toBeVisible({ timeout: 15_000 })
  })

  test('notification manuelle : ouverture modale + envoi', async ({ page }) => {
    await loginViaToken(page)
    await mockTravelApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /^Contacts$/i }).click()
    await page.getByRole('button', { name: /Notifier/i }).click()
    await expect(page.getByText(/Message borné/i)).toBeVisible({ timeout: 15_000 })
    await page.locator('#travel-notify-message').fill('Votre trajet est confirmé.')
    await page.getByRole('button', { name: /Envoyer/i }).click()
    await expect(page.locator('#travel-notify-message')).not.toBeVisible({ timeout: 15_000 })
  })
})
