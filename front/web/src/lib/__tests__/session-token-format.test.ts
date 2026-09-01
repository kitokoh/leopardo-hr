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
  const middlewareSrc = readFileSync(join(__dirname, '../../middleware.ts'), 'utf8');

  it('le regex du middleware accepte le pipe (format Sanctum id|secret) et sa forme encodée', () => {
    const regexMatch = middlewareSrc.match(
      /isValidToken\s*=\s*[\s\S]*?\/\^(\[A-Za-z0-9._|%-\]+)\\\/\.test/
    );
    expect(regexMatch).not.toBeNull();
    const pattern = regexMatch?.[1] ?? '';
    const regex = new RegExp(`^${pattern}+$`);

    // Format réel posé par login/route.ts (token Sanctum brut).
    expect(regex.test('1001|HVs0OHabcdefghijklmnopqrstuvwxyz')).toBe(true);
    // Forme URL-encodée (cookie encodé par le navigateur) : `%7C`.
    expect(regex.test('1001%7CHVs0OHabcdefghijklmnopqrstuvwxyz')).toBe(true);
    // Les tokens ordinaires restent acceptés.
    expect(regex.test('abcdefghijklmnopqrstuvwxyz0123456789.-_')).toBe(true);
  });

  it('le login pose le token BACKEND BRUT (id|secret) dans le cookie, sans transformation', () => {
    const loginSrc = readFileSync(
      join(__dirname, '../../app/api/v1/auth/login/route.ts'),
      'utf8'
    );
    // Le cookie reçoit directement la valeur `token` de la réponse backend
    // (pas un hash dérivé) — c'est ce qui impose l'acceptation du `|`.
    const cookieSet = loginSrc.match(/cookies\.set\([^)]*token[^)]*\)/s);
    expect(cookieSet).not.toBeNull();
  });
});
