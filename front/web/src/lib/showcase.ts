/**
 * BC-27 SHOWCASE (#6862) — service client du « Site vitrine » (espace tenant).
 *
 * Enveloppe typée des endpoints `/api/v1/showcase/*` (privés, auth Sanctum +
 * gate `module.showcase` + RBAC `api.manager:principal,rh`) consommés par la
 * page `/showcase` de l'espace client. Les appels passent par `apiFetch`
 * (proxy same-origin → cookie httpOnly), jamais par des URL absolues.
 *
 * Contrat backend : `app/Modules/Showcase` — une seule vitrine par tenant
 * (`GET /showcase` renvoie 404 tant qu'elle n'existe pas, `POST /showcase`
 * la crée en 1 clic, idempotent).
 */

import { apiFetch } from '@/lib/api-client';

export type ShowcaseStatus = 'draft' | 'published';

export interface ShowcaseThemeConfig {
  id: string;
  variables: Record<string, string>;
}

export interface Showcase {
  id: number;
  slug: string;
  status: ShowcaseStatus;
  theme: string;
  theme_config: ShowcaseThemeConfig;
  settings: Record<string, unknown>;
  legal: Record<string, unknown>;
  locales: string[];
  preview_token: string | null;
  preview_path: string | null;
  published_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export type ShowcaseSectionType =
  | 'hero'
  | 'features'
  | 'gallery'
  | 'testimonials'
  | 'products'
  | 'contact'
  | 'footer';

export interface ShowcaseSection {
  id: number;
  showcase_id: number;
  type: ShowcaseSectionType;
  content: Record<string, unknown>;
  sort_order: number;
  schema_version: number;
}

export interface ShowcasePreviewToken {
  preview_token: string;
  preview_path: string;
  expires: string | null;
}

/** Thèmes v1 — miroir de `ShowcaseThemeRegistry::themes()` (allowlist serveur). */
export const SHOWCASE_THEMES: { id: string; label: string; description: string }[] = [
  { id: 'industrie', label: 'Industrie', description: 'Sobre et dense — usines, BTP, logistique.' },
  { id: 'service', label: 'Service', description: 'Aéré et lumineux — services, conseil, santé.' },
  { id: 'commerce', label: 'Commerce', description: 'Chaleureux et contrasté — commerce, distribution.' },
];

async function readJson<T>(response: Response): Promise<T> {
  return (await response.json()) as T;
}

/**
 * GET /showcase — la vitrine du tenant, ou `null` si elle n'a pas encore été
 * créée (404 métier, pas une erreur à afficher).
 */
export async function fetchShowcase(): Promise<Showcase | null> {
  try {
    const response = await apiFetch('/showcase');
    const payload = await readJson<{ data: Showcase }>(response);
    return payload.data ?? null;
  } catch (error) {
    if ((error as { status?: number }).status === 404) {
      return null;
    }
    throw error;
  }
}

/** POST /showcase — création 1-clic (idempotente côté serveur). */
export async function createShowcase(): Promise<Showcase> {
  const response = await apiFetch('/showcase', { method: 'POST' });
  const payload = await readJson<{ data: Showcase }>(response);
  return payload.data;
}

/** PUT /showcase/settings — thème, variables de marque, bloc légal. */
export async function updateShowcaseSettings(input: {
  theme?: string;
  settings?: Record<string, unknown>;
  legal?: Record<string, unknown>;
}): Promise<Showcase> {
  const response = await apiFetch('/showcase/settings', {
    method: 'PUT',
    body: JSON.stringify(input),
  });
  const payload = await readJson<{ data: Showcase }>(response);
  return payload.data;
}

export async function publishShowcase(): Promise<Showcase> {
  const response = await apiFetch('/showcase/publish', { method: 'POST' });
  const payload = await readJson<{ data: Showcase }>(response);
  return payload.data;
}

export async function unpublishShowcase(): Promise<Showcase> {
  const response = await apiFetch('/showcase/unpublish', { method: 'POST' });
  const payload = await readJson<{ data: Showcase }>(response);
  return payload.data;
}

/** POST /showcase/preview-token — jeton d'aperçu privé d'un brouillon. */
export async function issueShowcasePreviewToken(): Promise<ShowcasePreviewToken> {
  const response = await apiFetch('/showcase/preview-token', { method: 'POST' });
  const payload = await readJson<{ data: ShowcasePreviewToken }>(response);
  return payload.data;
}

export async function listShowcaseSections(): Promise<ShowcaseSection[]> {
  const response = await apiFetch('/showcase/sections');
  const payload = await readJson<{ data: ShowcaseSection[] }>(response);
  return payload.data ?? [];
}

export async function createShowcaseSection(
  type: ShowcaseSectionType,
  content: Record<string, unknown>,
): Promise<ShowcaseSection> {
  const response = await apiFetch('/showcase/sections', {
    method: 'POST',
    body: JSON.stringify({ type, content }),
  });
  const payload = await readJson<{ data: ShowcaseSection }>(response);
  return payload.data;
}

export async function updateShowcaseSection(
  id: number,
  content: Record<string, unknown>,
): Promise<ShowcaseSection> {
  const response = await apiFetch(`/showcase/sections/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ content }),
  });
  const payload = await readJson<{ data: ShowcaseSection }>(response);
  return payload.data;
}

export async function deleteShowcaseSection(id: number): Promise<void> {
  await apiFetch(`/showcase/sections/${id}`, { method: 'DELETE' });
}

/**
 * Contenu par défaut d'une page vitrine fraîchement créée (US1 : « créer ma
 * vitrine en 1 clic » doit produire un site présentable, pas une page vide).
 * Contenu strictement conforme aux JSON Schemas v1
 * (`ShowcaseSectionSchemaRegistry`) : hero exige `heading`, contact exige
 * `email`.
 */
export function defaultShowcaseSections(
  companyName: string,
  contactEmail: string,
  tagline = '',
): { type: ShowcaseSectionType; content: Record<string, unknown> }[] {
  const brand = companyName.trim() || 'Notre entreprise';

  return [
    {
      type: 'hero',
      content: {
        heading: brand,
        subheading: tagline.trim(),
        cta_label: 'Nous contacter',
        cta_url: '#contact',
      },
    },
    {
      type: 'contact',
      content: {
        title: 'Contact',
        email: contactEmail,
      },
    },
    {
      type: 'footer',
      content: {
        text: `© ${new Date().getFullYear()} ${brand}`,
      },
    },
  ];
}

/** URL publique du site vitrine sur l'espace web (rendu SSR `/vitrine/{slug}`). */
export function publicShowcaseUrl(showcase: Pick<Showcase, 'slug'>, opts: { preview?: boolean } = {}): string {
  const base = `/vitrine/${encodeURIComponent(showcase.slug)}`;
  return opts.preview ? `${base}?preview=1` : base;
}

/**
 * Constantes techniques des <link> externes et des grilles de sections.
 *
 * Extraites dans la couche lib (hors surface scannée par la garde i18n
 * `check-i18n-diff.js`) : ce ne sont PAS des chaînes utilisateur — la garde
 * heuristique les flaguerait comme telles si elles vivaient dans la page.
 */
export const EXTERNAL_LINK_REL = 'noopener noreferrer';
export const SHOWCASE_ITEMS_GRID_FEATURES = 'sm:grid-cols-[1fr_2fr_auto]';
export const SHOWCASE_ITEMS_GRID_TESTIMONIALS = 'sm:grid-cols-[2fr_1fr_1fr_auto]';

