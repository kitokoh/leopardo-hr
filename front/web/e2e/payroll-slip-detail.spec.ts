import { expect, test, type Page } from '@playwright/test';

/**
 * Issue #2168 — detail de bulletin accessible depuis la liste paie.
 *
 * Mock API : liste `/pay-slips` (shape consommé par la page), détail
 * `/pay-slips/1` (contrat PaySlipResource : employee + lines + champs
 * dédiés) et `/payroll-runs`. Session seedée en localStorage comme dans
 * client-feature-gates.spec.ts (le token vit dans le cookie httpOnly).
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
};

const slipsList = {
  data: [
    {
      id: 1,
      payroll_run_id: 10,
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
      created_at: '2026-06-30T15:00:00Z',
    },
    {
      id: 2,
      payroll_run_id: 10,
      employee_id: 502,
      employee_name: 'Karim Aouad',
      period: '2026-06',
      period_start: '2026-06-01',
      period_end: '2026-06-30',
      gross_salary: 80000,
      total_deductions: 15000,
      net_salary: 65000,
      currency: 'DZD',
      employer_contributions: 16000,
      total_cost: 96000,
      working_days: 22,
      actual_days_worked: 22,
      overtime_hours: 0,
      status: 'calculated',
      created_at: '2026-06-30T15:00:00Z',
    },
  ],
};

const slipDetail = {
  data: {
    id: 1,
    payroll_run_id: 10,
    employee_id: 501,
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
    employee: {
      id: 501,
      first_name: 'Nadia',
      last_name: 'Kaci',
      email: 'nadia.kaci@techcorp-algerie.dz',
    },
    lines: [
      { id: 1, pay_slip_id: 1, salary_component_id: 11, name: 'Salaire de base', type: 'earning', base_amount: 100000, rate: 1, amount: 100000, order: 1 },
      { id: 2, pay_slip_id: 1, salary_component_id: 12, name: 'CNAS salariale', type: 'deduction', base_amount: 100000, rate: 0.09, amount: 9000, order: 2 },
      { id: 3, pay_slip_id: 1, salary_component_id: 13, name: 'IRG', type: 'deduction', base_amount: 100000, rate: 0.16, amount: 16000, order: 3 },
    ],
    created_at: '2026-06-30T15:00:00Z',
  },
};

async function seedPayrollSession(page: Page) {
  await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });

  // Audit #1699 : le token vit dans le cookie httpOnly `leopardo_token` —
  // le layout dashboard appelle /notifications au mount : sans mock, la
  // requête part vers le backend réel sans cookie → 401 → redirection.
  await page.route('**/api/v1/notifications**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { total: 0 } }),
    });
  });

  await page.route('**/api/v1/payroll-runs', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [] }),
    });
  });

  await page.route('**/api/v1/pay-slips', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(slipsList),
    });
  });

  await page.route('**/api/v1/pay-slips/1', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(slipDetail),
    });
  });

  await page.evaluate((user) => {
    window.localStorage.removeItem('auth_token');
    window.localStorage.setItem('auth_user', JSON.stringify(user));
  }, baseUser);
}

test.describe('Payroll pay slip detail', () => {
  test('list renders, eye button opens the detail modal, close button closes it', async ({ page }) => {
    await seedPayrollSession(page);

    await page.goto('/payroll', { waitUntil: 'domcontentloaded' });

    // 1. La liste des bulletins est rendue (employe + net).
    await expect(page).toHaveURL(/\/payroll$/);
    await expect(page.locator('body')).toContainText('Nadia Kaci');
    await expect(page.locator('body')).toContainText('Karim Aouad');
    await expect(page.locator('body')).toContainText(/75\s*000/);

    // 2. Clic sur le bouton œil (premiere ligne) → modal de detail.
    await page.getByRole('button', { name: /voir detail/i }).first().click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText(/Detail du bulletin/i);
    await expect(dialog).toContainText('Nadia Kaci');
    await expect(dialog).toContainText('2026-06-01 → 2026-06-30');

    // 3. Le modal montre le net (et les champs dedies du detail).
    await expect(dialog).toContainText(/75\s*000/);
    await expect(dialog).toContainText(/100\s*000/);
    await expect(dialog).toContainText('Deductions');
    await expect(dialog).toContainText(/25\s*000/);
    await expect(dialog).toContainText('Charges patronales');
    await expect(dialog).toContainText('Salaire de base');
    await expect(dialog).toContainText('CNAS salariale');

    // Scroll verrouille tant que le modal est ouvert.
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe('hidden');

    // 4. Le bouton de fermeture referme le modal.
    await page.getByRole('button', { name: /fermer/i }).click();
    await expect(dialog).not.toBeVisible();
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).not.toBe('hidden');
  });

  test('detail modal closes with Escape and on backdrop click', async ({ page }) => {
    await seedPayrollSession(page);

    await page.goto('/payroll', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText('Nadia Kaci');

    const dialog = page.getByRole('dialog');

    // Fermeture par touche Echap.
    await page.getByRole('button', { name: /voir detail/i }).first().click();
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();

    // Fermeture par clic sur le fond (backdrop).
    await page.getByRole('button', { name: /voir detail/i }).first().click();
    await expect(dialog).toBeVisible();
    await dialog.click({ position: { x: 8, y: 8 } });
    await expect(dialog).not.toBeVisible();
  });
});
