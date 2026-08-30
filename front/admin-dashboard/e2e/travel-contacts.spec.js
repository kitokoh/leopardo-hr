import { expect, test } from '@playwright/test'

/**
 * E2E BC-24 TRAVEL — écrans admin « Contacts » et formulaire de contact
 * (TRAVEL-912, #6417) : registre des contacts, consentements par canal,
 * notification manuelle (422 sans canal consenti), formulaire → accusé.
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

const CONTACTS = [
  {
    id: 2,
    first_name: 'Bruno',
    last_name: 'Mba',
    email: 'bruno@example.com',
    phone: '+237611111111',
    email_consent_given: false,
    email_consent_at: null,
    sms_consent_given: true,
    sms_consent_at: '2026-08-28T09:00:00+00:00',
    whatsapp_consent_given: false,
    whatsapp_consent_at: null,
    created_at: '2026-08-30T08:00:00+00:00',
  },
  {
    id: 1,
    first_name: 'Aline',
    last_name: 'Ngo',
    email: 'aline@example.com',
    phone: '+237699999999',
    email_consent_given: true,
    email_consent_at: '2026-08-29T10:00:00+00:00',
    sms_consent_given: false,
    sms_consent_at: null,
    whatsapp_consent_given: false,
    whatsapp_consent_at: null,
    created_at: '2026-08-29T10:00:00+00:00',
  },
]

async function mockContactsApi(page, { notifyStatus = 200, contactStatus = 202 } = {}) {
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
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/contacts(\?.*)?$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: CONTACTS }) }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/contacts\/2\/consent$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/contacts\/2\/notify$/, (route) => {
    if (notifyStatus === 422) {
      return route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Aucun canal configuré avec consentement pour ce contact.' }),
      })
    }
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { channels: ['email'] } }) })
  })
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/contact$/, (route) =>
    route.fulfill({
      status: contactStatus,
      contentType: 'application/json',
      body: JSON.stringify({ status: 'received' }),
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

test.describe('TravelAgency — Contacts (TRAVEL-912)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('onglet Contacts : registre avec consentements par canal', async ({ page }) => {
    await loginViaToken(page)
    await mockContactsApi(page)
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contacts/i }).click()
    await expect(page.getByText('Aline Ngo')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Bruno Mba')).toBeVisible()
    await expect(page.getByText('aline@example.com')).toBeVisible()
  })

  test('notification manuelle : 422 sans canal consenti affiché honnêtement', async ({ page }) => {
    await loginViaToken(page)
    await mockContactsApi(page, { notifyStatus: 422 })
    await page.goto('/travel')

    await page.getByRole('tab', { name: /Contacts/i }).click()
    const row = page.locator('tr', { hasText: 'Bruno Mba' })
    await row.getByRole('button', { name: /Notifier/i }).click()
    await expect(page.getByRole('dialog')).toBeVisible({ timeout: 15_000 })
    await page.getByLabel(/Message/i).fill('Votre trajet est confirmé.')
    await page.getByRole('button', { name: /Envoyer/i }).click()
    await expect(page.getByText(/Aucun canal configuré avec consentement/i)).toBeVisible({
      timeout: 15_000,
    })
  })

  test('formulaire de contact : soumission → accusé de réception', async ({ page }) => {
    await loginViaToken(page)
    await mockContactsApi(page)
    await page.goto('/travel/contact-form')

    await expect(page.getByRole('heading', { name: /Nous contacter/i })).toBeVisible({
      timeout: 15_000,
    })
    await page.getByLabel(/Email/i).fill('visiteur@example.com')
    await page.getByLabel(/Message/i).fill('Je souhaite des informations sur la ligne Douala-Yaoundé.')
    await page.getByLabel(/J'accepte d'être contacté/i).check()
    await page.getByRole('button', { name: /Envoyer la demande/i }).click()
    await expect(page.getByRole('heading', { name: /Demande bien reçue/i })).toBeVisible({
      timeout: 15_000,
    })
  })
})
