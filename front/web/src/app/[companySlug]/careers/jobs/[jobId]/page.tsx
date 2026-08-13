import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Briefcase, MapPin, Clock, Wallet } from 'lucide-react';
import { Navbar } from '@/modules/vitrine/components/Navbar';
import { Footer } from '@/modules/vitrine/components/Footer';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { JsonLd } from '@/components/JsonLd';
import {
  getPublicCareersCompany,
  getPublicJobPosting,
  CONTRACT_TYPE_LABELS,
  REMOTE_POLICY_LABELS,
} from '@/lib/careers-api';
import { ApplyForm } from './ApplyForm';

interface JobDetailPageProps {
  params: Promise<{ companySlug: string; jobId: string }>;
}

export async function generateMetadata({ params }: JobDetailPageProps): Promise<Metadata> {
  const { companySlug, jobId } = await params;
  const job = await getPublicJobPosting(companySlug, jobId);

  if (!job) {
    return { title: 'Offre introuvable' };
  }

  const company = await getPublicCareersCompany(companySlug);

  return generateSEOMetadata({
    title: `${job.title}${company ? ` chez ${company.display_name}` : ''}`,
    description: job.description?.slice(0, 155) || `Postulez a l'offre "${job.title}".`,
    canonical: `https://gestionemployer-backend.vercel.app/${companySlug}/careers/jobs/${job.id}`,
    ogType: 'article',
    ogImage: company?.logo_url ?? undefined,
  });
}

/**
 * Maps our JobPosting contract_type to Schema.org employmentType, matching
 * https://schema.org/JobPosting so Google Jobs can index this page.
 */
function schemaEmploymentType(contractType: string): string {
  return (
    {
      cdi: 'FULL_TIME',
      cdd: 'CONTRACTOR',
      stage: 'INTERN',
      freelance: 'TEMPORARY',
    }[contractType] ?? 'FULL_TIME'
  );
}

export default async function JobDetailPage({ params }: JobDetailPageProps) {
  const { companySlug, jobId } = await params;
  const [job, company] = await Promise.all([
    getPublicJobPosting(companySlug, jobId),
    getPublicCareersCompany(companySlug),
  ]);

  if (!job) {
    notFound();
  }

  const hasSalary = job.salary_range_min || job.salary_range_max;
  const brandColor = company?.primary_color ?? '#10B981';
  const organizationName = company?.display_name ?? companySlug;

  return (
    <div className="min-h-screen bg-white dark:bg-slate-950">
      <Navbar isDark={false} onToggleDark={() => {}} />

      <JsonLd
        data={{
          '@context': 'https://schema.org',
          '@type': 'JobPosting',
          title: job.title,
          description: job.description ?? job.title,
          datePosted: job.created_at,
          validThrough: job.closes_at ?? undefined,
          employmentType: schemaEmploymentType(job.contract_type),
          hiringOrganization: {
            '@type': 'Organization',
            name: organizationName,
            logo: company?.logo_url ?? undefined,
          },
          jobLocation: job.location
            ? {
                '@type': 'Place',
                address: { '@type': 'PostalAddress', addressLocality: job.location },
              }
            : undefined,
          ...(hasSalary
            ? {
                baseSalary: {
                  '@type': 'MonetaryAmount',
                  currency: job.currency ?? 'DZD',
                  value: {
                    '@type': 'QuantitativeValue',
                    minValue: job.salary_range_min ?? undefined,
                    maxValue: job.salary_range_max ?? undefined,
                    unitText: 'MONTH',
                  },
                },
              }
            : {}),
        }}
      />

      <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <Link
          href={`/${companySlug}/careers`}
          className="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 mb-8"
          style={{ color: brandColor }}
        >
          <ArrowLeft className="w-4 h-4" />
          Retour aux offres chez {organizationName}
        </Link>

        <h1 className="text-3xl font-black text-slate-900 dark:text-white">{job.title}</h1>

        <div className="flex flex-wrap gap-4 mt-4 text-sm text-slate-600 dark:text-slate-400">
          {job.department && (
            <span className="flex items-center gap-1.5">
              <Briefcase className="w-4 h-4" />
              {job.department.name}
            </span>
          )}
          {job.location && (
            <span className="flex items-center gap-1.5">
              <MapPin className="w-4 h-4" />
              {job.location}
            </span>
          )}
          <span className="flex items-center gap-1.5">
            <Clock className="w-4 h-4" />
            {CONTRACT_TYPE_LABELS[job.contract_type]} - {REMOTE_POLICY_LABELS[job.remote_policy]}
          </span>
          {hasSalary && (
            <span className="flex items-center gap-1.5">
              <Wallet className="w-4 h-4" />
              {job.salary_range_min ?? '?'} - {job.salary_range_max ?? '?'} {job.currency}
            </span>
          )}
        </div>

        {job.description && (
          <div className="mt-8 prose prose-slate dark:prose-invert max-w-none whitespace-pre-line">
            {job.description}
          </div>
        )}

        {job.skills_required && job.skills_required.length > 0 && (
          <div className="mt-8">
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white mb-3">Competences recherchees</h2>
            <div className="flex flex-wrap gap-2">
              {job.skills_required.map((skill) => (
                <span
                  key={skill}
                  className="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-300"
                >
                  {skill}
                </span>
              ))}
            </div>
          </div>
        )}

        <div className="mt-12 border-t border-slate-200 dark:border-slate-800 pt-10">
          <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-6">Postuler a cette offre</h2>
          <ApplyForm companySlug={companySlug} jobId={job.id} />
        </div>
      </div>

      <Footer />
    </div>
  );
}
