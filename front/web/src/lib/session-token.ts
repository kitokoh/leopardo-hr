/**
 * Garde de forme « cosmétique » du cookie de session (gate UX du middleware
 * Next.js, issue #3522). Ce n'est PAS une frontière de sécurité : la vraie
 * auth est côté serveur (API). Elle évite seulement de servir le HTML/JS du
 * dashboard à un visiteur manifestement non authentifié.
 *
 * #6726 : les tokens Sanctum ont le format `{id}|{plaintext}` (ex.
 * "990|1FVyYnVzSbMu8F1OCOtk…"). Le regex historique excluait le séparateur
 * `|` → tout utilisateur authentifié était redirigé en boucle vers
 * /auth/login (dashboard web mort en prod).
 */
export function isValidSessionTokenShape(token: string | undefined | null): boolean {
  if (!token || token.length < 20) {
    return false;
  }

  // Forme legacy simple (token sans pipe).
  if (/^[A-Za-z0-9._-]+$/.test(token)) {
    return true;
  }

  // Forme Sanctum : `{id}|{plaintext}` (#6726).
  return /^[0-9]+\|[A-Za-z0-9._-]{20,}$/.test(token);
}
