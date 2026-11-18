/**
 * #9580 (BC-41 BRANDING) — entités et gardes du domaine « surface publique ».
 *
 * ## Rôle de cette couche
 *
 * Ce fichier ne contient AUCUNE E/S : ni `fetch`, ni `next/headers`, ni
 * `localStorage`. Il décrit la forme d'une Brand publique et fournit les
 * fonctions pures qui la valident.
 *
 * C'est la correction du défaut principal relevé par l'audit DDD du
 * 2026-11-17 : 148 fichiers de route qui importaient directement `api-client`
 * ou un `*-api`, donc dont la logique métier était inséparable de la couche
 * réseau. Ici, la règle est vérifiable par un test de garde : `domain/` et
 * `components/` n'importent jamais `infrastructure/`.
 *
 * ## Pourquoi des gardes de parsing plutôt qu'un cast
 *
 * Le DTO vient d'une API qui peut évoluer indépendamment du front. Un
 * `as PublicBrand` transformerait une réponse partielle en objet « valide »
 * dont chaque champ nul exploserait plus tard, loin de la cause. Les gardes
 * ci-dessous rendent la frontière explicite : ce qui entre dans le domaine est
 * vérifié, et ce qui ne l'est pas est rejeté (`null`), ce qui aboutit à un 404
 * propre plutôt qu'à une page blanche.
 */

/** Sens d'écriture, dérivé de la locale — jamais recopié dans trois vues. */
export type Direction = 'ltr' | 'rtl';

/** Locales rendues par le produit (registre partagé avec l'API). */
export const SUPPORTED_LOCALES = ['fr', 'en', 'ar', 'tr'] as const;

export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

/**
 * Palette de la Brand, telle que reçue : `on_primary` est CALCULÉ côté serveur
 * (contraste WCAG). Le front ne le recalcule pas — deux implémentations de la
 * même formule finiraient par diverger, et c'est l'écran qui paierait.
 */
export interface BrandPalette {
  primary: string;
  accent: string;
  surface: string;
  on_primary: string;
}

export interface BrandIdentity {
  display_name: string;
  tagline: string | null;
  logo_url: string | null;
  favicon_url: string | null;
  og_image_url: string | null;
}

/** Surface publique : adresse et locales de la Brand. */
export interface BrandSurface {
  host: string | null;
  path_prefix: string | null;
  locales: string[];
  default_locale: string;
}

/**
 * Capacités publiques qu'une verticale sait offrir.
 *
 * Ce n'est PAS une permission : c'est une déclaration de forme. Le rendu
 * n'affiche une action que si l'établissement peut réellement l'honorer —
 * déclarer `order` ne doit jamais produire un bouton qui mène à une erreur.
 */
export type SurfaceCapability =
  | 'order'
  | 'reserve'
  | 'book'
  | 'quote'
  | 'prescription'
  | 'ride'
  | 'contact'
  | 'directions'
  | 'hours'
  | 'ticket';

/** Profil de surface fourni par la verticale (même contrat que `SurfaceProfile`). */
export interface SurfaceProfile {
  vertical: string;
  label: string;
  capabilities: SurfaceCapability[];
  block_types: string[];
  theme: Record<string, string | number | boolean>;
  primary_cta: SurfaceCapability | null;
}

/** Une Brand publique, telle que consommée par le rendu. */
export interface PublicBrand {
  slug: string;
  locale: string;
  direction: Direction;
  locales: string[];
  default_locale: string;
  vertical: string;
  identity: BrandIdentity;
  palette: BrandPalette;
  surface: BrandSurface;
  profile: SurfaceProfile;
  published_at: string | null;
}

// ── Gardes ────────────────────────────────────────────────────────────────

