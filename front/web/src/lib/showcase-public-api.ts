/**
 * BC-27 SHOWCASE (#6867/#6862) — accès serveur au site vitrine PUBLIC.
 *
 * Rend la ressource publique `/api/v1/public/vitrine/{slug}` (DTO dédié,
 * aucune donnée interne) consommée par la page SSR `/vitrine/{slug}` de
 * l'espace web. Ce helper tourne dans un Server Component : il parle
 * directement à l'API Laravel (pas de proxy same-origin, pas d'auth) —
 * même pattern que `careers-api.ts`.
 *
 * Le backend renvoie 404 pour un slug inconnu OU une vitrine en brouillon
 * (le brouillon n'est visible qu'avec un jeton d'aperçu `?token=`).
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

export interface VitrineSection {
  type: string;
  schema_version: number;
  content: Record<string, unknown>;
}

export interface VitrineTheme {
  id: string;
  variables: Record<string, string>;
}

export interface VitrineMeta {
  title: string;
  description: string | null;
  og_image: string | null;
  canonical_path: string;
  indexable: boolean;
}

export interface VitrinePublic {
  slug: string;
  company_name: string;
  lang: string;
  locales: string[];
  theme: string;
  theme_config: VitrineTheme;
  published_at: string | null;
  settings: {
    colors?: Record<string, string>;
    brand_name?: string;
    tagline?: string;
  };
  legal: Record<string, unknown>;
  cookies: { third_party: boolean; banner_required: boolean; policy: unknown };
  meta: VitrineMeta;
  sections: VitrineSection[];
}

export async function getPublicVitrine(
  slug: string,
  options: { lang?: string; token?: string } = {},
): Promise<VitrinePublic | null> {
  const params = new URLSearchParams();
  if (options.lang) params.set('lang', options.lang);
  if (options.token) params.set('token', options.token);
  const qs = params.toString();

  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/vitrine/${encodeURIComponent(slug)}${qs ? `?${qs}` : ''}`,
      {
        headers: { Accept: 'application/json' },
        // Les vitrines publiées changent rarement : cache court côté serveur.
        // Une réponse avec jeton d'aperçu n'est jamais mise en cache.
        ...(options.token ? { cache: 'no-store' as const } : { next: { revalidate: 60 } }),
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: VitrinePublic };
    return payload.data ?? null;
  } catch {
    return null;
  }
}

/** Variables de thème → déclarations CSS `--vitrine-*` (rendu SSR). */
export function vitrineCssVariables(vitrine: VitrinePublic): Record<string, string> {
  const variables = vitrine.theme_config?.variables ?? {};
  const colors = vitrine.settings?.colors ?? {};

  const merged: Record<string, string> = { ...variables, ...colors };

  return {
    '--vitrine-primary': merged.primary ?? '#0F766E',
    '--vitrine-accent': merged.accent ?? '#0D9488',
    '--vitrine-surface': merged.surface ?? '#F8FAFC',
    '--vitrine-on-primary': merged.on_primary ?? '#FFFFFF',
    '--vitrine-radius': merged.radius ?? '0.5rem',
    '--vitrine-font': merged.font_family ?? 'system-ui, sans-serif',
  };
}
