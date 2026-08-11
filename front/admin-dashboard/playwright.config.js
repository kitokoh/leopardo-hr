import { defineConfig } from '@playwright/test'

// PA2-QA-001 — Smoke login across the 5 surfaces (API, employee/manager
// mobile, platform admin, admin web, kiosk).
//
// .github/workflows/e2e-staging.yml runs this suite against the real
// deployed staging URL by exporting BASE_URL (see the front/web project's
// own convention in front/web/playwright.config.ts). This config used to
// only read PLAYWRIGHT_BASE_URL, so the "staging" admin-dashboard smoke job
// silently ignored BASE_URL, always fell back to 127.0.0.1:4173, and spun up
// a fresh local dev server instead of testing staging — the CI job passed
// without ever exercising the real deployed admin login flow.
const baseURL = process.env.BASE_URL || process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:4173'
const shouldStartLocalServer = !process.env.BASE_URL && !process.env.PLAYWRIGHT_BASE_URL

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  // Issue #1725 : les expect gardaient 5s par defaut alors que le cold start
  // Render free tier peut prendre 30-60s (le warm-up du workflow e2e-staging.yml
  // reveille l'instance avant la suite, mais un delai supplementaire absorbe
  // les lenteurs residuelles de premier rendu).
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
    baseURL,
    headless: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  webServer: shouldStartLocalServer
    ? {
        command: 'npm run dev -- --host 127.0.0.1 --port 4173',
        url: 'http://127.0.0.1:4173/login',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      }
    : undefined,
})
