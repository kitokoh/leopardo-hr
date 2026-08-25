import { expect, test } from '@playwright/test';

/**
 * Issue #5433 — E2E portail client des documents partagés.
 *
 * Les appels API sont MOCKÉS via page.route (le backend partages #5357 n'est
 * pas requis pour ce spec) : la page Next.js `/documents/shared/[token]`
 * (issue #5233) est testée de bout en bout côté web — résumé, téléchargement,
 * lien invalide, et en-têtes de sécurité (no-referrer, #5429).
 */

const META_OK = {
  data: {
    number: 'FAC-2026-0042',
    type: 'invoice',
    type_label: 'Facture',
    status: 'sent',
    issue_date: '2026-08-20',
    currency: 'DZD',
    total_ttc: 125000.5,
    expires_at: '2026-09-06T00:00:00+00:00',
  },
};

test('portail : résumé du document partagé + téléchargement (API mockée)', async ({ page }) => {
  await page.route('**/api/v1/accounting/documents/shared/**', async (route) => {
    const url = route.request().url();
    if (url.endsWith('/download')) {
      await route.fulfill({
        status: 200,
        contentType: 'application/pdf',
        body: Buffer.from('%PDF-1.4 mock'),
      });
      return;
    }
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(META_OK) });
  });

  await page.goto('/documents/shared/test-token-0001');

  await expect(page.getByText('FAC-2026-0042')).toBeVisible();
  await expect(page.getByText('Facture')).toBeVisible();
  await expect(page.getByText('Envoyé')).toBeVisible();

  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: /Télécharger/i }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toContain('FAC-2026-0042');
});

test('portail : lien invalide → écran dédié (pas de fuite)', async ({ page }) => {
  await page.route('**/api/v1/accounting/documents/shared/**', async (route) => {
    await route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
  });

  await page.goto('/documents/shared/invalid-token-0000');

  await expect(page.getByText('Lien invalide ou expiré')).toBeVisible();
  await expect(page.getByText('FAC-2026-0042')).toHaveCount(0);
});

test('portail : Referrer-Policy no-referrer sur les URL tokenisées (#5429)', async ({ page }) => {
  await page.route('**/api/v1/accounting/documents/shared/**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(META_OK) });
  });

  const response = await page.goto('/documents/shared/test-token-0002');
  expect(response?.headers()['referrer-policy']).toBe('no-referrer');
});
