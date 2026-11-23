import { expect, test } from '@playwright/test'

/**
 * #9849 / #9851 (BC-14 INTEGRATION) — onglet « Connecteurs », surface SUPERADMIN.
 *
 * Ce spec couvre ce que le backend ne peut pas prouver seul : le FORMULAIRE est
 * réellement généré depuis `field_schema` du catalogue, et un type ajouté au
 * catalogue (ici `stripe_connect`) apparaît dans l'écran sans qu'une ligne de
 * Vue ait été modifiée.
 *
 * Pattern aligné sur `sidebar-unique-entries.spec.js` : session simulée par
 * `sessionStorage.admin_token` + réponses API mockées. Aucun backend n'est
 * requis, donc le spec tourne aussi en CI locale (`web-ci.yml`), qui ne fournit
 * pas `PLAYWRIGHT_AUTH_TOKEN`.
 *
 * Toutes les assertions du formulaire sont cadrées sur `getByRole('dialog')` :
 * l'en-tête de l'application contient lui aussi des `<select>` (sélecteur de
 * langue), et un `page.locator('select').first()` visait le mauvais.
 */

const LIVE = process.env.BACKEND_LIVE === '1'

/**
 * Session plateforme complète : le menu et les routes sont filtrés sur
 * `permissions` (#7553/#7554), pas sur `role`.
 */
const SUPER_ADMIN_USER = {
  id: 1,
  name: 'Agent E2E',
  email: 'agent.e2e@leopardo.test',
  role: 'super_admin',
  platform_role: 'super_admin',
  permissions: [
    'companies.view', 'companies.manage', 'billing.view', 'billing.manage',
    'connectors.view', 'connectors.manage', 'payments.view',
  ],
  language: 'fr',
}

/** Entrée de catalogue telle que l'API la renvoie (`toApi()`). */
const CATALOG = [
  {
    key: 'bank_transfer',
    label: 'Compte bancaire (virement)',
    category: 'bank',
    audience: 'platform',
    fields: [
      { key: 'bank_name', label: 'Nom de la banque', type: 'text', required: true },
      { key: 'account_holder', label: 'Titulaire du compte', type: 'text', required: true },
      { key: 'iban', label: 'IBAN / RIB', type: 'text', secret: true, required: true },
    ],
  },
  {
    key: 'stripe',
    label: 'Stripe',
    category: 'payment',
    audience: 'both',
    fields: [
      { key: 'account_id', label: 'Identifiant de compte (acct_…)', type: 'text', required: false },
      { key: 'secret_key', label: 'Clé secrète', type: 'password', secret: true, required: true },
    ],
  },
  {
    // Le type ajouté par cette mission : s'il n'apparaît pas dans le
    // formulaire, c'est que l'écran code ses champs en dur.
    key: 'stripe_connect',
    label: 'Stripe Connect',
    category: 'payment',
    audience: 'both',
    fields: [
      { key: 'account_id', label: 'Compte connecté (acct_…)', type: 'text', required: true },
      { key: 'client_id', label: 'Client ID Connect (ca_…)', type: 'text', required: false },
      { key: 'secret_key', label: 'Clé secrète de la plateforme', type: 'password', secret: true, required: true },
      { key: 'webhook_secret', label: 'Secret de webhook Connect', type: 'password', secret: true, required: false },
      {
        key: 'business_type',
        label: 'Type de compte connecté',
        type: 'select',
        required: false,
        options: [
          { value: 'company', label: 'Entreprise' },
          { value: 'individual', label: 'Particulier' },
        ],
      },
    ],
  },
]

const CONNECTIONS = [
  {
    id: 7,
    catalog_key: 'stripe_connect',
    label: 'Reversements tenants',
    status: 'active',
    is_default: true,
    config: { account_id: 'acct_1QwertyUIOP', business_type: 'company' },
    // Secrets write-only : l'API ne renvoie qu'un masque.
    secrets: { secret_key: { configured: true, mask: 'sk_live_••••4242' } },
  },
]

const OVERVIEW = {
  integrations: [
    {
      key: 'payment_gateways',
      label: 'Passerelles de paiement (abonnements)',
      category: 'payment',
      href: '/billing/gateways',
      count: 2,
      configured: true,
      detail: 'Stripe et Chargily : encaissement des abonnements de la plateforme.',
    },
  ],
  connections_count: 1,
  active_count: 1,
}

