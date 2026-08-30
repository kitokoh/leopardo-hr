import { expect, test } from '@playwright/test'

/**
 * BC-22-D12 (issue #6244) — golden journey Analytics & Reporting.
 *
 * Parcours UI « ouvrir le dashboard comptable → lire les agrégats → exporter
 * les impayés » sur données synthétiques déterministes (mocks API en ligne) :
 *   - états loading / data / empty / error vérifiés (pas de crash) ;
 *   - agrégats rendus cohérents avec le jeu de données mocké ;
 *   - export CSV déclenché sur l'endpoint canonique ;
 *   - la garde UI n'est pas autoritaire : sans session → redirection /login
 *     (le RBAC API reste la source de vérité, testé côté backend).
 *
 * E2E isolé : aucun appel réseau réel (toutes les routes API sont mockées),
 * exécuté par web-ci.yml (front/admin-dashboard/**).
 */

const AUTH_ME_URL = /\/api\/v1\/platform\/auth\/me$/
const DASHBOARD_URL = /\/api\/v1\/accounting\/dashboard\?.*$/
const EXPORT_URL = /\/api\/v1\/accounting\/dashboard\/export(?:\?.*)?$/

const USER = {
  data: {
    id: 1,
    name: 'Comptable Pilote',
    email: 'comptable@analytics-pilot-001.leopardo.test',
    role: 'admin',
  },
}

// Agrégats déterministes alignés sur le vocabulaire du glossaire BC-22
// (docs/architecture/ANALYTICS_GLOSSARY.md).
const DASHBOARD = {
  data: {
    period: { from: '2026-08-01', to: '2026-08-30' },
    invoices: { count: 7, total_ttc: 1234500.0 },
    collections: { count: 2, total: 305000.0 },
    expenses: { count: 1, total_ttc: 89000.0 },
    outstanding: {
      count: 2,
      total_due: 392000.0,
      aging: [
        { bucket: '0_30', count: 1, total_due: 213500.0 },
        { bucket: '31_60', count: 1, total_due: 178500.0 },
        { bucket: '61_90', count: 0, total_due: 0.0 },
        { bucket: '90_plus', count: 0, total_due: 0.0 },
      ],
      list: [
        {
          id: 1,
          number: 'FAC-2026-0002',
          contact: 'EPE Distribution Nord',
          issue_date: '2026-08-10',
          due_date: '2026-08-25',
          days_late: 5,
          total_ttc: 305000.0,
          paid_amount: 91500.0,
          due_amount: 213500.0,
          status: 'partially_paid',
        },
        {
          id: 2,
          number: 'DEV-2026-0001',
          contact: 'ETS Horizon Services',
          issue_date: '2026-07-16',
          due_date: null,
          days_late: 0,
          total_ttc: 178500.0,
          paid_amount: 0,
          due_amount: 178500.0,
          status: 'sent',
        },
      ],
    },
    snapshot: { source: 'live' },
  },
}

const EMPTY_DASHBOARD = {
  data: {
    period: { from: '2026-08-01', to: '2026-08-30' },
    invoices: { count: 0, total_ttc: 0 },
    collections: { count: 0, total: 0 },
    expenses: { count: 0, total_ttc: 0 },
    outstanding: { count: 0, total_due: 0, aging: [], list: [] },
    snapshot: { source: 'live' },
  },
}

const CSV_EXPORT =
  'number,contact,issue_date,due_date,days_late,total_ttc,paid_amount,due_amount,status\n' +
  'FAC-2026-0002,EPE Distribution Nord,2026-08-10,2026-08-25,5,305000,91500,213500,partially_paid\n' +
  'DEV-2026-0001,ETS Horizon Services,2026-07-16,,0,178500,0,178500,sent\n'

function mockAuth(page) {
  return page.route(AUTH_ME_URL, (route) => {
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(USER) })
  })
}

function mockDashboard(page, payload = DASHBOARD, delayMs = 0) {
  return page.route(DASHBOARD_URL, async (route) => {
    if (delayMs > 0) {
      await new Promise((resolve) => setTimeout(resolve, delayMs))
    }
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payload) })
  })
}

function mockExport(page, csv = CSV_EXPORT) {
  return page.route(EXPORT_URL, (route) => {
    route.fulfill({
      status: 200,
      contentType: 'text/csv; charset=UTF-8',
      headers: {
        'content-disposition': 'attachment; filename="accounting-dashboard-outstanding-2026-08-01_2026-08-30.csv"',
      },
      body: csv,
    })
  })
}

function signIn(page) {
  return page.addInitScript(() => sessionStorage.setItem('admin_token', 'pilote-token'))
}

test.describe('Golden journey Analytics — dashboard comptable', () => {
  test('sans session : redirection /login (garde UI non autoritaire)', async ({ page }) => {
    await page.goto('/accounting/dashboard')
    await expect(page).toHaveURL(/\/login/)
  })

  test('état loading puis agrégats déterministes rendus', async ({ page }) => {
    await mockAuth(page)
    await mockDashboard(page, DASHBOARD, 600)
    await mockExport(page)
    await signIn(page)

    await page.goto('/accounting/dashboard')

    // Loading explicite pendant la réponse différée.
    await expect(page.getByText('Chargement…')).toBeVisible()

    // Agrégats rendus, cohérents avec le jeu mocké (glossaire BC-22) :
    // montants formatés fr-FR + lignes d'impayés.
    await expect(page.getByText('Tableau de bord comptable')).toBeVisible()
    await expect(page.getByText('1 234 500,00')).toBeVisible()
    await expect(page.getByText('305 000,00')).toBeVisible()
    await expect(page.getByText('89 000,00')).toBeVisible()
    await expect(page.getByText('FAC-2026-0002')).toBeVisible()
    await expect(page.getByText('EPE Distribution Nord')).toBeVisible()
  })

  test('état vide : message explicite, aucun crash', async ({ page }) => {
    await mockAuth(page)
    await mockDashboard(page, EMPTY_DASHBOARD)
    await mockExport(page)
    await signIn(page)

    await page.goto('/accounting/dashboard')

    await expect(page.getByText('Aucune facture sur la période.')).toBeVisible()
  })

  test('état erreur : toast explicite, la page reste utilisable', async ({ page }) => {
    await mockAuth(page)
    await page.route(DASHBOARD_URL, (route) =>
      route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Erreur serveur' }) }),
    )
    await mockExport(page)
    await signIn(page)

    await page.goto('/accounting/dashboard')

    await expect(page.getByText('Erreur serveur')).toBeVisible()
    // Pas de crash : le chargement s'arrête et la page reste affichée.
    await expect(page.getByText('Tableau de bord comptable')).toBeVisible()
    await expect(page.getByText('Chargement…')).not.toBeVisible()
  })

  test('export CSV impayés : appel à l’endpoint canonique et téléchargement', async ({ page }) => {
    await mockAuth(page)
    await mockDashboard(page)
    await mockExport(page)
    await signIn(page)

    const exportRequest = page.waitForRequest((request) => request.url().includes('/api/v1/accounting/dashboard/export'))
    const downloadPromise = page.waitForEvent('download')

    await page.goto('/accounting/dashboard')
    await page.getByRole('button', { name: 'Exporter' }).click()

    const request = await exportRequest
    expect(request.method()).toBe('GET')
    expect(request.url()).toContain('/api/v1/accounting/dashboard/export')

    const download = await downloadPromise
    expect(download.suggestedFilename()).toContain('accounting-dashboard-outstanding')
  })
})
