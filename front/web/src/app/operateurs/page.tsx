import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import {
  MOBILITY_SURFACE,
  MobilitySurface,
  loadMobilitySurface,
} from '@/modules/public-surfaces';

/**
 * Vitrine publique Mobilité — annuaire VTC / taxi (`/operateurs`), #9622.
 *
 * ## Ce qui a changé
 *
 * Cette page était un appel de 19 lignes à `buildDirectoryPage({ type,
 * basePath })` — le gabarit partagé du portail public. Elle était donc la
 * seule verticale découvrable du backend SANS vitrine propre : pas d'identité,
 * pas de domaine canonique, pas de déploiement dédié, et une composition
 * visuelle identique à l'annuaire générique.
 *
 * Elle rend désormais sa propre composition (liste en lignes, ville d'ancrage
 * en signal principal, devise en badge, aucun prix ni note inventés) et charge
 * via son cas d'usage, qui distingue panne API, annuaire vide et résultats.
 *
 * ## Le chemin reste `/operateurs`
 *
 * C'est celui que publie `VtcDiscoverableProvider` (`url = /operateurs/{slug}`)
 * et que consomme déjà la fiche `/operateurs/{slug}`. Le déplacer aurait cassé
 * les liens de la découverte et l'indexation existante : la vitrine prend le
 * chemin, elle ne le déplace pas.
 *
 * SSR indexable, alimentée par `GET /api/v1/public/discover?type=vtc_operator`
 * (opt-in `vtc_marketplace_operators.status = active`).
 */

interface OperateursPageProps {
  searchParams: Promise<{ q?: string; city?: string; page?: string; lang?: string }>;
}

export async function generateMetadata({ searchParams }: OperateursPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: MOBILITY_SURFACE.displayName,
    description: MOBILITY_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}${MOBILITY_SURFACE.basePath}` },
    robots: { index: true, follow: true },
    openGraph: {
      title: MOBILITY_SURFACE.displayName,
      description: MOBILITY_SURFACE.tagline,
      type: 'website',
      locale,
    },
  };
}

export default async function OperateursPage({ searchParams }: OperateursPageProps) {
  const { q, city, page, lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  const result = await loadMobilitySurface({
    q,
    city,
    page: page ? Number.parseInt(page, 10) || undefined : undefined,
  });

  return <MobilitySurface identity={MOBILITY_SURFACE} result={result} locale={locale} />;
}
