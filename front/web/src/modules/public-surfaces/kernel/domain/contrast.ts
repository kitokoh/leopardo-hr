/**
 * Contraste WCAG — noyau des vitrines publiques.
 *
 * ## Pourquoi ici et pas dans un composant
 *
 * Chaque vitrine verticale possède sa propre palette (identité propre, cf.
 * `identity/`). Une palette saisie à la main dérive : un jour un accent clair
 * se retrouve sous un texte blanc et le CTA devient illisible. La règle
 * « le texte sur l'accent est lisible » est donc une **règle du domaine**, pas
 * une convention de design : elle est calculée, pas déclarée.
 *
 * Ce fichier est PUR : aucune E/S, aucun import de framework. Il est utilisable
 * côté serveur (SSR) comme côté client, et testable sans DOM.
 */

const HEX_PATTERN = /^#([0-9a-fA-F]{6})$/;

/** Seuil WCAG AA pour du texte normal. */
export const AA_NORMAL = 4.5;

/** Seuil WCAG AA pour du texte large (≥ 18,66 px gras ou ≥ 24 px). */
export const AA_LARGE = 3;

/**
 * Décompose une couleur `#RRGGBB` en canaux 0-255.
 *
 * @throws si la valeur n'est pas un hex à 6 chiffres — on préfère un échec
 * bruyant à une couleur silencieusement noire.
 */
export function parseHex(hex: string): [number, number, number] {
  if (!HEX_PATTERN.test(hex)) {
    throw new Error(`Couleur hex invalide (attendu #RRGGBB) : ${hex}`);
  }

  const value = hex.slice(1);

  return [
    Number.parseInt(value.slice(0, 2), 16),
    Number.parseInt(value.slice(2, 4), 16),
    Number.parseInt(value.slice(4, 6), 16),
  ];
}

/** Linéarisation d'un canal sRGB (formule WCAG 2.1). */
function linearize(channel: number): number {
  const normalized = channel / 255;

  return normalized <= 0.03928
    ? normalized / 12.92
    : Math.pow((normalized + 0.055) / 1.055, 2.4);
}

/** Luminance relative d'une couleur (0 = noir, 1 = blanc). */
export function relativeLuminance(hex: string): number {
  const [r, g, b] = parseHex(hex);

  return 0.2126 * linearize(r) + 0.7152 * linearize(g) + 0.0722 * linearize(b);
}

/** Ratio de contraste entre deux couleurs (1 → 21). */
export function contrastRatio(a: string, b: string): number {
  const luminanceA = relativeLuminance(a);
  const luminanceB = relativeLuminance(b);
  const lighter = Math.max(luminanceA, luminanceB);
  const darker = Math.min(luminanceA, luminanceB);

  return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Choisit, parmi des candidats, la couleur de texte la plus lisible sur un fond.
 *
 * Le défaut (`#0B1220` / `#FFFFFF`) couvre les deux cas réels : accent clair →
 * texte sombre, accent sombre → texte blanc. Le résultat est donc **toujours**
 * le meilleur des deux, jamais une valeur devinée.
 */
export function pickReadableOn(
  background: string,
  candidates: readonly string[] = ['#0B1220', '#FFFFFF'],
): string {
  if (candidates.length === 0) {
    throw new Error('[public-surfaces] pickReadableOn : au moins un candidat est requis');
  }

  let best = candidates[0];
  let bestRatio = -1;

  for (const candidate of candidates) {
    const ratio = contrastRatio(background, candidate);

    if (ratio > bestRatio) {
      bestRatio = ratio;
      best = candidate;
    }
  }

  return best;
}

/** Vrai si le couple fond/texte atteint le seuil AA demandé. */
export function meetsContrast(
  background: string,
  foreground: string,
  threshold: number = AA_NORMAL,
): boolean {
  return contrastRatio(background, foreground) >= threshold;
}