/**
 * Monte la session + les mocks de l'onglet.
 *
 * L'ordre compte : la route générique `**\/api/v1/**` est déclarée en PREMIER,
 * les routes spécifiques ensuite — Playwright évalue les handlers du plus
 * récent au plus ancien, donc les mocks précis gagnent.
 */
async function openConnectors(page) {
  await page.addInitScript(() => {
    sessionStorage.setItem('admin_token', 'e2e-connectors-token')
    // Les libellés de l'écran sont en français : sans ça, le headless
    // (navigator.language = en-US) rendrait l'anglais.
    localStorage.setItem('admin_locale', 'fr')
  })

  await page.route('**/api/v1/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: SUPER_ADMIN_USER }) }),
  )
  await page.route(/\/api\/v1\/platform\/connectors\/catalog(\?.*)?$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: CATALOG } }) }),
  )
  await page.route(/\/api\/v1\/platform\/connectors\/connections(\?.*)?$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: CONNECTIONS } }) }),
  )
  await page.route(/\/api\/v1\/platform\/connectors\/overview(\?.*)?$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: OVERVIEW }) }),
  )

  await page.goto('/connectors')
}

/** Ouvre le formulaire d'ajout et rend le modal. */
async function openForm(page) {
  await page.getByRole('button', { name: /Ajouter un connecteur/i }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  return dialog
}

test.describe('Onglet Connecteurs — Superadmin (#9849)', () => {
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('la page liste les connexions de la plateforme et les intégrations existantes', async ({ page }) => {
    await openConnectors(page)

    await expect(page.getByRole('heading', { name: /^Connecteurs$/i }).first()).toBeVisible({ timeout: 15_000 })

    // La connexion existante est affichée avec son type lisible et son état.
    await expect(page.getByText('Reversements tenants')).toBeVisible()
    await expect(page.getByText('Stripe Connect').first()).toBeVisible()
    await expect(page.getByText('Actif').first()).toBeVisible()

    // Le secret n'est jamais affiché en clair : masque uniquement.
    await expect(page.getByText(/sk_live_••••4242/)).toBeVisible()

    // L'index des intégrations déjà en place est en lecture seule, avec un
    // lien vers l'écran natif (source de vérité).
    await expect(page.getByRole('heading', { name: /Intégrations déjà en place/i })).toBeVisible()
    await expect(page.getByText('Passerelles de paiement (abonnements)')).toBeVisible()
    await expect(page.getByRole('link', { name: /Ouvrir l'écran/i }).first()).toHaveAttribute('href', '/billing/gateways')
  })

  test('le formulaire est généré depuis le schéma du catalogue (Stripe Connect)', async ({ page }) => {
    await openConnectors(page)

    const dialog = await openForm(page)

    // Le type ajouté au catalogue est proposé sans modification du front.
    const typeSelect = dialog.locator('select').first()
    await expect(typeSelect.locator('option', { hasText: 'Stripe Connect' })).toHaveCount(1)

    await typeSelect.selectOption('stripe_connect')

    // Les champs du schéma apparaissent, y compris le `select` fermé.
    await expect(dialog.getByText(/Compte connecté \(acct_/)).toBeVisible()
    await expect(dialog.getByText('Clé secrète de la plateforme')).toBeVisible()
    await expect(dialog.getByText('Secret de webhook Connect')).toBeVisible()
    await expect(dialog.getByText('Type de compte connecté')).toBeVisible()

    // Un champ secret est rendu en `password` : la valeur ne s'affiche pas.
    await expect(dialog.locator('input[type="password"]')).toHaveCount(2)

    // Le `select` du schéma propose exactement les options déclarées.
    const businessType = dialog.locator('select').nth(1)
    await expect(businessType.locator('option', { hasText: 'Entreprise' })).toHaveCount(1)
    await expect(businessType.locator('option', { hasText: 'Particulier' })).toHaveCount(1)
  })

  test('changer de type réinitialise les champs du formulaire', async ({ page }) => {
    await openConnectors(page)

    const dialog = await openForm(page)
    const typeSelect = dialog.locator('select').first()

    await typeSelect.selectOption('stripe_connect')
    await expect(dialog.getByText(/Compte connecté \(acct_/)).toBeVisible()

    // Passer à un autre type ne doit pas laisser les champs du précédent :
    // sinon on enverrait `account_id` à un connecteur bancaire.
    await typeSelect.selectOption('bank_transfer')
    await expect(dialog.getByText('Nom de la banque')).toBeVisible()
    await expect(dialog.getByText(/Compte connecté \(acct_/)).toHaveCount(0)
  })
})
