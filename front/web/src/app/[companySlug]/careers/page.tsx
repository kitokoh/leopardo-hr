import { SITE_URL } from '@/lib/site-url';
import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import { Briefcase, MapPin, Clock, ArrowRight, Rss, FileSearch, FileText } from 'lucide-react';
import { CareersNavbar } from './CareersNavbar';
import { Footer } from '@/modules/vitrine/components/Footer';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { JsonLd } from '@/components/JsonLd';
import { normalizeLocale, type AppLocale } from '@/lib/i18n';
import { getTenantCareersCopy, tenantCareersMetaTitle, tenantCareersMetaDescription } from '@/modules/vitrine/data/tenant-careers';
import { getCandidateToolsCopy } from '@/modules/vitrine/data/candidate-tools';
import {
  getPublicCareersCompany,
  getPublicJobPostings,
  isExternalOffer,
  CONTRACT_TYPE_LABELS,
  REMOTE_POLICY_LABELS,
  type PublicJobPosting,
} from '@/lib/careers-api';

// #4448 : locale SSR. Ces routes (hors groupe `(landing)`) ne passent pas
// par le middleware vitrine → `x-vitrine-lang` n'y est JAMAIS posé (sinon
// tout retombait en 'fr'). On résout `?lang=` (prioritaire, #4173) puis
// Accept-Language normalisé, même règle que le RootLayout (#2657/#4393).
async function resolveCareersLocale(
  urlLang?: string,
): Promise<AppLocale> {
  const h = await headers();
  const fromUrl = normalizeLocale(urlLang ?? '');
  if (fromUrl !== 'fr' || urlLang) return fromUrl;
  const accept = h.get('accept-language') ?? '';
  return normalizeLocale(accept.split(',')[0] ?? '');
}

interface CareersPortalPageProps {
  params: Promise<{ companySlug: string }>;
  searchParams: Promise<{ lang?: string; q?: string; contract_type?: string; remote_policy?: string }>;
}

/**
 * BC-10 (#9220) — contrat interne → `employmentType` Schema.org.
 *
 * Google Jobs attend les valeurs normalisées de schema.org (`FULL_TIME`,
 * `CONTRACTOR`, `INTERN`…), pas nos codes internes (`cdi`, `cdd`…). Envoyer
 * `cdi` ferait ignorer le champ, et l'annonce perdrait son type de contrat.
 */
function mapEmploymentType(contractType: string | null): string {
  switch (contractType) {
    case 'cdi':
      return 'FULL_TIME';
    case 'cdd':
      return 'CONTRACTOR';
    case 'stage':
      return 'INTERN';
    case 'freelance':
      return 'TEMPORARY';
    default:
      return 'FULL_TIME';
  }
}

export async function generateMetadata({ params, searchParams }: CareersPortalPageProps): Promise<Metadata> {
  const { companySlug } = await params;
  const { lang } = await searchParams;
  const [jobs, company] = await Promise.all([
    getPublicJobPostings(companySlug),
    getPublicCareersCompany(companySlug),
  ]);
  const locale = await resolveCareersLocale(lang);

  if (jobs === null) {
    return { title: getTenantCareersCopy(locale).portalNotFoundTitle };
  }

  const displayName = company?.display_name ?? '';

  return generateSEOMetadata({
    title: tenantCareersMetaTitle(locale, displayName, jobs.length),
    description: tenantCareersMetaDescription(locale, displayName),
    canonical: `${SITE_URL}/${companySlug}/careers`,
    ogType: 'website',
    ogImage: company?.logo_url ?? undefined,
  });
}

