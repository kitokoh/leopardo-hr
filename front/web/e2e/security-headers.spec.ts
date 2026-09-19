import { test, expect } from '@playwright/test';

/**
 * Security Headers E2E — Vitrine (issue #1607, bascule enforce #7650)
 *
 * Vérifie que la vitrine Next.js envoie les headers de sécurité communs
 * ainsi que la Content-Security-Policy en mode ENFORCE avec nonce par
 * requête (audit #7650 — fin du Report-Only permanent décidé le
 * 2026-08-09, la politique vit dans `src/lib/csp.ts` et est émise par
 * `src/proxy.ts`).
 *
 * Rollback opérationnel : `CSP_REPORT_ONLY=true` (env) — si ce levier est
 * activé durablement, ce test doit redévenir rouge : l'état nominal est
 * l'enforce.
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

  test('CSP is ENFORCED with a per-request nonce (audit #7650)', async ({ request }) => {
    const response = await request.get('/');
    expect(response.ok()).toBeTruthy();

    const headers = response.headers();
    const cspHeader = headers['content-security-policy'];

    // #7650 : la CSP bloque désormais réellement (une Report-Only ne bloque
    // rien — c'était le constat central de l'audit).
    expect(cspHeader).toBeTruthy();
    expect(headers['content-security-policy-report-only']).toBeUndefined();

    expect(cspHeader).toContain("default-src 'self'");
    expect(cspHeader).toContain("object-src 'none'");
    expect(cspHeader).toContain("frame-ancestors 'none'");
    expect(cspHeader).toContain('upgrade-insecure-requests');

    // script-src : nonce + strict-dynamic, plus AUCUN 'unsafe-inline'.
    const scriptSrc = cspHeader
      .split(';')
      .find((directive) => directive.trim().startsWith('script-src'));
    expect(scriptSrc).toBeDefined();
    expect(scriptSrc).toMatch(/'nonce-[A-Za-z0-9+/=]+'/);
    expect(scriptSrc).toContain("'strict-dynamic'");
    expect(scriptSrc).not.toContain("'unsafe-inline'");
  });

  test('the nonce is unique per request and stamped on SSR inline scripts', async ({ request }) => {
    const extractNonce = (csp: string | undefined): string | null =>
      csp?.match(/'nonce-([A-Za-z0-9+/=]+)'/)?.[1] ?? null;

    const first = await request.get('/');
    const second = await request.get('/');
    const firstNonce = extractNonce(first.headers()['content-security-policy']);
    const secondNonce = extractNonce(second.headers()['content-security-policy']);

    expect(firstNonce).toBeTruthy();
    expect(secondNonce).toBeTruthy();
    expect(firstNonce).not.toBe(secondNonce);

    // Le HTML rendu doit porter le nonce de SA requête : sans lui, les
    // scripts inline SSR (bootstrap Next, Consent Mode) seraient bloqués.
    const html = await first.text();
    expect(html).toContain(`nonce="${firstNonce}"`);
  });
});
