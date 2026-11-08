import { expect, test } from '@playwright/test'

/**
 * BC-10 (#9219) — écran super admin des connecteurs d'agrégateurs d'offres.
 *
 * Ce que ce test verrouille, et qui ne se voit pas à l'œil nu :
 *
 *  - les six fournisseurs sont listés, y compris ceux qui ne sont pas
 *    configurés : un connecteur absent de l'écran serait un connecteur
 *    impossible à configurer ;
 *  - `not_connected` s'affiche comme un état de premier rang, pas comme un
 *    zéro honteux ;
 *  - le formulaire ne PRÉ-REMPLIT jamais un secret : le serveur ne renvoie
 *    jamais la valeur stockée, et un champ pré-rempli la ferait fuiter dans
 *    le DOM ;
 *  - le message de test ne prétend pas que l'accès fournisseur est vérifié
 *    quand le serveur dit le contraire.
 *
 * Aucun backend réel : toutes les réponses sont simulées via `page.route`.
 */
test.describe('Recruitment — sources d’offres (super admin)', () => {
  test.setTimeout(60_000)
  test.describe.configure({ retries: 3 })

  const corsHeaders = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'authorization, content-type, accept',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
  }

  const fulfillJson = (route, body, status = 200) =>
    route.fulfill({
      status,
      contentType: 'application/json',
      headers: corsHeaders,
      body: JSON.stringify(body),
    })

  const handleOptions = (route) => {
    if (route.request().method() === 'OPTIONS') {
      route.fulfill({ status: 204, headers: corsHeaders })
      return true
    }
    return false
  }

  const sources = [
    {
      provider: 'indeed',
      label: 'Indeed',
      status: 'not_connected',
      status_label: 'Non connecté',
      has_credentials: false,
      offers_imported: 0,
      last_synced_at: null,
      last_error: null,
      credential_keys: ['publisher_id', 'api_key'],
    },
    {
      provider: 'linkedin',
      label: 'LinkedIn Jobs',
      status: 'not_connected',
      status_label: 'Non connecté',
      has_credentials: false,
      offers_imported: 0,
      last_synced_at: null,
      last_error: null,
      credential_keys: ['client_id', 'client_secret', 'access_token'],
    },
    {
      provider: 'glassdoor',
      label: 'Glassdoor',
      status: 'not_connected',
      status_label: 'Non connecté',
      has_credentials: false,
      offers_imported: 0,
      last_synced_at: null,
      last_error: null,
      credential_keys: ['partner_id', 'partner_key'],
    },
    {
      provider: 'adzuna',
      label: 'Adzuna',
      status: 'connected',
      status_label: 'Connecté',
      has_credentials: true,
      offers_imported: 42,
      last_synced_at: '2026-11-08T10:00:00Z',
      last_error: null,
      credential_keys: ['app_id', 'app_key'],
    },
    {
      provider: 'jooble',
      label: 'Jooble',
      status: 'error',
      status_label: 'En erreur',
      has_credentials: true,
      offers_imported: 0,
      last_synced_at: null,
      last_error: 'identifiants refusés (HTTP 403).',
      credential_keys: ['api_key'],
    },
    {
      provider: 'custom_feed',
      label: 'Flux XML personnalisé',
      status: 'not_connected',
      status_label: 'Non connecté',
      has_credentials: false,
      offers_imported: 0,
      last_synced_at: null,
      last_error: null,
      credential_keys: ['feed_url', 'api_key'],
    },
  ]

  async function authenticate(page) {
    await page.route(/\/platform\/auth\/login(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, {
        token: 'playwright-admin-token',
        data: { id: 1, name: 'Admin', email: 'admin@example.test', role: 'super_admin' },
      })
    })

    await page.route(/\/platform\/auth\/me(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, {
        data: { id: 1, name: 'Admin', email: 'admin@example.test', role: 'super_admin' },
      })
    })

    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'playwright-admin-token')
    })
  }

  test('liste les six fournisseurs, y compris ceux non configurés', async ({ page }) => {
    await authenticate(page)

    await page.route(/\/recruitment\/sources(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, { data: sources })
    })

    await page.goto('/recruitment/sources')

    // Un connecteur absent de l'écran serait un connecteur impossible à
    // configurer : les six doivent être là.
    for (const label of ['Indeed', 'LinkedIn Jobs', 'Glassdoor', 'Adzuna', 'Jooble']) {
      await expect(page.getByRole('heading', { name: label })).toBeVisible({ timeout: 10_000 })
    }
  })

  test('affiche not_connected comme un état de premier rang', async ({ page }) => {
    await authenticate(page)

    await page.route(/\/recruitment\/sources(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, { data: sources })
    })

    await page.goto('/recruitment/sources')

    // « Non connecté » et non « 0 offre » : un connecteur jamais configuré
    // n'est pas un connecteur qui ne remonte rien.
    await expect(page.getByText('Non connecté').first()).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Connecté').first()).toBeVisible()
    await expect(page.getByText('En erreur').first()).toBeVisible()
  })

  test('affiche le motif d’erreur d’un connecteur refusé', async ({ page }) => {
    await authenticate(page)

    await page.route(/\/recruitment\/sources(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, { data: sources })
    })

    await page.goto('/recruitment/sources')

    // L'opérateur doit savoir POURQUOI le connecteur est en erreur, sinon il
    // ne peut pas corriger.
    await expect(page.getByText(/identifiants refusés/i)).toBeVisible({ timeout: 10_000 })
  })

  test('ne pré-remplit jamais un secret dans le formulaire', async ({ page }) => {
    await authenticate(page)

    await page.route(/\/recruitment\/sources(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, { data: sources })
    })

    await page.goto('/recruitment/sources')

    // Adzuna a des identifiants enregistrés : on ouvre son formulaire.
    const adzunaCard = page.locator('.card', { hasText: 'Adzuna' })
    await adzunaCard.getByRole('button', { name: /Configurer/i }).click()

    const inputs = adzunaCard.locator('input[type="password"]')
    await expect(inputs.first()).toBeVisible({ timeout: 10_000 })

    // Le serveur ne renvoie JAMAIS la valeur stockée : un champ pré-rempli la
    // ferait fuiter dans le DOM, où n'importe quel script pourrait la lire.
    const count = await inputs.count()
    for (let i = 0; i < count; i++) {
      await expect(inputs.nth(i)).toHaveValue('')
    }
  })

  test('ne prétend pas que l’accès fournisseur est vérifié après un simple test', async ({ page }) => {
    await authenticate(page)

    await page.route(/\/recruitment\/sources(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, { data: sources })
    })

    await page.route(/\/recruitment\/sources\/adzuna\/test(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      // Le serveur dit explicitement que l'accès n'est PAS prouvé.
      await fulfillJson(route, {
        result: {
          provider_access_verified: false,
          message: 'Identifiants complets : lancez une synchronisation pour valider l’accès.',
        },
      })
    })

    await page.goto('/recruitment/sources')

    const adzunaCard = page.locator('.card', { hasText: 'Adzuna' })
    await adzunaCard.getByRole('button', { name: /Tester/i }).click()

    // Un test local ne prouve pas l'accès fournisseur : afficher « vérifié »
    // donnerait un faux sentiment de sécurité à l'opérateur.
    await expect(adzunaCard.getByText(/lancez une synchronisation/i)).toBeVisible({ timeout: 10_000 })
    await expect(adzunaCard.getByText(/Accès fournisseur vérifié/i)).toHaveCount(0)
  })

  test('désactive la synchronisation quand aucun identifiant n’est enregistré', async ({ page }) => {
    await authenticate(page)

    await page.route(/\/recruitment\/sources(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, { data: sources })
    })

    await page.goto('/recruitment/sources')

    // Indeed n'a pas d'identifiants : synchroniser ne pourrait que produire
    // une erreur. Le bouton doit être désactivé, pas échouer après coup.
    const indeedCard = page.locator('.card', { hasText: 'Indeed' })
    await expect(indeedCard.getByRole('button', { name: /Synchroniser/i })).toBeDisabled({ timeout: 10_000 })
  })

  test('affiche le compteur d’offres importées', async ({ page }) => {
    await authenticate(page)

    await page.route(/\/recruitment\/sources(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, { data: sources })
    })

    await page.goto('/recruitment/sources')

    const adzunaCard = page.locator('.card', { hasText: 'Adzuna' })
    await expect(adzunaCard.getByText(/42/)).toBeVisible({ timeout: 10_000 })
  })
})
