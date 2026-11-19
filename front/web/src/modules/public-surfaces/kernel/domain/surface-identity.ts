/**
 * Identité d'une vitrine publique verticale.
 *
 * ## Ce que ce type N'EST PAS
 *
 * Ce n'est **pas** une marque de tenant. Le branding d'un tenant est un sujet
 * distinct, déjà traité ailleurs dans le produit : il ne doit jamais être
 * confondu avec l'identité d'une vitrine verticale grand public.
 *
 * Ici, l'identité est celle d'une **surface publique de verticale** : le site
 * grand public dédié au restaurant, au retail, à l'hôtellerie, au B2B, aux
 * billets ou à la pharmacie. Elle est unique par verticale, connue à la
 * compilation, et ne dépend d'aucune donnée client.
 *
 * ## Invariants (vérifiés par `assertSurfaceIdentity`)
 *
 * - `basePath` est un chemin absolu (`/restaurants`), jamais une URL.
 * - `canonicalDomain` est un hôte nu (`restaurants.exemple.tld`), sans schéma
 *   ni slash : le schéma dépend de l'environnement, pas de l'identité.
 * - `onPrimary` / `onSurface` atteignent le contraste AA sur leur fond — la
 *   lisibilité est une règle du domaine, pas une intention de design.
 * - `discoveryType` est le type canonique du contrat public de découverte :
 *   c'est lui qui relie la vitrine aux données réelles de la verticale.
 */

import { AA_NORMAL, meetsContrast, pickReadableOn } from './contrast';

/** Verticales grand public couvertes par les vitrines dédiées. */
export const SURFACE_VERTICALS = [
  'restaurant',
  'retail',
  'hospitality',
  'trade',
  'travel',
  'pharmacy',
  // #9622 — verticale Mobilité (VTC / taxi). `VtcDiscoverableProvider` sert
  // `vtc_operator` depuis P1-02 (#8773), `POST /public/vtc/marketplace/rides`
  // accepte les courses invitées — mais aucune vitrine ne portait la verticale :
  // son annuaire `/operateurs` rendait encore le gabarit `buildDirectoryPage`.
  // Une verticale découvrable sans vitrine n'a ni identité ni composition
  // propres, et son parcours grand public s'arrête à une liste sommaire.
  'vtc',
  // #9629 — verticale Emploi (Careers). Le socle API existait déjà
  // (`/public/careers*`, `POST .../apply`, `POST .../applications/track`) et
  // l'espace pro de publication aussi (`/recruitment` du dashboard tenant),
  // mais **aucune surface publique cross-tenant** ne l'exposait : un candidat
  // devait connaître le slug de l'entreprise pour voir ses offres.
  'careers',
] as const;

export type SurfaceVertical = (typeof SURFACE_VERTICALS)[number];

/** Palette propre à une vitrine. Aucune valeur n'est partagée entre verticales. */
export interface SurfacePalette {
  /** Couleur d'action principale (CTA, liens actifs). */
  readonly primary: string;
  /** Texte posé sur `primary` — calculé, jamais saisi. */
  readonly onPrimary: string;
  /** Accent secondaire (badges, soulignements). */
  readonly accent: string;
  /** Fond de page. */
  readonly surface: string;
  /** Texte principal sur `surface`. */
  readonly onSurface: string;
  /** Texte secondaire (légendes, métadonnées). */
  readonly muted: string;
}

/** Identité complète d'une vitrine verticale. */
export interface SurfaceIdentity {
  readonly vertical: SurfaceVertical;
  /** Nom public de la vitrine (ex. « Leopardo Restaurants »). */
  readonly displayName: string;
  /** Accroche d'une ligne, orientée visiteur. */
  readonly tagline: string;
  /** Hôte canonique de la vitrine, sans schéma. */
  readonly canonicalDomain: string;
  /** Chemin public de la vitrine dans l'application web. */
  readonly basePath: string;
  /** Type canonique du contrat public de découverte. */
  readonly discoveryType: string;
  readonly palette: SurfacePalette;
  /** Locales servies par la vitrine (au moins `fr`). */
  readonly locales: readonly string[];
}

/**
 * Construit une palette dont les couleurs de texte sont **calculées**.
 *
 * L'appelant ne fournit que les couleurs de fond ; `onPrimary` et `onSurface`
 * sont dérivés pour garantir le contraste. Impossible de produire une palette
 * illisible par erreur de saisie.
 */
export function buildPalette(input: {
  primary: string;
  accent: string;
  surface: string;
  onSurface: string;
  muted: string;
}): SurfacePalette {
  return {
    primary: input.primary,
    onPrimary: pickReadableOn(input.primary),
    accent: input.accent,
    surface: input.surface,
    onSurface: input.onSurface,
    muted: input.muted,
  };
}

/**
 * Valide une identité et lève une erreur actionnable si un invariant casse.
 *
 * Appelée à la construction du registre : une identité invalide fait échouer le
 * démarrage (et donc la CI), jamais un rendu silencieusement dégradé.
 */
export function assertSurfaceIdentity(identity: SurfaceIdentity): void {
  const { vertical, displayName, tagline, canonicalDomain, basePath, discoveryType, palette } =
    identity;

  if (!SURFACE_VERTICALS.includes(vertical)) {
    throw new Error(`Verticale inconnue : ${String(vertical)}`);
  }

  if (displayName.trim() === '') {
    throw new Error(`[${vertical}] displayName vide`);
  }

  if (tagline.trim() === '') {
    throw new Error(`[${vertical}] tagline vide`);
  }

  if (!basePath.startsWith('/') || basePath.endsWith('/')) {
    throw new Error(`[${vertical}] basePath doit être un chemin absolu sans slash final : ${basePath}`);
  }

  if (/^https?:\/\//i.test(canonicalDomain) || canonicalDomain.includes('/')) {
    throw new Error(
      `[${vertical}] canonicalDomain doit être un hôte nu, sans schéma ni slash : ${canonicalDomain}`,
    );
  }

  if (discoveryType.trim() === '') {
    throw new Error(`[${vertical}] discoveryType vide`);
  }

  if (identity.locales.length === 0) {
    throw new Error(`[${vertical}] au moins une locale est requise`);
  }

  if (!meetsContrast(palette.primary, palette.onPrimary, AA_NORMAL)) {
    throw new Error(`[${vertical}] contraste AA insuffisant : onPrimary sur primary`);
  }

  if (!meetsContrast(palette.surface, palette.onSurface, AA_NORMAL)) {
    throw new Error(`[${vertical}] contraste AA insuffisant : onSurface sur surface`);
  }
}
