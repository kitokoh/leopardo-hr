/**
 * @jest-environment node
 */
import { NextRequest } from 'next/server';

import { buildCspDirectives, cspHeaderName, generateCspNonce, resolveApiOrigin } from '@/lib/csp';
import { proxy } from '@/proxy';

/**
 * Issue #7650 — la CSP est ENFORCE, avec nonce par requête, émise par le
 * proxy (fini le Report-Only permanent de #1300/#1607 et le connect-src
 * hardcodé sur l'API dev dans vercel.json). Ces tests verrouillent :
 *   1. le header `Content-Security-Policy` (enforce) posé par le proxy ;
 *   2. le nonce : présent, propagé à Next via les en-têtes de requête
 *      (`content-security-policy` + `x-nonce`), UNIQUE par requête ;
 *   3. script-src sans `'unsafe-inline'` (le point central de l'audit) ;
 *   4. connect-src paramétré par `NEXT_PUBLIC_API_URL` ;
 *   5. le levier de rollback `CSP_REPORT_ONLY=true`.
 */
describe('CSP enforce + nonce par requête (#7650)', () => {
  const ORIGINAL_ENV = { ...process.env };

  afterEach(() => {
    process.env = { ...ORIGINAL_ENV };
  });

  function request(path = '/pricing'): NextRequest {
    return new NextRequest(`https://app.example.com${path}`);
  }

  describe('① header émis par le proxy', () => {
    it('pose Content-Security-Policy (ENFORCE, pas Report-Only) sur une route servie', () => {
      const res = proxy(request());
      const csp = res.headers.get('content-security-policy');
      expect(csp).toBeTruthy();
      expect(res.headers.get('content-security-policy-report-only')).toBeNull();
      expect(csp).toContain("default-src 'self'");
      expect(csp).toContain("object-src 'none'");
      expect(csp).toContain("frame-ancestors 'none'");
      expect(csp).toContain('upgrade-insecure-requests');
    });

    it('conserve la responsabilité historique x-vitrine-lang (#4004)', () => {
      const res = proxy(request('/pricing?lang=tr'));
      expect(res.headers.get('x-vitrine-lang')).toBe('tr');
      expect(res.headers.get('content-security-policy')).toBeTruthy();
    });
  });

  describe('② nonce par requête', () => {
    it('script-src porte un nonce et strict-dynamic', () => {
      const csp = proxy(request()).headers.get('content-security-policy') ?? '';
      const scriptSrc = csp.split(';').find((d) => d.trim().startsWith('script-src')) ?? '';
      expect(scriptSrc).toMatch(/'nonce-[A-Za-z0-9+/=]+'/);
      expect(scriptSrc).toContain("'strict-dynamic'");
    });

    it('propage le nonce à Next via les en-têtes de REQUÊTE (x-nonce + content-security-policy)', () => {
      // NextResponse.next({ request }) matérialise la réécriture d'en-têtes
      // dans x-middleware-override-headers / x-middleware-request-*.
      const res = proxy(request());
      const overridden = res.headers.get('x-middleware-override-headers') ?? '';
      expect(overridden).toContain('x-nonce');
      expect(overridden).toContain('content-security-policy');
      const nonce = res.headers.get('x-middleware-request-x-nonce');
      expect(nonce).toBeTruthy();
      expect(res.headers.get('x-middleware-request-content-security-policy')).toContain(
        `'nonce-${nonce}'`,
      );
      expect(res.headers.get('content-security-policy')).toContain(`'nonce-${nonce}'`);
    });

    it('le nonce est UNIQUE par requête (jamais rejouable)', () => {
      const first = proxy(request()).headers.get('x-middleware-request-x-nonce');
      const second = proxy(request()).headers.get('x-middleware-request-x-nonce');
      expect(first).toBeTruthy();
      expect(second).toBeTruthy();
      expect(first).not.toBe(second);
    });

    it('generateCspNonce produit 128 bits en base64, uniques', () => {
      const a = generateCspNonce();
      const b = generateCspNonce();
      expect(Buffer.from(a, 'base64')).toHaveLength(16);
      expect(a).not.toBe(b);
    });
  });

  describe("③ plus d'unsafe-inline dans script-src (cœur de l'audit)", () => {
    it("script-src ne contient PAS 'unsafe-inline' (style-src le garde — Tailwind/framer-motion)", () => {
      const csp = proxy(request()).headers.get('content-security-policy') ?? '';
      const directives = Object.fromEntries(
        csp.split(';').map((d) => {
          const [name, ...rest] = d.trim().split(' ');
          return [name, rest.join(' ')];
        }),
      );
      expect(directives['script-src']).not.toContain("'unsafe-inline'");
      expect(directives['style-src']).toContain("'unsafe-inline'");
    });

    it("script-src en production ne contient pas 'unsafe-eval' (réservé à next dev)", () => {
      const prod = buildCspDirectives({ nonce: 'abc', isDev: false });
      const dev = buildCspDirectives({ nonce: 'abc', isDev: true });
      expect(prod).not.toContain("'unsafe-eval'");
      expect(dev).toContain("'unsafe-eval'");
    });
  });

  describe('④ connect-src par environnement (fini le hardcode dev de vercel.json)', () => {
    it("suit NEXT_PUBLIC_API_URL quand la variable est posée", () => {
      process.env.NEXT_PUBLIC_API_URL = 'https://api.leopardo-rh.com/api/v1';
      expect(resolveApiOrigin()).toBe('https://api.leopardo-rh.com');
      const csp = buildCspDirectives({ nonce: 'abc', isDev: false });
      expect(csp).toContain('connect-src');
      expect(csp).toContain('https://api.leopardo-rh.com');
      expect(csp).not.toContain('onrender.com');
    });

    it('retombe sur le backend LOCAL UNIQUEMENT sans variable (poste local sans .env, jamais en production — #7842/#7963)', () => {
      delete process.env.NEXT_PUBLIC_API_URL;
      delete process.env.VERCEL_ENV;
      expect(resolveApiOrigin()).toBe('http://localhost:8000');
    });

    it('une valeur invalide ne casse pas la construction de la politique en dev/test', () => {
      process.env.NEXT_PUBLIC_API_URL = 'not-a-url';
      delete process.env.VERCEL_ENV;
      expect(resolveApiOrigin()).toBe('http://localhost:8000');
    });

    it('fail-fast #7842 : en production, NEXT_PUBLIC_API_URL absente → erreur actionnable (plus de repli silencieux)', () => {
      delete process.env.NEXT_PUBLIC_API_URL;
      process.env.VERCEL_ENV = 'production';
      expect(() => resolveApiOrigin()).toThrow(/NEXT_PUBLIC_API_URL/);
      expect(() => resolveApiOrigin()).toThrow(/#7842/);
    });

    it('fail-fast #7842 : en production, NEXT_PUBLIC_API_URL invalide → erreur actionnable', () => {
      process.env.NEXT_PUBLIC_API_URL = 'not-a-url';
      process.env.VERCEL_ENV = 'production';
      expect(() => resolveApiOrigin()).toThrow(/NEXT_PUBLIC_API_URL/);
    });

    it('pendant `next build` (NEXT_PHASE=phase-production-build) sans variable : erreur explicite (#7963 — les workflows CI posent NEXT_PUBLIC_API_URL)', () => {
      delete process.env.NEXT_PUBLIC_API_URL;
      process.env.VERCEL_ENV = 'production';
      process.env.NEXT_PHASE = 'phase-production-build';
      expect(() => resolveApiOrigin()).toThrow(/NEXT_PUBLIC_API_URL/);
      expect(() => resolveApiOrigin()).toThrow(/#7963/);
    });
  });

  describe('⑤ rollback opérationnel CSP_REPORT_ONLY', () => {
    it('CSP_REPORT_ONLY=true rebascule le header en Report-Only sans changer les directives', () => {
      process.env.CSP_REPORT_ONLY = 'true';
      expect(cspHeaderName()).toBe('Content-Security-Policy-Report-Only');
      const res = proxy(request());
      expect(res.headers.get('content-security-policy')).toBeNull();
      expect(res.headers.get('content-security-policy-report-only')).toContain("default-src 'self'");
    });

    it('toute autre valeur reste en enforce (état sûr par défaut)', () => {
      delete process.env.CSP_REPORT_ONLY;
      expect(cspHeaderName()).toBe('Content-Security-Policy');
      process.env.CSP_REPORT_ONLY = 'false';
      expect(cspHeaderName()).toBe('Content-Security-Policy');
    });
  });
});
