/**
 * #9351 (audit sécurité 2026-11-10, F9) — rendu sûr d'un SVG fourni par l'API.
 *
 * Le panneau d'activation 2FA injectait `enrollData.qr_code_svg` via
 * `dangerouslySetInnerHTML`. Le SVG est produit côté serveur, mais rien côté
 * client ne garantissait son intégrité : une réponse API altérée (proxy,
 * régression amont, XSS dans le générateur) faisait exécuter un `<script>`
 * dans la page de réglages de sécurité — c'est-à-dire l'écran qui manipule
 * les secrets TOTP.
 *
 * On n'injecte plus de balisage dans le DOM. Le SVG est converti en data URL
 * et affiché dans un `<img>` : le navigateur rend alors le document dans un
 * contexte isolé, sans exécuter de script, sans événement `on*`, sans accès
 * au DOM de la page. Aucune dépendance supplémentaire (DOMPurify n'est pas
 * dans l'arbre de dépendances du projet) et aucune analyse de balisage à
 * écrire — la barrière est celle du moteur de rendu.
 *
 * Défense complémentaire : on refuse les documents qui ne sont pas un `<svg`
 * à la racine, et on rejette ce qui ne peut pas être encodé.
 */

/** Longueur maximale acceptée pour un QR code (un QR 2FA fait < 2 Ko). */
const MAX_SVG_LENGTH = 32_768;

/**
 * Convertit un SVG (chaîne) en data URL affichable dans une balise `<img>`.
 *
 * @param svgMarkup Balisage SVG renvoyé par l'API.
 * @returns Une data URL `data:image/svg+xml;base64,…`, ou `null` si l'entrée
 *          n'est pas un SVG exploitable.
 */
export function svgToDataUrl(svgMarkup: unknown): string | null {
  if (typeof svgMarkup !== 'string') {
    return null;
  }

  const markup = svgMarkup.trim();

  if (markup === '' || markup.length > MAX_SVG_LENGTH) {
    return null;
  }

  // Un QR code est un `<svg>` (éventuellement précédé d'un prologue XML ou
  // d'un commentaire). Tout autre racine est refusé : pas de `<html>`, pas de
  // fragment arbitraire.
  if (!/^(?:<\?xml[^>]*\?>\s*)?(?:<!--[\s\S]*?-->\s*)*<svg[\s>]/i.test(markup)) {
    return null;
  }

  try {
    // `btoa` ne gère que le Latin-1 : on encode d'abord en UTF-8 pour que les
    // métadonnées accentuées (titre, libellé) ne cassent pas l'encodage.
    const bytes = new TextEncoder().encode(markup);
    let binary = '';

    for (const byte of bytes) {
      binary += String.fromCharCode(byte);
    }

    return `data:image/svg+xml;base64,${btoa(binary)}`;
  } catch {
    return null;
  }
}
