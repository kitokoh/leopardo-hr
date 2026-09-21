/**
 * Issue #8022 (tranches 1 + 3) — la CSP est ENFORCE, avec nonce par requête,
 * émise par le proxy (fini le `script-src 'unsafe-inline'` statique de
 * #7980). Pattern et tests calqués sur `proxy-csp.test.ts` de front/web
 * (#7650). Ces tests verrouillent :
 *   1. le header `Content-Security-Policy` (enforce) posé par le proxy ;
 *   2. le nonce : présent, propagé à Next via les en-têtes de REQUÊTE
 *      (`content-security-policy` + `x-nonce`), UNIQUE par requête ;
 *   3. script-src sans `'unsafe-inline'` (le point central de l'issue) ;
 *   4. connect-src paramétré par `NEXT_PUBLIC_API_URL`, avec repli dev/test
 *      sur `http://localhost:8000` quand elle est absente (tranche 3) et
 *      fail-fast explicite au build / au runtime de production (#7963) ;
 *   5. le levier de rollback `CSP_REPORT_ONLY=true`.
 */
import { PHASE_PRODUCTION_BUILD } from "next/constants";
import { NextRequest } from "next/server";
import { afterEach, describe, expect, it, vi } from "vitest";

import {
  buildCspDirectives,
  cspHeaderName,
  generateCspNonce,
  resolveApiOrigin,
} from "@/lib/csp";
import { proxy } from "@/proxy";

const ORIGINAL_ENV = { ...process.env };

afterEach(() => {
  process.env = { ...ORIGINAL_ENV };
  vi.unstubAllEnvs();
});

function request(path = "/trips"): NextRequest {
  return new NextRequest(`https://travel.example.com${path}`);
}

function scriptSrcOf(csp: string): string {
  return csp.split(";").find((d) => d.trim().startsWith("script-src")) ?? "";
}

function connectSrcOf(csp: string): string {
  return csp.split(";").find((d) => d.trim().startsWith("connect-src")) ?? "";
}

