import { expect, test } from '@playwright/test'

const SUPPORT_EMAIL = 'support@leopardo-rh.com'
// docs/security/ has no README.md — link to the directory listing instead of a
// possibly-missing blob URL (see issue #2169).
const SECURITY_DOCS_URL = 'https://github.com/kitokoh/leopardo-hr/tree/main/docs/security'

test('login screen links point to real destinations, never dead # anchors', async ({ page }) => {
  await page.goto('/login')

  // 1. "Mot de passe oublié ?" — no super-admin platform reset flow exists,
  // so the link is a mailto to the support team (keeps the styling).
  const forgotPassword = page.getByRole('link', { name: /Mot de passe/i })
  await expect(forgotPassword).toBeVisible()
  const forgotHref = await forgotPassword.getAttribute('href')
  expect(forgotHref).not.toBe('#')
  expect(forgotHref).toContain(`mailto:${SUPPORT_EMAIL}`)
  expect(forgotHref).toContain('subject=')

  // 2. Footer "Sécurité" — GitHub security docs directory (external link).
  const security = page.getByRole('link', { name: 'Sécurité' })
  await expect(security).toBeVisible()
  await expect(security).toHaveAttribute('href', SECURITY_DOCS_URL)

  // 3. Footer "Support" — mailto to the real product support channel
  // (same address as front/web/src/modules/vitrine/lib/constants.ts).
  const support = page.getByRole('link', { name: 'Support', exact: true })
  await expect(support).toBeVisible()
  await expect(support).toHaveAttribute('href', `mailto:${SUPPORT_EMAIL}`)

  // Regression guard: no link anywhere on the login screen keeps a dead "#" href.
  await expect(page.locator('a[href="#"]')).toHaveCount(0)
})
