import { test, expect } from '@playwright/test';

/**
 * Security Headers E2E — Vitrine (issue #1607)
 *
 * Vérifie que la vitrine Next.js envoie les headers de sécurité communs
 * ainsi que la Content-Security-Policy (Report-Only, décision datée
 * 2026-08-09 dans next.config.ts — voir issue #1607).
 *
 * Si la décision évolue vers le passage en enforce (`Content-Security-Policy`),
 * ce test doit être mis à jour en conséquence (et la vitrine vérifiée :
 * login, checkout, docs).
 */
test.describe('Security Headers', () => {
  test('common security headers are present on the landing page', async ({ request }) => {
    const response = await request.get('/');
    expect(response.ok()).toBeTruthy();

    const headers = response.headers();
    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(headers['x-frame-options']).toBe('DENY');
    expect(headers['strict-transport-security']).toContain('max-age=');
    expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
    expect(headers['permissions-policy']).toBeDefined();
  });

  test('CSP is present (Report-Only, decision #1607)', async ({ request }) => {
    const response = await request.get('/');
    expect(response.ok()).toBeTruthy();

    const headers = response.headers();
    const cspHeader = headers['content-security-policy-report-only'];

    // Décision 2026-08-09 : Report-Only maintenu (enforce après câblage
    // nonce/hash + endpoint de report — voir next.config.ts).
    expect(cspHeader).toBeTruthy();
    expect(cspHeader).toContain("default-src 'self'");
    expect(cspHeader).toContain("object-src 'none'");
    expect(cspHeader).toContain("frame-ancestors 'none'");
    expect(cspHeader).toContain('upgrade-insecure-requests');
    // Le header enforce ne doit PAS être actif tant que la décision est
    // Report-Only (sinon régression analytics/checkout).
    expect(headers['content-security-policy']).toBeUndefined();
  });
});
