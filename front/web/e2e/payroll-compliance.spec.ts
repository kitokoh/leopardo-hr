import { expect, test } from './fixtures/authenticated';
import type { Page } from '@playwright/test';

/**
 * Issue #2116 — la Web App affiche le badge de conformité par niveau de
 * confiance sur les bulletins (`/pay-slips`) quand le payload expose le bloc
 * `compliance` (contrat #1872) : niveau localisé, avertissement, source.
 * Rétro-compatible : pas de badge quand le bloc est absent (aucune erreur).
 */

const complianceSlips = [
  {
    id: 1,
    payroll_run_id: 11,
    employee_id: 501,
    employee_name: 'Nadia Kaci',
    period: '2026-05',
    country_code: 'CI',
    gross_salary: 400000,
    net_salary: 298000,
    status: 'validated',
    compliance: {
      level: 'pilot',
      warning: 'Règles pilotes pour CI : montants issus de références publiques générales.',
      warning_key: 'payroll.compliance_warning_pilot',
      source: 'docs/payroll/CI_COMPLIANCE.md',
      verification_date: null,
    },
  },
  {
    id: 2,
    payroll_run_id: 12,
    employee_id: 502,
    employee_name: 'Karim Aouad',
    period: '2026-05',
    country_code: 'TG',
    gross_salary: 150000,
    net_salary: 120000,
    status: 'validated',
    compliance: {
      level: 'placeholder',
      warning: 'Maquette sans valeurs pour TG.',
      warning_key: 'payroll.compliance_warning_placeholder',
      source: 'docs/payroll/TG_COMPLIANCE.md',
      verification_date: null,
    },
  },
  {
    id: 3,
    payroll_run_id: 13,
    employee_id: 503,
    employee_name: 'Salima Bensalem',
    period: '2026-05',
    country_code: 'FR',
    gross_salary: 5000,
    net_salary: 3700,
    status: 'draft',
    compliance: {
      level: 'production',
      warning: 'Règles validées et utilisées en production pour FR.',
      warning_key: 'payroll.compliance_warning_production',
      source: 'docs/payroll/FR_COMPLIANCE.md',
      verification_date: '2026-07-01',
    },
  },
  {
    // Rétro-compatibilité : pas de bloc compliance → aucun badge, pas d'erreur.
    id: 4,
    payroll_run_id: 14,
    employee_id: 504,
    employee_name: 'Yacine Merbah',
    period: '2026-05',
    gross_salary: 90000,
    net_salary: 82000,
    status: 'validated',
  },
];

async function mockPayrollApis(page: Page) {
  await page.route('**/api/v1/pay-slips**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: complianceSlips,
        meta: { total: complianceSlips.length },
      }),
    });
  });

  await page.route('**/api/v1/payroll-runs**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          { id: 11, period: '2026-05', status: 'completed', total_gross: 400000, total_net: 298000, employee_count: 3, created_at: '2026-05-31T00:00:00Z' },
        ],
        meta: { total: 1 },
      }),
    });
  });

}

test.describe('Client web — conformité paie par niveau de confiance (#2116)', () => {
  test('manager sees localized compliance badges per confidence level on pay slips', async ({ authenticatedPage: page }) => {
    await mockPayrollApis(page);


    await page.goto('/payroll', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/payroll$/);

    // Colonne conformité présente (clé localisée FR).
    await expect(page.locator('body')).toContainText('Conformité');

    // Badge « pilot » (CI) — niveau localisé + source en sous-texte.
    const pilotRow = page.locator('tr', { hasText: 'Nadia Kaci' });
    await expect(pilotRow).toContainText('Pilote');
    await expect(pilotRow).toContainText('CI_COMPLIANCE.md');

    // Badge « placeholder » (TG).
    const placeholderRow = page.locator('tr', { hasText: 'Karim Aouad' });
    await expect(placeholderRow).toContainText('Maquette');

    // Badge « production » (FR) — date de vérification en sous-texte.
    const productionRow = page.locator('tr', { hasText: 'Salima Bensalem' });
    await expect(productionRow).toContainText('Production');
    await expect(productionRow).toContainText('2026-07-01');

    // Rétro-compatibilité : pas de bloc compliance → aucun badge, aucune erreur.
    const legacyRow = page.locator('tr', { hasText: 'Yacine Merbah' });
    await expect(legacyRow).not.toContainText('Pilote');
    await expect(legacyRow).not.toContainText('Production');
  });
});
