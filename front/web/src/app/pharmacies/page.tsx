import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import {
  PHARMACY_SURFACE,
  PharmacySurface,
  loadPharmacySurface,
  surfaceUrl,
} from '@/modules/public-surfaces';

/**
 * Vitrine publique Pharmacie — annuaire (`/pharmacies`), #9592.
 *
 * SSR indexable, alimentée par `GET /api/v1/public/discover?type=pharmacy`
 * (provider `PharmacyDiscoverableProvider`, opt-in
 * `pharmacy_public_profiles.enabled`).
 *
 * ## Pourquoi cette page n'existait pas
 *
 * La verticale Pharmacie avait son identité, son domaine et son composant de
 * vitrine, mais **aucune route** : le provider backend manquait, donc la page
 * aurait affiché « bientôt disponible » en permanence. Le provider existe
 * maintenant, la page peut donc être servie.
 *
 * ## Trois états distincts
 *
 * `unavailable` (panne API), `empty` (aucune officine opt-in) et `ready` sont
 * rendus différemment par `PharmacySurface` : une verticale pas encore
 * alimentée ne doit jamais ressembler à une recherche sans résultat.
 */

interface PharmaciesPageProps {
  searchParams: Promise<{ q?: string; city?: string; page?: string; lang?: string }>;
}

export async function generateMetadata({ searchParams }: PharmaciesPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: PHARMACY_SURFACE.displayName,
    description: PHARMACY_SURFACE.tagline,
    alternates: { canonical: surfaceUrl(PHARMACY_SURFACE) },
    robots: { index: true, follow: true },
    openGraph: {
      title: PHARMACY_SURFACE.displayName,
      description: PHARMACY_SURFACE.tagline,
      type: 'website',
    },
  };
}

export default async function PharmaciesPage({ searchParams }: PharmaciesPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const result = await loadPharmacySurface({
    q: (params.q ?? '').slice(0, 120) || undefined,
    city: (params.city ?? '').slice(0, 120) || undefined,
    page: Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1),
    locale,
  });

  return (
    <PharmacySurface
      identity={PHARMACY_SURFACE}
      result={result}
      locale={locale}
      searchParams={{ q: params.q, city: params.city }}
    />
  );
}
