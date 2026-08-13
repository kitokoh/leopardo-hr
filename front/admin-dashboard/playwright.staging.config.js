import { defineConfig } from '@playwright/test'

// Suite E2E dédiée au portail web Laravel (blade) réellement déployé en
// staging/production sur le domaine de l'API (DEFAULT_STAGING_URL du workflow
// e2e-staging.yml). Le portail sert : /login (connexion manager),
// /platform/login (connexion super admin), /docs + /api-explorer +
// /tester-guide (documentation de l'API pour l'administration et les clients).
//
// Volontairement SÉPARÉE de la suite `e2e/` (app Vue leopardo-admin-dashboard,
// jouée en local par web-ci.yml) : les deux surfaces ont des marqueurs UI
// différents, et lancer les specs Vue contre la page blade faisait échouer le
// job e2e-admin-dashboard du staging (run #31653999170, main 2026-08-13).
if (!process.env.BASE_URL && !process.env.PLAYWRIGHT_BASE_URL) {
  throw new Error(
    'playwright.staging.config.js requires BASE_URL (URL du portail blade staging/prod).',
  )
}

export default defineConfig({
  testDir: './e2e-staging',
  timeout: 30_000,
  // Issue #1725 : le cold-start Render free tier peut prendre 30-60 s ; le
  // warm-up du workflow e2e-staging.yml réveille l'instance avant la suite,
  // et ce délai supplémentaire absorbe les lenteurs résiduelles.
  expect: { timeout: 15_000 },
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI
    ? [
        ['github'],
        ['html', { open: 'never' }],
        ['junit', { outputFile: 'test-results/junit.xml' }],
      ]
    : 'list',
  use: {
    baseURL: process.env.BASE_URL || process.env.PLAYWRIGHT_BASE_URL,
    headless: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
})
