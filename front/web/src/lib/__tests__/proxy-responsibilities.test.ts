/**
 * @jest-environment node
 */
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

import { NextRequest } from 'next/server';

import { proxy } from '@/proxy';

/**
 * Issue #7305 — migration Next 16 `middleware` → `proxy`.
 *
 * Next 16 déprécie la convention de fichier : au build comme au démarrage on
 * lisait `⚠ The "middleware" file convention is deprecated. Please use "proxy"
 * instead.` Le fichier `front/web/src/middleware.ts` portait trois
 * responsabilités **critiques** : ce test les verrouille une par une, ainsi que
 * la convention elle-même (export `proxy`, plus aucun `middleware.ts`), pour
 * que la migration ne puisse pas « faire taire un avertissement » en cassant un
 * comportement :
 *   1. gate d'auth de la zone dashboard (`/dashboard`, `/payroll`, …) ;
 *   2. `/signup` sans offre souscriptible → `/pricing#plans` ;
 *   3. normalisation de la locale vitrine → en-tête `x-vitrine-lang`.
 */
describe('proxy — convention Next 16 + responsabilités préservées (#7305)', () => {
  const base = 'https://app.example.com';
  const sessionCookie = '990|1FVyYnVzSbMu8F1OCOtk';

  function request(
    path: string,
    { token, acceptLanguage }: { token?: string; acceptLanguage?: string } = {},
  ): NextRequest {
    const req = new NextRequest(`${base}${path}`, {
      headers: acceptLanguage ? { 'accept-language': acceptLanguage } : undefined,
    });
    if (token) req.cookies.set('leopardo_token', token);
    return req;
  }

  describe('① convention de fichier (Next 16)', () => {
    it("exporte une fonction `proxy` et plus de fonction `middleware`", async () => {
      const mod = await import('@/proxy');
      expect(typeof mod.proxy).toBe('function');
      expect((mod as Record<string, unknown>).middleware).toBeUndefined();
    });

    it("n'expose plus `src/middleware.ts` (convention dépréciée) mais bien `src/proxy.ts`", () => {
      expect(existsSync(join(__dirname, '../../middleware.ts'))).toBe(false);
      expect(existsSync(join(__dirname, '../../proxy.ts'))).toBe(true);
    });

    it("ne déclare aucun segment `runtime` (un proxy s'exécute toujours sur Node.js)", () => {
      const src = readFileSync(join(__dirname, '../../proxy.ts'), 'utf8');
      // `export const runtime = …` est refusé par Next dans un proxy
      // (E1031 : « Route segment config is not allowed in Proxy file »).
      expect(src).not.toMatch(/export\s+const\s+runtime/);
      expect(src).toMatch(/export\s+const\s+config\s*=/);
    });
  });

  describe("② gate d'auth de la zone dashboard", () => {
    it('redirige vers /auth/login une requête anonyme sur chaque préfixe protégé', () => {
      for (const path of ['/dashboard', '/payroll/2026', '/employees/42', '/settings/security/2fa']) {
        const res = proxy(request(path));
        expect(res.status).toBe(307);
        expect(res.headers.get('location')).toBe(`${base}/auth/login`);
      }
    });

    it('laisse passer une session valide (cookie `leopardo_token`)', () => {
      const res = proxy(request('/dashboard', { token: sessionCookie }));
      expect(res.status).toBe(200);
      expect(res.headers.get('location')).toBeNull();
    });

    it('rejette un cookie de session au format invalide', () => {
      for (const bad of ['short', 'a|b', 'hello world 12345678901234567890']) {
        const res = proxy(request('/dashboard', { token: bad }));
        expect(res.status).toBe(307);
        expect(res.headers.get('location')).toBe(`${base}/auth/login`);
      }
    });

    it('ne bloque pas la vitrine (hors zone dashboard)', () => {
      const res = proxy(request('/pricing'));
      expect(res.status).toBe(200);
    });
  });

  describe('③ /signup sans offre souscriptible → /pricing#plans', () => {
    it.each(['free', 'pilot', 'operations', 'enterprise'])(
      'conserve /signup?plan=%s (offre souscriptible)',
      (plan) => {
        const res = proxy(request(`/signup?plan=${plan}`));
        expect(res.status).toBe(200);
        expect(res.headers.get('location')).toBeNull();
      },
    );

    it.each(['/signup', '/signup?plan=pro', '/signup?plan=', '/signup?source=navbar'])(
      'renvoie %s vers /pricing#plans',
      (path) => {
        const res = proxy(request(path));
        expect(res.status).toBe(307);
        // ⚠️ FRAGMENT et non query : un `?from=signup` faisait échouer le
        // prefetch Next (e2e marketing-funnel, timeout 90 s) — cf. #7238.
        expect(res.headers.get('location')).toBe(`${base}/pricing#plans`);
      },
    );
  });

  describe('④ normalisation de la locale vitrine (`?lang=`)', () => {
    it('propage `?lang=` dans `x-vitrine-lang`', () => {
      for (const [lang, expected] of [['en', 'en'], ['tr', 'tr'], ['ar', 'ar'], ['fr', 'fr']]) {
        const res = proxy(request(`/pricing?lang=${lang}`));
        expect(res.status).toBe(200);
        expect(res.headers.get('x-vitrine-lang')).toBe(expected);
      }
    });

    it('retombe sur Accept-Language normalisé quand `?lang=` est absent (#4393)', () => {
      expect(proxy(request('/pricing', { acceptLanguage: 'tr-TR,tr;q=0.9' })).headers.get('x-vitrine-lang')).toBe('tr');
      expect(proxy(request('/pricing', { acceptLanguage: 'de-DE,de;q=0.8' })).headers.get('x-vitrine-lang')).toBe('fr');
    });

    it('ignore un `?lang=` non supporté et suit Accept-Language', () => {
      const res = proxy(request('/pricing?lang=zz', { acceptLanguage: 'en-US,en;q=0.9' }));
      expect(res.headers.get('x-vitrine-lang')).toBe('en');
    });

    it("n'écrase pas la redirection /signup par l'en-tête de locale", () => {
      // L'ordre des responsabilités compte : la redirection « pas d'offre »
      // passe AVANT la pose de `x-vitrine-lang` (une redirection n'est pas une
      // réponse `next()`, elle ne porte pas l'en-tête).
      const res = proxy(request('/signup?lang=en'));
      expect(res.status).toBe(307);
      expect(res.headers.get('x-vitrine-lang')).toBeNull();
    });
  });

  describe('responsabilité annexe préservée (split /restaurant ↔ /restaurateur)', () => {
    it('renvoie un visiteur anonyme de `/restaurant` vers la vitrine', () => {
      const res = proxy(request('/restaurant'));
      expect(res.status).toBe(307);
      expect(res.headers.get('location')).toBe(`${base}/restaurateur`);
    });

    it('sert `/restaurant` à une session valide et protège toujours les sous-routes', () => {
      expect(proxy(request('/restaurant', { token: sessionCookie })).status).toBe(200);
      const sub = proxy(request('/restaurant/kitchen'));
      expect(sub.status).toBe(307);
      expect(sub.headers.get('location')).toBe(`${base}/auth/login`);
    });
  });
});
