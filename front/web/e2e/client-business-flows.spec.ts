import { expect, test, type Page } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

/**
 * #4944 — Baseline e2e des parcours métier exposés par le portail web client.
 *
 * La conception impose des parcours métier précis (docs/dossierdeConception/
 * 11_ux_wireframes/13_USER_FLOWS_VALIDES.md FLOW 4/6, 05_REGLES_METIER.md §10.3)
 * : validation paie en 2 étapes + verrouillage, affichage du salaire selon
 * salary_type (fixed/daily/hourly), approbation/refus de congés avec motif
 * obligatoire. Ces fonctionnalités ne sont PAS (encore) exposées par le
 * portail web (elles vivent côté mobile/backend) — ce spec documente le
 * comportement réel actuel (baseline) pour les parcours présents :
 *   - paie : liste + statuts + modal détail + onglet cycles ;
 *   - absences : liste avec type/dates/motif/statut.
 * Les écarts de conception sont tracés dans les issues de suivi #5017 (paie 2 étapes), #5018 (salary_type §10.3), #5019 (refus congé motivé).
 */

const baseUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    employees: true,
    attendance: true,
    absences: true,
    payroll: true,
    reports: true,
  },
  company: {
    id: 'company-1',
    name: 'TechCorp Algerie SARL',
    language: 'fr',
    timezone: 'Africa/Algiers',
    currency: 'DZD',
    features: {
      employees: true,
      attendance: true,
      absences: true,
      payroll: true,
      reports: true,
    },
    metadata: { onboarding_completed: true },
  },
};

const slipsList = {
  data: [
    {
      id: 1,
      payroll_run_id: 10,
      employee_id: 501,
      employee_name: 'Nadia Kaci',
      period: '2026-06',
      gross_salary: 100000,
      total_deductions: 25000,
      net_salary: 75000,
      currency: 'DZD',
      working_days: 22,
      actual_days_worked: 20,
      overtime_hours: 3.5,
      status: 'validated',
    },
    {
      id: 2,
      payroll_run_id: 10,
      employee_id: 502,
      employee_name: 'Karim Aouad',
      period: '2026-06',
      gross_salary: 80000,
      total_deductions: 15000,
      net_salary: 65000,
      currency: 'DZD',
      working_days: 22,
      actual_days_worked: 22,
      overtime_hours: 0,
      status: 'calculated',
    },
  ],
};

const slipDetail = {
  data: {
    id: 1,
    employee_id: 501,
    employee_name: 'Nadia Kaci',
    period: '2026-06',
    period_start: '2026-06-01',
    period_end: '2026-06-30',
    gross_salary: 100000,
    total_deductions: 25000,
    net_salary: 75000,
    currency: 'DZD',
    employer_contributions: 20000,
    total_cost: 120000,
    working_days: 22,
    actual_days_worked: 20,
    overtime_hours: 3.5,
    status: 'validated',
    lines: [
      { label: 'Salaire de base', amount: 100000 },
      { label: 'CNAS', amount: 9000 },
      { label: 'IRG', amount: 16000 },
    ],
  },
};

const payrollRuns = {
  data: [
    { id: 10, period: '2026-06', status: 'completed', total_gross: 180000, total_net: 140000 },
  ],
};

const absences = {
  data: [
    {
      id: 1,
      absence_type: { name: 'Congé payé' },
      start_date: '2026-07-06',
      end_date: '2026-07-10',
      reason: 'Vacances annuelles',
      days_count: 5,
      status: 'approved',
    },
    {
      id: 2,
      absence_type: { name: 'Maladie' },
      start_date: '2026-07-15',
      end_date: '2026-07-16',
      reason: null,
      days_count: 2,
      status: 'pending',
    },
  ],
};

async function mockCommon(page: Page) {
  await page.route('**/api/v1/notifications**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { unread_count: 0 } }) })
  );
  await page.evaluate((user) => {
    window.localStorage.setItem('auth_token', 'business-flows-token');
    window.localStorage.setItem('auth_user', JSON.stringify(user));
  }, baseUser);
  await setSessionCookie(page);
}

test.describe('Parcours métier du portail web (baseline #4944)', () => {
  test('paie : liste des bulletins, statuts, onglet cycles et modal détail', async ({ page }) => {
    await mockCommon(page);
    await page.route('**/api/v1/pay-slips', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(slipsList) })
    );
    await page.route('**/api/v1/pay-slips/1', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(slipDetail) })
    );
    await page.route('**/api/v1/payroll-runs', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payrollRuns) })
    );

    await page.goto('/payroll', { waitUntil: 'domcontentloaded' });

    // Liste : statuts localisés (validé / brouillon) et employés.
    await expect(page.locator('body')).toContainText('Nadia Kaci');
    await expect(page.locator('body')).toContainText('Karim Aouad');
    await expect(page.getByText('Validé').first()).toBeVisible();

    // Modal détail : brut/net/compliance (contrat PaySlipResource).
    await page.getByRole('button', { name: 'Voir detail' }).first().click();
    await expect(page.locator('body')).toContainText('Salaire de base');
    await expect(page.locator('body')).toContainText('100000');

    // Onglet cycles : statut completed.
    await page.getByRole('button', { name: 'Cycles de paie' }).click();
    await expect(page.locator('body')).toContainText('Termine');
  });

  test('absences : liste avec type, dates, motif et statut', async ({ page }) => {
    await mockCommon(page);
    await page.route('**/api/v1/absences', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(absences) })
    );

    await page.goto('/absences', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('body')).toContainText('Congé payé');
    await expect(page.locator('body')).toContainText('Vacances annuelles');
    await expect(page.locator('body')).toContainText('approved');
    await expect(page.locator('body')).toContainText('Maladie');
    // Absence sans motif : pas de crash, statut pending affiché.
    await expect(page.locator('body')).toContainText('pending');
  });

  test('paie : montants brut/net affichés (baseline §10.3 — décomposition par salary_type absente du web)', async ({ page }) => {
    await mockCommon(page);
    await page.route('**/api/v1/pay-slips', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(slipsList) })
    );
    await page.route('**/api/v1/payroll-runs', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payrollRuns) })
    );

    await page.goto('/payroll', { waitUntil: 'domcontentloaded' });

    // Baseline actuelle : brut et net par bulletin sont affichés.
    await expect(page.locator('body')).toContainText('100000');
    await expect(page.locator('body')).toContainText('75000');
    // Écart conception #5018 : la décomposition §10.3 (Taux journalier : X —
    // Ce mois : Y jours × X = Z) n'est PAS exposée par le portail web.
  });
});
