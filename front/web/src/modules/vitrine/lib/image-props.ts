/**
 * Propriétés d'image pour les visuels servis en local (`public/`).
 *
 * Contexte (bug constaté en production, cf. issue vitrine) : `next/image`
 * optimise les images via `/_next/image`. Or Next **refuse les SVG** tant que
 * `images.dangerouslyAllowSVG` est faux — et il l'est volontairement
 * (`front/web/next.config.ts`), car un SVG peut embarquer du script. Résultat :
 * `/_next/image?url=/blog/xxx.svg` répond **400** (« image type is not allowed »)
 * et l'image ne s'affiche pas.
 *
 * Nos visuels de contenu sont des SVG first-party (`/blog/*.svg` pour les
 * couvertures d'articles, `/avatars/*.svg` pour les équipes et les auteurs) :
 * vecteurs, donc rien à optimiser. La bonne réponse est celle que Next
 * recommande lui-même dans son message d'erreur : `unoptimized`, qui sert le
 * fichier directement depuis `public/` (200) au lieu de passer par l'optimiseur.
 *
 * Preuve mesurée (build de production local, 2026-09-16) :
 *   /_next/image?url=%2Fblog%2Fstartup-rh.svg → 400
 *   /_next/image?url=%2Fscreenshots%2Fweb-dashboard.png → 200
 *   /blog/startup-rh.svg (direct) → 200
 */

/**
 * Vrai si la source est un vecteur (SVG) servi localement — donc à servir sans
 * passer par l'optimiseur d'images de Next.
 *
 * Les paramètres de requête et le fragment sont ignorés (`/x.svg?v=2`).
 * Les sources distantes (`https://…`) ne sont pas concernées : `remotePatterns`
 * est vide et une URL distante en SVG doit rester un choix explicite.
 */
export function isVectorAsset(src: unknown): boolean {
  if (typeof src !== "string") return false;
  const withoutQuery = src.split(/[?#]/, 1)[0];
  return withoutQuery.toLowerCase().endsWith(".svg");
}

/**
 * À étaler sur un `<Image>` : `{...localImageProps(src)}`.
 *
 * On garde `unoptimized` conditionnel (et non systématique) pour que les PNG
 * — qui, eux, bénéficient réellement d'AVIF/WebP et du redimensionnement —
 * continuent de passer par l'optimiseur.
 */
export function localImageProps(src: unknown): { unoptimized?: true } {
  return isVectorAsset(src) ? { unoptimized: true } : {};
}
