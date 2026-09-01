/**
 * @jest-environment node
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Issue #6679 (P0) — le middleware de garde du dashboard doit accepter le
 * format de token Sanctum posé dans le cookie de session :
 * `id|secret` (ex. `1001|HVs0OH…`) et sa forme URL-encodée `%7C`.
 *
 * La garde est cosmétique (#3522 — la vraie auth est serveur), mais un
 * faux négatif ici boucle l'utilisateur vers /auth/login après un login
 * réussi.
 */
describe('session token format vs middleware guard (#6679)', () => {
  it('le middleware accepte le format Sanctum id|secret et sa forme encodée %7C', async () => {
    // Test comportemental (pattern middleware-session-token.test.ts #6726) :
    // on exécute le middleware avec le cookie au format réel posé par
    // login/route.ts, au lieu d'introspecter le source (fragile).
    const { NextRequest } = await import('next/server');
    const { middleware } = await import('@/middleware');

    for (const token of ['1001|HVs0OHabcdefghijklmnopqrstuvwxyz', '1001%7CHVs0OHabcdefghijklmnopqrstuvwxyz', 'abcdefghijklmnopqrstuvwxyz0123456789.-_']) {
      const req = new NextRequest('https://app.example.com/dashboard');
      req.cookies.set('leopardo_token', token);
      const res = middleware(req);
      expect(res.status).toBe(200);
    }
  });

  it('le login pose le token BACKEND BRUT (id|secret) dans le cookie, sans transformation', () => {
    const loginSrc = readFileSync(
      join(__dirname, '../../app/api/v1/auth/login/route.ts'),
      'utf8'
    );
    // Le cookie reçoit directement la valeur `token` de la réponse backend
    // (pas un hash dérivé) — c'est ce qui impose l'acceptation du `|`.
    const cookieSet = loginSrc.match(/cookieStore\.set\([^)]*token[^)]*\)/s);
    expect(cookieSet).not.toBeNull();
  });
});
