import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { JsonLd } from '@/components/JsonLd';
import {
  CAREERS_SURFACE,
  JobDetailSurface,
  jobPostingJsonLd,
  loadJobDetailView,
} from '@/modules/public-surfaces';

/**
 * Fiche d'une offre d'emploi (`/emploi/{companySlug}/offres/{jobId}`) — #9629.
 *
 * Reprise de `front/web/src/app/[companySlug]/careers/jobs/[jobId]/page.tsx`.
 *
 * ## JSON-LD `JobPosting`
 *
 * C'est ce qui rend l'offre éligible à Google Jobs. Le mapping des types de
 * contrat (`cdi` → `FULL_TIME`) et `directApply` sont produits par le domaine,
 * pas recopiés ici : une offre agrégée ne doit pas se déclarer candidatable
 * sur place.
 */

interface JobPageProps {
  params: Promise<{ companySlug: string; jobId: string }>;
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ params, searchParams }: JobPageProps): Promise<Metadata> {
  const { companySlug, jobId } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  const view = await loadJobDetailView(companySlug, jobId, locale);

  if (view === null) {
    return { title: CAREERS_SURFACE.displayName, robots: { index: false, follow: false } };
  }

  const title = `${view.offer.title} — ${view.offer.companyName}`;

  return {
    title,
    description: view.offer.description?.slice(0, 160) ?? CAREERS_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}/emploi/${companySlug}/offres/${jobId}` },
    robots: { index: true, follow: true },
    openGraph: { title, description: CAREERS_SURFACE.tagline, type: 'website' },
  };
}

export default async function JobPage({ params, searchParams }: JobPageProps) {
  const { companySlug, jobId } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  const view = await loadJobDetailView(companySlug, jobId, locale);

  if (view === null) {
    notFound();
  }

  const canonical = `${SITE_URL}/emploi/${companySlug}/offres/${jobId}`;

  return (
    <>
      <JsonLd data={jobPostingJsonLd(view.offer, canonical)} />
      <JobDetailSurface
        identity={CAREERS_SURFACE}
        view={view}
        companySlug={companySlug}
        locale={locale}
      />
    </>
  );
}
