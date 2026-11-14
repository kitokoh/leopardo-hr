import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { headers } from 'next/headers';
import { CareersNavbar } from '../CareersNavbar';
import { Footer } from '@/modules/vitrine/components/Footer';
import { normalizeLocale, type AppLocale } from '@/lib/i18n';
import { getCandidateToolsCopy } from '@/modules/vitrine/data/candidate-tools';
import { getPublicCareersCompany } from '@/lib/careers-api';
import { TrackApplicationsForm } from './TrackApplicationsForm';

/**
 * Suivi de candidature (ATS #9461).
 *
 * Page publique, sans compte : le candidat saisit l'email utilisé pour
 * postuler. Le tenant est résolu depuis `{companySlug}`, donc un candidat ne
 * voit que les dossiers déposés chez cette entreprise.
 *
 * `noindex` : la page ne contient aucune offre et n'a rien à faire dans un
 * moteur de recherche — l'indexer reviendrait à publier une page vide.
 */

async function resolveLocale(urlLang?: string): Promise<AppLocale> {
  const h = await headers();
  const fromUrl = normalizeLocale(urlLang ?? '');
  if (fromUrl !== 'fr' || urlLang) return fromUrl;
  const accept = h.get('accept-language') ?? '';
  return normalizeLocale(accept.split(',')[0] ?? '');
}

interface TrackPageProps {
  params: Promise<{ companySlug: string }>;
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ params, searchParams }: TrackPageProps): Promise<Metadata> {
  const { companySlug } = await params;
  const { lang } = await searchParams;
  const locale = await resolveLocale(lang);
  const copy = getCandidateToolsCopy(locale).track;

  return {
    title: `${copy.title} — ${companySlug}`,
    description: copy.intro,
    robots: { index: false, follow: false },
  };
}

export default async function TrackApplicationsPage({ params, searchParams }: TrackPageProps) {
  const { companySlug } = await params;
  const { lang } = await searchParams;

  const company = await getPublicCareersCompany(companySlug);
  if (company === null) {
    notFound();
  }

  const locale = await resolveLocale(lang);
  const copy = getCandidateToolsCopy(locale).track;
  const brandColor = company.primary_color ?? '#10B981';
  const displayName = company.display_name || company.name;

  return (
    <div className="min-h-screen bg-white">
      <CareersNavbar />

      <main className="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <header className="mb-8">
          <p
            className="mb-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-widest"
            style={{ backgroundColor: `${brandColor}1A`, color: brandColor }}
          >
            {displayName}
          </p>
          <h1 className="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
            {copy.title}
          </h1>
          <p className="mt-3 text-sm text-slate-600">{copy.intro}</p>
        </header>

        <TrackApplicationsForm companySlug={companySlug} />
      </main>

      <Footer />
    </div>
  );
}