const HEX = /^#[0-9a-f]{6}$/i;

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function str(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function stringList(value: unknown): string[] {
  return Array.isArray(value) ? value.filter((v): v is string => typeof v === 'string') : [];
}

/**
 * Parse une palette. Une couleur absente ou mal formée fait échouer le parsing
 * plutôt que de produire un `undefined` qui deviendrait `NaN` dans les calculs
 * de teinte au rendu.
 */
export function parsePalette(value: unknown): BrandPalette | null {
  if (!isRecord(value)) {
    return null;
  }

  const primary = str(value.primary);
  const accent = str(value.accent);
  const surface = str(value.surface);
  const onPrimary = str(value.on_primary);

  if (!primary || !accent || !surface || !onPrimary) {
    return null;
  }

  if (!HEX.test(primary) || !HEX.test(accent) || !HEX.test(surface) || !HEX.test(onPrimary)) {
    return null;
  }

  return { primary, accent, surface, on_primary: onPrimary };
}

function parseIdentity(value: unknown): BrandIdentity | null {
  if (!isRecord(value)) {
    return null;
  }

  const displayName = str(value.display_name);

  // Le nom public est l'invariant fort : sans lui, la page n'a ni titre ni
  // métadonnées. L'API refuse déjà la publication sans ce champ ; le front le
  // vérifie aussi, parce qu'une Brand publiée avant l'ajout de la règle existe.
  if (!displayName) {
    return null;
  }

  return {
    display_name: displayName,
    tagline: str(value.tagline),
    logo_url: str(value.logo_url),
    favicon_url: str(value.favicon_url),
    og_image_url: str(value.og_image_url),
  };
}

function parseProfile(value: unknown): SurfaceProfile | null {
  if (!isRecord(value)) {
    return null;
  }

  const vertical = str(value.vertical);
  const label = str(value.label);

  if (!vertical || !label) {
    return null;
  }

  const capabilities = stringList(value.capabilities) as SurfaceCapability[];
  const blockTypes = stringList(value.block_types);
  const primaryCta = str(value.primary_cta) as SurfaceCapability | null;

  return {
    vertical,
    label,
    capabilities,
    block_types: blockTypes,
    theme: isRecord(value.theme) ? (value.theme as SurfaceProfile['theme']) : {},
    // Le CTA primaire doit être une capacité réellement déclarée : sinon le
    // rendu mettrait en avant une action que la surface ne sait pas faire.
    primary_cta: primaryCta && capabilities.includes(primaryCta) ? primaryCta : null,
  };
}

function parseSurface(value: unknown, fallbackLocale: string): BrandSurface {
  const record = isRecord(value) ? value : {};
  const locales = stringList(record.locales);
  const declaredDefault = str(record.default_locale);

  return {
    host: str(record.host),
    path_prefix: str(record.path_prefix),
    locales: locales.length > 0 ? locales : [fallbackLocale],
    default_locale: declaredDefault ?? fallbackLocale,
  };
}

/**
 * Parse le DTO public d'une Brand. `null` = la page doit faire `notFound()`.
 */
export function parsePublicBrand(value: unknown): PublicBrand | null {
  if (!isRecord(value)) {
    return null;
  }

  const slug = str(value.slug);
  const locale = str(value.locale) ?? 'fr';
  const identity = parseIdentity(value.identity);
  const palette = parsePalette(value.palette);
  const profile = parseProfile(value.profile);

  if (!slug || !identity || !palette || !profile) {
    return null;
  }

  const direction: Direction = value.direction === 'rtl' ? 'rtl' : 'ltr';

  return {
    slug,
    locale,
    direction,
    locales: stringList(value.locales),
    default_locale: str(value.default_locale) ?? locale,
    vertical: str(value.vertical) ?? 'generic',
    identity,
    palette,
    surface: parseSurface(value.surface, locale),
    profile,
    published_at: str(value.published_at),
  };
}

/**
 * Normalise une locale reçue en query string.
 *
 * Une locale inconnue retombe sur '' (donc sur la locale par défaut de la
 * Brand) plutôt que de forcer `fr` : une Brand dont la langue par défaut est
 * l'arabe ne doit pas basculer en français parce qu'un visiteur a demandé
 * `?lang=xx`.
 */
export function normalizeLocale(raw: string | undefined | null): string {
  if (!raw) {
    return '';
  }

  const candidate = raw.trim().toLowerCase().slice(0, 2);

  return (SUPPORTED_LOCALES as readonly string[]).includes(candidate) ? candidate : '';
}

/** Le sens d'écriture d'une locale — même règle que le presenter API. */
export function directionFor(locale: string): Direction {
  return locale === 'ar' ? 'rtl' : 'ltr';
}
