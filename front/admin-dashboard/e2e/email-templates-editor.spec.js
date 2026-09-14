import { expect, test } from '@playwright/test'

// #4415 : creds de test via env — jamais de littéral prod dans le dépôt.
const E2E_ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || 'e2e-fixture-password'

const withQuery = (path) => new RegExp(`\\/api\\/v1\\/${path}(?:\\?.*)?$`)
const json = (body) => ({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })

/**
 * #7347 — l'admin peut éditer les e-mails depuis Paramètres › E-mails.
 *
 * Ce que la spec verrouille :
 *   - l'écran charge la liste des modèles et affiche la valeur EFFECTIVE ;
 *   - « Enregistrer » envoie bien (template_key, locale) + les champs ;
 *   - « Revenir au défaut » appelle bien la suppression de la surcharge ;
 *   - « Aperçu » rend le HTML renvoyé par l'API dans une iframe (le vrai layout).
 */
test('Paramètres › E-mails : éditer, réinitialiser et prévisualiser un modèle', async ({ page }) => {
  test.setTimeout(60_000)

  const DEFAULTS = {
    subject: 'Vérifiez votre email',
    heading: 'Vérifiez votre email',
    body: 'Veuillez utiliser le code ci-dessous.',
    cta_label: 'Ouvrir',
    overridden: {},
  }

  const calls = { put: [], delete: [], preview: [] }

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
  for (const path of ['admin/dashboard/stats', 'admin/dashboard/activities', 'admin/dashboard/alerts', 'notifications', 'platform/company-requests', 'platform/metrics/overview']) {
    await page.route(withQuery(path), (route) => route.fulfill(json({ data: [], meta: { total: 0 } })))
  }

  await page.route(withQuery('admin/email-templates/preview'), async (route) => {
    calls.preview.push(route.request().postDataJSON())
    await route.fulfill(json({ data: { html: '<!DOCTYPE html><html dir="ltr"><body>TITRE-APERCU</body></html>' } }))
  })

  await page.route(withQuery('admin/email-templates'), async (route) => {
    const method = route.request().method()
    if (method === 'PUT') {
      const payload = route.request().postDataJSON()
      calls.put.push(payload)
      await route.fulfill(json({ data: { locale: payload.locale, ...DEFAULTS, ...payload, overridden: { subject: true, body: true } } }))
      return
    }
    if (method === 'DELETE') {
      calls.delete.push(route.request().postDataJSON())
      await route.fulfill(json({ data: { locale: 'fr', ...DEFAULTS } }))
      return
    }
    await route.fulfill(json({
      data: [
        { key: 'trial_verification', variables: [':name', ':brand'], locales: { fr: DEFAULTS, en: DEFAULTS, ar: DEFAULTS, tr: DEFAULTS } },
        { key: 'password_reset', variables: [':name', ':brand'], locales: { fr: DEFAULTS, en: DEFAULTS, ar: DEFAULTS, tr: DEFAULTS } },
      ],
      meta: { locales: ['fr', 'en', 'ar', 'tr'], fields: ['subject', 'heading', 'body', 'cta_label'] },
    }))
  })

  await page.goto('/login')
  await page.locator('#email').fill('admin@leopardo-rh.com')
  await page.locator('#password').fill(E2E_ADMIN_PASSWORD)
  await page.getByRole('button', { name: /^Se connecter$/i }).click()
  await expect(page).toHaveURL(/\/$/)

  await page.goto('/settings/emails')

  // 1) la liste des modèles est chargée et la valeur effective est affichée
  const subjectInput = page.locator('input[maxlength="255"]').first()
  await expect(subjectInput).toHaveValue(DEFAULTS.subject)

  // 2) édition + enregistrement
  await subjectInput.fill('Sujet personnalisé')
  await page.getByRole('button', { name: /Enregistrer/i }).click()
  await expect.poll(() => calls.put.length).toBeGreaterThan(0)
  expect(calls.put[0]).toMatchObject({
    template_key: 'trial_verification',
    locale: 'fr',
    subject: 'Sujet personnalisé',
  })

  // 3) retour au défaut
  await page.getByRole('button', { name: /Revenir au défaut/i }).click()
  await expect.poll(() => calls.delete.length).toBeGreaterThan(0)
  expect(calls.delete[0]).toMatchObject({ template_key: 'trial_verification', locale: 'fr' })

  // 4) aperçu rendu dans une iframe (le vrai layout)
  await page.getByRole('button', { name: /Aperçu/i }).click()
  const frame = page.frameLocator('iframe[title]')
  await expect(frame.locator('body')).toContainText('TITRE-APERCU')
  expect(calls.preview.length).toBeGreaterThan(0)
})
