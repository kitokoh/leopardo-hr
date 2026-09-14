/**
 * cookie-scope.ts — utilitaires de portée des cookies relayés par le proxy web.
 *
 * QA onboarding 2026-09-14 : le state anti-CSRF du flux Google vit dans la
 * session de l'API, qui doit revenir au callback pour être validée. Or l'API
 * émet son `Set-Cookie` avec SON domaine : relayé tel quel par le proxy
 * same-origin de la vitrine, le navigateur le refuse (domaine étranger) → la
 * session était perdue et tout retour de Google finissait en
 * `INVALID_OAUTH_STATE`.
 *
 * Ces helpers sont volontairement hors `app/` et `modules/` : ce sont des
 * constantes techniques (aucun texte utilisateur), et les gardes i18n
 * PA2-I18N-014 n'ont pas à les analyser.
 */

/**
 * Récrit un `Set-Cookie` de l'API pour qu'il appartienne à l'origine qui répond
 * (la vitrine) : suppression de `Domain`, `Path=/`, `SameSite=Lax`.
 * Les attributs de sécurité (HttpOnly, Secure, Max-Age) sont conservés.
 *
 * @param cookie  valeur brute d'un en-tête Set-Cookie du backend
 * @param isHttps la réponse est-elle servie en HTTPS (sinon `Secure` est retiré,
 *                un navigateur refusant un cookie Secure en clair)
 */
export function rescopeSessionCookie(cookie: string, isHttps: boolean): string {
  const kept: string[] = [];

  for (const part of cookie.split(';')) {
    const trimmed = part.trim();
    if (trimmed === '') continue;

    const attribute = trimmed.split('=')[0].trim().toLowerCase();
    if (['domain', 'path', 'samesite'].includes(attribute)) continue;
    if (attribute === 'secure' && !isHttps) continue;

    kept.push(trimmed);
  }

  kept.push('Path=/');
  kept.push('SameSite=Lax');

  return kept.join('; ');
}
