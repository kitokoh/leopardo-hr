import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import {
  CAREERS_SURFACE,
  CompanyJobsSurface,
  loadCompanyJobsView,
} from '@/modules/public-surfaces';

/**
 * Offres d'une entreprise (`/emploi/{companySlug}`) — #9629.
 *
 * Reprise de `front/web/src/app/[companySlug]/careers/page.tsx`, mais rendue
 * par la verticale au lieu d'être réimplémentée dans la page.
 *
 * ## Pourquoi une page et pas une redirection
 *
 * L'ancienne route `/{companySlug}/careers` reste en place (elle est indexée et
 * liée depuis les portails d'entreprise). Cette page est le chemin **cross-tenant** :
 * on y arrive depuis l'annuaire `/emploi`, sans connaître le slug à l'avance.
 */

interface CompanyJobsPageProps {
  params: Promise<{ companySlug: string }>;
  searchParams: Promise<{
    q?: string;
    contract_type?: string;
    remote_policy?: string;
    lang?: string;
  }>;
}

export async function generateMetadata({
  params,
  searchParams,
}: CompanyJobsPageProps): Promise<Metadata> {
  const { companySlug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  const result = await loadCompanyJobsView(companySlug, {}, locale);

  return {
    title: `${result.companyName} — ${CAREERS_SURFACE.displayName}`,
    description: CAREERS_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}/emploi/${companySlug}` },
    robots: { index: true, follow: true },
    openGraph: {
      title: `${result.companyName} — ${CAREERS_SURFACE.displayName}`,
      description: CAREERS_SURFACE.tagline,
      type: 'website',
    },
  };
}

export default async function CompanyJobsPage({ params, searchParams }: CompanyJobsPageProps) {
  const { companySlug } = await params;
  const search = await searchParams;
  const locale = normalizeLocale(search.lang ?? '');

  const result = await loadCompanyJobsView(
    companySlug,
    {
      q: (search.q ?? '').slice(0, 120) || undefined,
      contractType: (search.contract_type ?? '').slice(0, 32) || undefined,
      remotePolicy: (search.remote_policy ?? '').slice(0, 32) || undefined,
    },
    locale,
  );

  return (
    <CompanyJobsSurface
      identity={CAREERS_SURFACE}
      result={result}
      companySlug={companySlug}
      locale={locale}
    />
  );
}
