/**
 * Validation de session « gate cosmétique » du middleware dashboard
 * (issue #3522 — la vraie authentification reste côté API).
 *
 * Doit accepter TOUT token effectivement posé dans le cookie httpOnly
 * `leopardo_token` par les route handlers (auth/login, auth/2fa/verify,
 * auth/google/callback, onboarding activation) : ils stockent toujours un
 * token Sanctum Laravel au format `{id}|{plaintext}` (ex. "990|1FVyYn…").
 *
 * Fix #6726 : le regex précédent `/^[A-Za-z0-9._-]+$/` excluait le séparateur
 * « | » → `isValidToken` valait toujours false → redirection en boucle vers
 * /auth/login pour tout utilisateur authentifié (dashboard web inaccessible).
 */
const SESSION_TOKEN_PATTERN = /^[A-Za-z0-9._|-]+$/;

export function isValidSessionToken(token: string | undefined | null): boolean {
  if (typeof token !== 'string' || token === '') {
    return false;
  }

  return token.length >= 20 && SESSION_TOKEN_PATTERN.test(token);
}
