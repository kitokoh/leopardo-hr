/**
 * #7297 — la base d'API du navigateur doit être le proxy same-origin
 * (`/api/v1`), en dev comme en production.
 *
 * Le token de session vit dans un cookie httpOnly `leopardo_token`, posé par
 * `src/app/api/v1/auth/login/route.ts` et relu par le proxy
 * `src/app/api/v1/[...path]/route.ts`. Toute base qui pointerait l'API **en
 * direct** court-circuite ces deux routes : le cookie n'est jamais posé et
 * `/auth/me` répond 401 (login impossible en dev, constaté le 2026-09-13).
 *
 * Le test s'exécute sous jsdom (`testEnvironment`), donc `typeof window !==
 * 'undefined'` est vrai : c'est bien le chemin navigateur qui est vérifié.
 * L'ancien code ne renvoyait le proxy qu'en `NODE_ENV === 'production'` et
 * renvoyait `NEXT_PUBLIC_API_URL` en dev — ce test échouait donc avant #7297.
 */
import { resolveApiBaseUrl } from '@/lib/api-client';

describe('resolveApiBaseUrl (#7297)', () => {
  it('renvoie le proxy same-origin dans le navigateur (et non l’API en direct)', () => {
    // Opt-out explicite uniquement.
    expect(process.env.NEXT_PUBLIC_API_DIRECT).not.toBe('true');

    expect(resolveApiBaseUrl()).toBe('/api/v1');
  });

  it('n’expose jamais une base cross-origin par défaut', () => {
    expect(resolveApiBaseUrl()).not.toMatch(/^https?:\/\//);
  });
});
