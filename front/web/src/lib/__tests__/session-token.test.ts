import { isValidSessionTokenShape } from '@/lib/session-token';

/**
 * Issue #6726 — la garde de forme du token de session (middleware Next.js)
 * doit accepter les tokens Sanctum `{id}|{plaintext}` : le regex historique
 * excluait le `|` → tout utilisateur authentifié était redirigé en boucle
 * vers /auth/login (dashboard web mort en prod).
 */
describe('isValidSessionTokenShape (#6726)', () => {
  it('accepte un vrai token Sanctum id|plain (format prod)', () => {
    expect(isValidSessionTokenShape('990|1FVyYnVzSbMu8F1OCOtkXyZ1234567890ab')).toBe(true);
  });

  it('accepte la forme legacy simple (sans pipe)', () => {
    expect(isValidSessionTokenShape('1FVyYnVzSbMu8F1OCOtkXyZ1234567890ab')).toBe(true);
  });

  it('rejette les tokens trop courts', () => {
    expect(isValidSessionTokenShape('short')).toBe(false);
    expect(isValidSessionTokenShape('12|short')).toBe(false);
  });

  it('rejette l’absence de token', () => {
    expect(isValidSessionTokenShape(undefined)).toBe(false);
    expect(isValidSessionTokenShape(null)).toBe(false);
    expect(isValidSessionTokenShape('')).toBe(false);
  });

  it('rejette les valeurs manifestement invalides (gate cosmétique #3522)', () => {
    expect(isValidSessionTokenShape('ééééééééééééééééééééééééé')).toBe(false);
    expect(isValidSessionTokenShape('$$$$$$$$$$$$$$$$$$$$$$$')).toBe(false);
  });
});
