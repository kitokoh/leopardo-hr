import { expect, test } from '@playwright/test'

// PA2-QA-001 — Smoke du portail web Laravel (blade) déployé en staging :
// connexion manager à /login. Cette suite tourne avec
// playwright.staging.config.js (job e2e-admin-dashboard de
// .github/workflows/e2e-staging.yml) — volontairement séparée de la suite
// e2e/ qui cible l'app Vue leopardo-admin-dashboard (testée en local par
// web-ci.yml).

test.describe('Portail blade — connexion manager', () => {
  test('la page de login charge avec une structure accessible', async ({ page }) => {
    await page.goto('/login')

    await expect(
      page.getByRole('heading', { level: 1, name: /Connexion manager/i }),
    ).toBeVisible()

    // Les champs doivent être résolvables par getByLabel : un label sans
    // association (ni for/id, ni aria-label) rendait le champ « sans nom
    // accessible » et cassait toute la suite E2E (régression run
    // #31653999170).
    await expect(page.getByLabel('Email', { exact: true })).toBeVisible()
    await expect(page.getByLabel('Mot de passe', { exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: /Se connecter/i })).toBeVisible()

    // Champs standard : email / password (pas des textbox anonymes)
    await expect(page.getByLabel('Email')).toHaveAttribute('type', 'email')
    await expect(page.getByLabel('Mot de passe')).toHaveAttribute('type', 'password')
  })

  test('les champs ont un label programmatiquement associé (for/id, S-6)', async ({ page }) => {
    await page.goto('/login')

    const email = page.getByLabel('Email')
    const password = page.getByLabel('Mot de passe')

    // getByLabel résout via l'association ARIA : si for/id manque,
    // toBeVisible() échoue — c'est la garde de régression du fix a11y.
    await expect(email).toBeVisible()
    await expect(password).toBeVisible()

    await expect(email).toHaveId('email')
    await expect(password).toHaveId('password')
    await expect(page.locator('label[for="email"]')).toHaveText('Email')
    await expect(page.locator('label[for="password"]')).toHaveText('Mot de passe')
  })

  test('la soumission vide est bloquée par la validation HTML5 (required)', async ({ page }) => {
    await page.goto('/login')

    await expect(page.getByLabel('Email')).toHaveAttribute('required', '')
    await expect(page.getByLabel('Mot de passe')).toHaveAttribute('required', '')
  })

  test('identifiants invalides → erreur visible + câblage ARIA (S-6)', async ({ page }) => {
    await page.goto('/login')

    await page.getByLabel('Email').fill('ci-staging-invalid@leopardo-rh.com')
    await page.getByLabel('Mot de passe').fill('mot-de-passe-inexistant')
    await page.getByRole('button', { name: /Se connecter/i }).click()

    // L'erreur serveur « Identifiants invalides. » (WebAuthController) doit
    // être annoncée aux lecteurs d'écran : role=alert + aria-describedby.
    const error = page.locator('#email-error')
    await expect(error).toBeVisible()
    await expect(error).toHaveText('Identifiants invalides.')
    await expect(error).toHaveAttribute('role', 'alert')

    const email = page.getByLabel('Email')
    await expect(email).toHaveAttribute('aria-invalid', 'true')
    await expect(email).toHaveAttribute('aria-describedby', 'email-error')
  })

  test('le formulaire est navigable au clavier', async ({ page }) => {
    await page.goto('/login')

    const email = page.getByLabel('Email')
    // Le lien « Leopardo RH » du header est le premier élément focusable :
    // on tabule jusqu'à atteindre le champ email (robuste à l'ordre exact).
    for (let i = 0; i < 6; i++) {
      await page.keyboard.press('Tab')
      if (await email.evaluate((el) => el === document.activeElement)) {
        break
      }
    }
    await expect(email).toBeFocused()
  })

  test('aucune trace de débogage (stack) exposée sur la page', async ({ page }) => {
    await page.goto('/login')

    const body = await page.locator('body').textContent()
    expect(body).not.toContain('vendor/laravel')
    expect(body).not.toContain('Stack trace')
    expect(body).not.toContain('SQLSTATE')
  })

  test('la page charge sans erreurs console inattendues', async ({ page }) => {
    const consoleErrors = []
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text())
    })

    await page.goto('/login')
    await page.waitForLoadState('networkidle')

    // Erreurs connues/bénignes filtrées (CDN tiers, favicon) : tout le reste
    // est une régression.
    const unexpected = consoleErrors.filter((text) => {
      const known = ['favicon', 'net::ERR_ABORTED', 'unpkg.com', 'fonts.bunny.net', 'cdn.jsdelivr.net']
      return !known.some((k) => text.includes(k))
    })
    expect(unexpected).toEqual([])
  })
})
