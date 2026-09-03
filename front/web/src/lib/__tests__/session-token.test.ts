import { isValidSessionToken } from '@/lib/session-token';

/**
 * Régression #6726 : le gate cosmétique du middleware dashboard rejetait les
 * tokens Sanctum (format "{id}|{plaintext}") car le séparateur « | » était
 * exclu du regex → tout utilisateur authentifié était redirigé vers
 * /auth/login (dashboard inaccessible). Un vrai token de session (login web
 * via le proxy Next.js) doit passer le gate.
 */
describe('isValidSessionToken (gate middleware #3522, régression #6726)', () => {
  it.each([
    // Vrais tokens Sanctum (format "{id}|{plaintext}") — cf. login/route.ts
    '990|1FVyYnVzSbMu8F1OCOtk7UvzExampleToken123',
    '1|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789',
    '12345|plainTextTokenWithAtLeastTwentyChars',
  ])('accepte un token Sanctum valide : %s', (token) => {
    expect(isValidSessionToken(token)).toBe(true);
  });

  it('rejette les valeurs non conformes', () => {
    expect(isValidSessionToken(undefined)).toBe(false);
    expect(isValidSessionToken(null)).toBe(false);
    expect(isValidSessionToken('')).toBe(false);
    // trop court
    expect(isValidSessionToken('12|short')).toBe(false);
    // caractères hors jeu (espace, caractères spéciaux…)
    expect(isValidSessionToken('12|token avec espaces interdits')).toBe(false);
    expect(isValidSessionToken('12|token;injection')).toBe(false);
  });
});
