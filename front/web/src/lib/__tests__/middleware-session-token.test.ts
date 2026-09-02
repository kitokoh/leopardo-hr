/**
 * @jest-environment node
 */
import { NextRequest } from 'next/server';

import { middleware } from '@/middleware';

/**
 * Issue #6726 — le gate cosmétique du middleware doit accepter le cookie de
 * session Sanctum `{id}|{plaintext}` posé par `app/api/v1/auth/login/route.ts`.
 * Avant le fix, le regex excluait le séparateur `|` → toute la zone dashboard
 * redirigeait en boucle vers /auth/login en production.
 */
describe('middleware — gate de session zone dashboard (#6726)', () => {
  const base = 'https://app.example.com';

  function request(path: string, token?: string) {
    const req = new NextRequest(`${base}${path}`);
    if (token) req.cookies.set('leopardo_token', token);
    return req;
  }

  function isRedirectedToLogin(response: Response): boolean {
    return response.status === 307 && response.headers.get('location') === `${base}/auth/login`;
  }

  it('laisse passer un token Sanctum id|secret sur /dashboard', () => {
    const res = middleware(request('/dashboard', '990|1FVyYnVzSbMu8F1OCOtk'));
    expect(res.status).toBe(200);
    expect(isRedirectedToLogin(res)).toBe(false);
  });

  it('laisse passer un token Sanctum sur chaque préfixe protégé', () => {
    const token = '1234|abcdefghijklmnopqrstuvwxyz';
    for (const prefix of ['/dashboard', '/absences', '/payroll', '/settings', '/social-marketing']) {
      const res = middleware(request(`${prefix}/some/page`, token));
      expect(res.status).toBe(200);
    }
  });

  it('laisse passer un token opaque historique (sans pipe)', () => {
    const res = middleware(request('/dashboard', 'opaque-token-abcdefghijklmnopqrstuvwxyz'));
    expect(res.status).toBe(200);
  });

  it('redirige vers /auth/login sans cookie', () => {
    const res = middleware(request('/dashboard'));
    expect(isRedirectedToLogin(res)).toBe(true);
  });

  it('redirige vers /auth/login pour un token au format invalide', () => {
    for (const bad of ['1234|bad token!', 'hello world 12345678901234567890', 'short', 'a|b', '']) {
      const res = middleware(request('/dashboard', bad));
      expect(isRedirectedToLogin(res)).toBe(true);
    }
  });

  it('ne bloque pas la vitrine (hors zone dashboard)', () => {
    const res = middleware(request('/', '990|1FVyYnVzSbMu8F1OCOtk'));
    expect(res.status).toBe(200);
    expect(res.headers.get('x-vitrine-lang')).toBeTruthy();
  });
});
