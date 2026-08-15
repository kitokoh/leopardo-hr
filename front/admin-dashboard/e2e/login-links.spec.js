import { expect, test } from '@playwright/test'

const SUPPORT_EMAIL = 'support@leopardo-rh.com'

// QA 2026-08-15 (#2658) : la surface « Mot de passe oublié » a été retirée de
// LoginView (aucun flux de reset super-admin plateforme n'existe) et les items
// footer « Sécurité » / « Support » ne sont plus des liens mais des libellés
// (spans) — l'ancien spec attendait des <a> inexistants et échouait en
// permanence. Le spec est réaligné sur l'écran réel : contact support affiché,
// libellés footer présents, et aucun lien mort '#' sur la page.
test('login screen shows real support contact and never dead # anchors', async ({ page }) => {
  await page.goto('/login')

  // 1. Le canal de support réel est affiché sur l'écran de connexion (texte,
  // pas un lien mailto — voir LoginView.vue).
  const supportContact = page.getByText(SUPPORT_EMAIL, { exact: false })
  await expect(supportContact.first()).toBeVisible()

  // 2. Les items footer « Sécurité » et « Support » sont présents à l'écran.
  const security = page.getByText('Sécurité', { exact: true })
  await expect(security.first()).toBeVisible()
  const support = page.getByText('Support', { exact: true })
  await expect(support.first()).toBeVisible()

  // 3. Regression guard : aucun lien sur l'écran de connexion ne garde un
  // href '#' mort.
  await expect(page.locator('a[href="#"]')).toHaveCount(0)
})
