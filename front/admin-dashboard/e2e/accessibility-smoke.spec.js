import { expect, test } from '@playwright/test'

test.describe('Accessibility smoke tests', () => {
  test('login page has proper ARIA labels', async ({ page }) => {
    await page.goto('/login')

    // Check for proper heading hierarchy
    const heading = page.getByRole('heading', { level: 1 }).or(
      page.getByRole('heading', { name: /Leopardo/i }),
    )
    await expect(heading.first()).toBeVisible()

    // Email field should have a label
    const emailInput = page.getByLabel(/Adresse email/i).or(
      page.getByLabel(/email/i),
    )
    await expect(emailInput.first()).toBeVisible()

    // Submit button should be keyboard-focusable
    const submitButton = page.getByRole('button', { name: /Se connecter/i })
    await submitButton.focus()
    await expect(submitButton).toBeFocused()
  })

  test('login page is keyboard navigable', async ({ page }) => {
    await page.goto('/login')

    // Tab through the form
    await page.keyboard.press('Tab')
    await page.keyboard.press('Tab')
    await page.keyboard.press('Tab')

    // At least one interactive element should be focused
    const focused = page.locator(':focus')
    await expect(focused).toBeVisible()
  })

  test('login page has no missing alt text on images', async ({ page }) => {
    await page.goto('/login')

    const images = page.locator('img')
    const count = await images.count()
    for (let i = 0; i < count; i++) {
      const img = images.nth(i)
      const alt = await img.getAttribute('alt')
      const role = await img.getAttribute('role')
      // Each image should have alt text or role="presentation"
      expect(alt !== null || role === 'presentation').toBe(true)
    }
  })

  test('login page exposes inline field errors with ARIA wiring (S-6 #1666)', async ({ page }) => {
    await page.goto('/login')

    // Submit the empty form → inline errors must appear with aria-invalid +
    // aria-describedby + role="alert" (WCAG W6).
    const submitButton = page.getByRole('button', { name: /Se connecter/i })
    await submitButton.click()

    const emailInput = page.getByLabel(/Adresse email/i)
    const passwordInput = page.getByLabel(/Clé d'Accès/i)

    await expect(emailInput).toHaveAttribute('aria-invalid', 'true')
    await expect(passwordInput).toHaveAttribute('aria-invalid', 'true')

    const describedByEmail = await emailInput.getAttribute('aria-describedby')
    expect(describedByEmail).toBeTruthy()
    const describedByPassword = await passwordInput.getAttribute('aria-describedby')
    expect(describedByPassword).toBeTruthy()

    // The error message nodes exist, are associated and use role="alert"
    if (describedByEmail) {
      await expect(page.locator(`#${describedByEmail}`)).toBeVisible()
      await expect(page.locator(`#${describedByEmail}`)).toHaveAttribute('role', 'alert')
    }
    if (describedByPassword) {
      await expect(page.locator(`#${describedByPassword}`)).toBeVisible()
      await expect(page.locator(`#${describedByPassword}`)).toHaveAttribute('role', 'alert')
    }
  })
})
