import { defineConfig, devices } from '@playwright/test';
import baseConfig from './playwright.config';

/**
 * Issue #5146 — config dédiée de la suite E2E funnel prospect.
 *
 * Cible staging/prod uniquement (jamais de serveur local) : le funnel a
 * besoin de la VRAIE API (signup trial, provisioning, OTP, employés).
 * Exécutée par le workflow `e2e-staging.yml` (job `e2e-web-vitrine`) :
 *
 *   npx playwright test e2e/funnel.spec.ts \
 *     --project=chromium --config=playwright.staging-funnel.config.ts
 *
 * Écarts volontaires par rapport à `playwright.config.ts` (suite locale
 * mockée) :
 *   - `expect.timeout` 15 s : FR-4 de la spec #5146 (≥ 15 s) — la prod free
 *     tier Render a des latences de cold start résiduelles même après le
 *     warm-up du workflow (#1725).
 *   - `timeout` 240 s par défaut (les tests à poll US1/US3 le relèvent à
 *     300 s via test.setTimeout) : GET /trial/status est pollé jusqu'à 180 s
 *     (tolérance CI 3 min, cible produit < 2 min).
 *   - `globalTimeout` 20 min : la suite complète (3 signups × poll) tient
 *     dans 45 min de job (timeout-minutes du workflow).
 *   - `retries: 0` : `/trial/signup` est sous `throttle:5,15` (5 signups /
 *     15 min / IP) — un retry doublerait les signups et 429rait la suite.
 *   - `webServer: undefined` : jamais de serveur local, on teste staging/prod
 *     (BASE_URL requis par le workflow).
 */
export default defineConfig({
  ...baseConfig,
  testDir: './e2e',
  testMatch: /funnel\.spec\.ts/,
  timeout: 240_000,
  globalTimeout: 1_200_000,
  expect: { timeout: 15_000 },
  retries: 0,
  workers: 1,
  reporter: process.env.CI
    ? [
        ['github'],
        ['html', { open: 'never' }],
        ['junit', { outputFile: 'test-results/junit-funnel.xml' }],
      ]
    : 'list',
  webServer: undefined,
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