describe("CSP enforce + nonce par requête (#8022)", () => {
  describe("① header émis par le proxy", () => {
    it("pose Content-Security-Policy (ENFORCE, pas Report-Only) sur une route servie", () => {
      process.env.NEXT_PUBLIC_API_URL = "https://api.example.com/api/v1";
      const res = proxy(request());
      const csp = res.headers.get("content-security-policy");
      expect(csp).toBeTruthy();
      expect(res.headers.get("content-security-policy-report-only")).toBeNull();
      expect(csp).toContain("default-src 'self'");
      expect(csp).toContain("object-src 'none'");
      expect(csp).toContain("frame-ancestors 'none'");
    });
  });

  describe("② nonce par requête", () => {
    it("script-src porte un nonce et strict-dynamic", () => {
      process.env.NEXT_PUBLIC_API_URL = "https://api.example.com/api/v1";
      const csp = proxy(request()).headers.get("content-security-policy") ?? "";
      const scriptSrc = scriptSrcOf(csp);
      expect(scriptSrc).toMatch(/'nonce-[A-Za-z0-9+/=]+'/);
      expect(scriptSrc).toContain("'strict-dynamic'");
    });

    it("propage le nonce à Next via les en-têtes de REQUÊTE (x-nonce + content-security-policy)", () => {
      process.env.NEXT_PUBLIC_API_URL = "https://api.example.com/api/v1";
      // NextResponse.next({ request }) matérialise la réécriture d'en-têtes
      // dans x-middleware-override-headers / x-middleware-request-*.
      const res = proxy(request());
      const overridden = res.headers.get("x-middleware-override-headers") ?? "";
      expect(overridden).toContain("x-nonce");
      expect(overridden).toContain("content-security-policy");
      const nonce = res.headers.get("x-middleware-request-x-nonce");
      expect(nonce).toBeTruthy();
      expect(res.headers.get("x-middleware-request-content-security-policy")).toContain(
        `'nonce-${nonce}'`,
      );
      expect(res.headers.get("content-security-policy")).toContain(`'nonce-${nonce}'`);
    });

    it("le nonce est UNIQUE par requête (jamais rejouable)", () => {
      process.env.NEXT_PUBLIC_API_URL = "https://api.example.com/api/v1";
      const first = proxy(request()).headers.get("x-middleware-request-x-nonce");
      const second = proxy(request()).headers.get("x-middleware-request-x-nonce");
      expect(first).toBeTruthy();
      expect(second).toBeTruthy();
      expect(first).not.toBe(second);
    });

    it("generateCspNonce produit 128 bits en base64, uniques", () => {
      const a = generateCspNonce();
      const b = generateCspNonce();
      expect(Buffer.from(a, "base64")).toHaveLength(16);
      expect(a).not.toBe(b);
    });
  });

  describe("③ plus d'unsafe-inline dans script-src (cœur de l'issue)", () => {
    it("script-src ne contient PAS 'unsafe-inline' (style-src le garde — Tailwind)", () => {
      process.env.NEXT_PUBLIC_API_URL = "https://api.example.com/api/v1";
      const csp = proxy(request()).headers.get("content-security-policy") ?? "";
      const directives = Object.fromEntries(
        csp.split(";").map((d) => {
          const [name, ...rest] = d.trim().split(" ");
          return [name, rest.join(" ")];
        }),
      );
      expect(directives["script-src"]).not.toContain("'unsafe-inline'");
      expect(directives["style-src"]).toContain("'unsafe-inline'");
    });

    it("'unsafe-eval' et les websockets HMR restent confinés au dev", () => {
      process.env.NEXT_PUBLIC_API_URL = "https://api.example.com/api/v1";
      const prod = buildCspDirectives({ nonce: "n", isDev: false });
      expect(scriptSrcOf(prod)).not.toContain("'unsafe-eval'");
      expect(connectSrcOf(prod)).not.toContain("ws:");
      const dev = buildCspDirectives({ nonce: "n", isDev: true });
      expect(scriptSrcOf(dev)).toContain("'unsafe-eval'");
      expect(connectSrcOf(dev)).toContain("ws:");
      expect(connectSrcOf(dev)).toContain("wss:");
    });
  });

  describe("④ connect-src par environnement (#7963 + tranche 3)", () => {
    it("utilise l'origine de NEXT_PUBLIC_API_URL quand elle est définie", () => {
      process.env.NEXT_PUBLIC_API_URL = "https://api.example.com/api/v1";
      expect(resolveApiOrigin()).toBe("https://api.example.com");
      const csp = buildCspDirectives({ nonce: "n" });
      expect(connectSrcOf(csp)).toContain("https://api.example.com");
    });

    it("rejoue le repli dev/test sur http://localhost:8000 quand elle est absente (tranche 3)", () => {
      delete process.env.NEXT_PUBLIC_API_URL;
      delete process.env.NEXT_PHASE;
      // NODE_ENV=test (vitest) : ni build, ni runtime de production.
      expect(resolveApiOrigin()).toBe("http://localhost:8000");
      const csp = buildCspDirectives({ nonce: "n" });
      expect(connectSrcOf(csp)).toContain("http://localhost:8000");
    });

    it("échoue explicitement pendant `next build` quand elle est absente (#7963)", () => {
      delete process.env.NEXT_PUBLIC_API_URL;
      process.env.NEXT_PHASE = PHASE_PRODUCTION_BUILD;
      expect(() => resolveApiOrigin()).toThrow(/next build/);
    });

    it("échoue explicitement au runtime de production quand elle est absente", () => {
      delete process.env.NEXT_PUBLIC_API_URL;
      delete process.env.NEXT_PHASE;
      vi.stubEnv("NODE_ENV", "production");
      expect(() => resolveApiOrigin()).toThrow(/en production/);
    });
  });

  describe("⑤ levier de rollback CSP_REPORT_ONLY", () => {
    it("rebascule en Report-Only quand CSP_REPORT_ONLY=true", () => {
      process.env.NEXT_PUBLIC_API_URL = "https://api.example.com/api/v1";
      process.env.CSP_REPORT_ONLY = "true";
      expect(cspHeaderName()).toBe("Content-Security-Policy-Report-Only");
      const res = proxy(request());
      expect(
        res.headers.get("content-security-policy-report-only"),
      ).toBeTruthy();
      expect(res.headers.get("content-security-policy")).toBeNull();
    });
  });
});