export default async function CareersPortalPage({ params, searchParams }: CareersPortalPageProps) {
  const { companySlug } = await params;
  const { lang, q, contract_type, remote_policy } = await searchParams;
  const [jobs, company] = await Promise.all([
    getPublicJobPostings(companySlug, {
      q,
      contractType: contract_type,
      remotePolicy: remote_policy,
    }),
    getPublicCareersCompany(companySlug),
  ]);
  const locale = await resolveCareersLocale(lang);
  const copy = getTenantCareersCopy(locale);
  // ATS (#9461) — libellés des outils candidat (suivi + CV).
  const tools = getCandidateToolsCopy(locale);

  if (jobs === null) {
    notFound();
  }

  const brandColor = company?.primary_color ?? '#10B981';
  const displayName = company?.display_name ?? copy.fallbackCompanyName;

  // BC-10 (#9100) — deux populations distinctes, présentées séparément.
  //
  // Les offres du tenant et les offres agrégées n'ont pas la même nature : les
  // premières se candidatent ICI, les secondes renvoient chez le partenaire.
  // Les mélanger dans une seule liste ferait cliquer le visiteur sur un
  // formulaire qui n'existe pas pour lui. On les sépare donc explicitement, et
  // chaque carte dit à quoi elle correspond.
  const companyJobs = jobs.filter((job): job is PublicJobPosting => !isExternalOffer(job));
  const externalJobs = jobs.filter(isExternalOffer);

  const hasFilters = Boolean(q || contract_type || remote_policy);

  return (
    <div className="min-h-screen bg-white dark:bg-slate-950">
      <CareersNavbar />

      <JsonLd
        data={{
          '@context': 'https://schema.org',
          '@type': 'ItemList',
          itemListElement: jobs.map((job, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            url: isExternalOffer(job)
              ? job.apply_url
              : `${SITE_URL}/${companySlug}/careers/jobs/${job.id}`,
          })),
        }}
      />

      {/* BC-10 (#9220) — les offres AGRÉGÉES n'avaient pas de `JobPosting`
          structuré : elles apparaissaient dans l'`ItemList` mais restaient
          non indexables comme annonces par Google Jobs. On émet donc un
          `JobPosting` par offre agrégée, avec `hiringOrganization` = l'entreprise
          partenaire (et non le tenant, qui n'embauche pas pour ce poste) et
          `url` = l'URL de candidature du partenaire.

          `directApply: false` est explicite : la candidature se fait chez le
          partenaire, pas sur notre page. L'omettre laisserait Google croire à
          une candidature directe et dégraderait l'annonce. */}
      {externalJobs.length > 0 && (
        <JsonLd
          data={{
            '@context': 'https://schema.org',
            // `@graph` plutôt qu'un tableau racine : `JsonLd` sérialise un
            // OBJET, et un document JSON-LD dont la racine est un tableau est
            // valide mais moins bien toléré par certains crawlers.
            '@graph': externalJobs.map((job) => ({
              '@type': 'JobPosting',
              title: job.title,
              description: job.description ?? job.title,
              datePosted: job.published_at ?? undefined,
              validThrough: job.expires_at ?? undefined,
              employmentType: mapEmploymentType(job.contract_type),
              hiringOrganization: {
                '@type': 'Organization',
                name: job.company_name,
              },
              jobLocation: {
                '@type': 'Place',
                address: {
                  '@type': 'PostalAddress',
                  addressLocality: job.location ?? undefined,
                  addressCountry: job.country ?? undefined,
                },
              },
              ...(job.remote_policy === 'remote'
                ? { jobLocationType: 'TELECOMMUTE' }
                : {}),
              url: job.apply_url,
              directApply: false,
            })),
          }}
        />
      )}

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
            {copy.badge}
          </span>
          <h1 className="mt-4 text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">
            {copy.joinCompany(displayName)}
          </h1>
          <p className="mt-3 text-lg text-slate-600 dark:text-slate-400">
            {jobs.length > 0 ? copy.openingsCount(jobs.length) : copy.noOpenings}
          </p>

          {/* ATS (#9461) — les deux outils du candidat, accessibles depuis le
              portail : suivre une candidature déjà déposée, et construire un
              CV. Sans ces liens, un candidat n'avait aucun moyen de retrouver
              l'état de son dossier ni de préparer un CV depuis la vitrine. */}
          <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
            <Link
              href={`/${companySlug}/careers/applications${lang ? `?lang=${lang}` : ''}`}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            >
              <FileSearch className="h-4 w-4" />
              {tools.track.title}
            </Link>
            <Link
              href={`/${companySlug}/careers/cv${lang ? `?lang=${lang}` : ''}`}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            >
              <FileText className="h-4 w-4" />
              {tools.cv.title}
            </Link>
          </div>
        </div>
      </section>

      <section className="py-16">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Filtres : un simple formulaire GET, sans JavaScript. Le portail est
              public et indexé — il doit rester utilisable et partageable même
              sans hydratation, et chaque filtre produit une URL partageable. */}
          <form method="get" className="mb-8 flex flex-wrap items-end gap-3">
            {lang && <input type="hidden" name="lang" value={lang} />}
            <div className="flex-1 min-w-[200px]">
              <label htmlFor="careers-q" className="sr-only">{copy.searchPlaceholder}</label>
              <input
                id="careers-q"
                name="q"
                type="search"
                defaultValue={q ?? ''}
                placeholder={copy.searchPlaceholder}
                className="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm text-slate-900 dark:text-white"
              />
            </div>
            <div>
              <label htmlFor="careers-contract" className="sr-only">Contract</label>
              <select
                id="careers-contract"
                name="contract_type"
                defaultValue={contract_type ?? ''}
                className="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm text-slate-900 dark:text-white"
              >
                <option value="">{copy.filterAll}</option>
                {Object.entries(CONTRACT_TYPE_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </div>
            <div>
              <label htmlFor="careers-remote" className="sr-only">Remote</label>
              <select
                id="careers-remote"
                name="remote_policy"
                defaultValue={remote_policy ?? ''}
                className="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm text-slate-900 dark:text-white"
              >
                <option value="">{copy.filterAll}</option>
                {Object.entries(REMOTE_POLICY_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </div>
            <button
              type="submit"
              className="rounded-xl px-5 py-2.5 text-sm font-semibold text-white"
              style={{ backgroundColor: brandColor }}
            >
              {copy.searchPlaceholder.split(' ')[0]}
            </button>
            {hasFilters && (
              <Link
                href={`/${companySlug}/careers${lang ? `?lang=${lang}` : ''}`}
                className="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
              >
                {copy.clearFilters}
              </Link>
            )}
          </form>

          {jobs.length === 0 ? (
            <div className="text-center py-16 text-slate-500 dark:text-slate-400">
              {hasFilters ? copy.noResults : copy.noJobsYet}
            </div>
          ) : (
            <div className="space-y-10">
              {companyJobs.length > 0 && (
                <div>
                  {externalJobs.length > 0 && (
                    <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      {copy.companyOffersTitle}
                    </h2>
                  )}
                  <div className="space-y-4">
                    {companyJobs.map((job) => (
                      <Link
                        key={job.id}
                        href={`/${companySlug}/careers/jobs/${job.id}`}
                        className="group block p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-emerald-500/40 hover:shadow-lg transition-all"
                      >
                        <div className="flex items-start justify-between gap-4">
                          <div className="flex-1">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white group-hover:text-emerald-700 dark:group-hover:text-emerald-400 transition-colors">
                              {job.title}
                            </h3>
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
                </div>
              )}

              {/* Offres agrégées : lien SORTANT explicite. `rel="noopener
                  noreferrer nofollow"` — on ne transmet pas notre referer au
                  partenaire et on ne lui cède pas de jus SEO. */}
              {externalJobs.length > 0 && (
                <div>
                  <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {copy.externalOffersTitle}
                  </h2>
                  <div className="space-y-4">
                    {externalJobs.map((job) => (
                      <a
                        key={`ext-${job.id}`}
                        href={job.apply_url}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        className="group block p-6 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 hover:border-slate-400 transition-all"
                      >
                        <div className="flex items-start justify-between gap-4">
                          <div className="flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                              <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                {job.title}
                              </h3>
                              <span className="rounded-full bg-slate-200 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                                {copy.externalBadge}
                              </span>
                            </div>
                            <p className="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-300">
                              {job.company_name}
                            </p>
                            <div className="mt-2 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
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
                            <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                              {copy.externalNotice}
                            </p>
                          </div>
                          <ArrowRight className="w-5 h-5 text-slate-400 flex-shrink-0 mt-1" />
                        </div>
                      </a>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          <div className="mt-10 text-center">
            <a
              href={`/api/v1/public/careers/${companySlug}/feed.xml`}
              className="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 hover:text-emerald-700 dark:hover:text-emerald-400"
              rel="nofollow"
            >
              <Rss className="w-3.5 h-3.5" />
              {copy.feedLabel}
            </a>
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
