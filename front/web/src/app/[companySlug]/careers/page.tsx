import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import { Briefcase, MapPin, Clock, ArrowRight, Rss } from 'lucide-react';
import { Navbar } from '@/modules/vitrine/components/Navbar';
import { Footer } from '@/modules/vitrine/components/Footer';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { JsonLd } from '@/components/JsonLd';
import {
  getPublicCareersCompany,
  getPublicJobPostings,
  CONTRACT_TYPE_LABELS,
  REMOTE_POLICY_LABELS,
} from '@/lib/careers-api';

interface CareersPortalPageProps {
  params: Promise<{ companySlug: string }>;
}

export async function generateMetadata({ params }: CareersPortalPageProps): Promise<Metadata> {
  const { companySlug } = await params;
  const [jobs, company] = await Promise.all([
    getPublicJobPostings(companySlug),
    getPublicCareersCompany(companySlug),
  ]);

  if (jobs === null) {
    return { title: 'Portail carrieres introuvable' };
  }

  const displayName = company?.display_name ?? '';

  return generateSEOMetadata({
    title: `Carrieres${displayName ? ` - ${displayName}` : ''} - ${jobs.length} offre${jobs.length > 1 ? 's' : ''} d'emploi`,
    description: `Decouvrez les offres d'emploi ouvertes chez ${displayName || 'cette entreprise'} et postulez en ligne en quelques minutes.`,
    canonical: `https://gestionemployer-backend.vercel.app/${companySlug}/careers`,
    ogType: 'website',
    ogImage: company?.logo_url ?? undefined,
  });
}

export default async function CareersPortalPage({ params }: CareersPortalPageProps) {
  const { companySlug } = await params;
  const [jobs, company] = await Promise.all([
    getPublicJobPostings(companySlug),
    getPublicCareersCompany(companySlug),
  ]);

  if (jobs === null) {
    notFound();
  }

  const brandColor = company?.primary_color ?? '#10B981';
  const displayName = company?.display_name ?? 'notre equipe';

  return (
    <div className="min-h-screen bg-white dark:bg-slate-950">
      <Navbar isDark={false} onToggleDark={() => {}} />

      <JsonLd
        data={{
          '@context': 'https://schema.org',
          '@type': 'ItemList',
          itemListElement: jobs.map((job, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            url: `https://gestionemployer-backend.vercel.app/${companySlug}/careers/jobs/${job.id}`,
          })),
        }}
      />

      {/* Issue #1330: branding dynamique selon le tenant — logo + couleur
          principale de l'entreprise cliente, avec repli sur les couleurs
          Leopardo par defaut si le tenant n'a pas configure de branding. */}
      <section
        className="py-20 bg-gradient-to-b to-white dark:to-slate-950"
        style={{ backgroundImage: `linear-gradient(to bottom, ${brandColor}1A, transparent)` }}
      >
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          {company?.logo_url && (
            // eslint-disable-next-line @next/next/no-img-element -- external, per-tenant logo URL; not part of the Next.js image domain allowlist.
            <img
              src={company.logo_url}
              alt={`Logo ${displayName}`}
              className="mx-auto mb-4 h-12 w-auto object-contain"
            />
          )}
          <span
            className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide"
            style={{ backgroundColor: `${brandColor}1A`, color: brandColor }}
          >
            <Briefcase className="w-3.5 h-3.5" />
            Carrieres
          </span>
          <h1 className="mt-4 text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">
            Rejoignez {displayName}
          </h1>
          <p className="mt-3 text-lg text-slate-600 dark:text-slate-400">
            {jobs.length > 0
              ? `${jobs.length} poste${jobs.length > 1 ? 's' : ''} actuellement ouvert${jobs.length > 1 ? 's' : ''}`
              : 'Aucun poste ouvert pour le moment. Revenez bientot !'}
          </p>
        </div>
      </section>

      <section className="py-16">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          {jobs.length === 0 ? (
            <div className="text-center py-16 text-slate-500 dark:text-slate-400">
              Il n&apos;y a pas d&apos;offre d&apos;emploi publiee pour le moment.
            </div>
          ) : (
            <div className="space-y-4">
              {jobs.map((job) => (
                <Link
                  key={job.id}
                  href={`/${companySlug}/careers/jobs/${job.id}`}
                  className="group block p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-emerald-500/40 hover:shadow-lg transition-all"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1">
                      <h2 className="text-lg font-bold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                        {job.title}
                      </h2>
                      {job.description && (
                        <p className="text-sm text-slate-600 dark:text-slate-400 mt-1 mb-3 line-clamp-2">
                          {job.description}
                        </p>
                      )}
                      <div className="flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
                        {job.department && (
                          <span className="flex items-center gap-1">
                            <Briefcase className="w-3 h-3" />
                            {job.department.name}
                          </span>
                        )}
                        {job.location && (
                          <span className="flex items-center gap-1">
                            <MapPin className="w-3 h-3" />
                            {job.location}
                          </span>
                        )}
                        <span className="flex items-center gap-1">
                          <Clock className="w-3 h-3" />
                          {CONTRACT_TYPE_LABELS[job.contract_type]} - {REMOTE_POLICY_LABELS[job.remote_policy]}
                        </span>
                      </div>
                    </div>
                    <ArrowRight className="w-5 h-5 text-slate-400 group-hover:text-emerald-500 transition-colors flex-shrink-0 mt-1" />
                  </div>
                </Link>
              ))}
            </div>
          )}

          <div className="mt-10 text-center">
            <a
              href={`/api/v1/public/careers/${companySlug}/feed.xml`}
              className="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400"
              rel="nofollow"
            >
              <Rss className="w-3.5 h-3.5" />
              Flux XML (Google Jobs / Indeed)
            </a>
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
