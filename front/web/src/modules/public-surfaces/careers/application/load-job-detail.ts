/**
 * Cas d'usage — verticale Emploi : la fiche d'une offre.
 *
 * Charge l'offre et prépare son affichage, y compris les données structurées
 * Schema.org que Google Jobs exige. Ne lève jamais.
 */

import { employmentType, isFullyRemote, salaryLabel, type JobOffer } from '../domain/job-offer';
import { loadJobOffer } from '../infrastructure/careers-gateway';
import { buildJobCard, type JobCard } from './load-jobs';

/** Fiche d'offre prête à l'affichage. */
export interface JobDetailView {
  readonly card: JobCard;
  readonly offer: JobOffer;
}

/** Charge une fiche d'offre ; `null` si absente ou API indisponible. */
export async function loadJobDetailView(
  companySlug: string,
  jobId: string | number,
  locale?: string,
): Promise<JobDetailView | null> {
  const offer = await loadJobOffer(companySlug, jobId);

  if (offer === null) {
    return null;
  }

  return { card: buildJobCard(offer, locale), offer };
}

/**
 * Données structurées `JobPosting` pour Google Jobs.
 *
 * `directApply` est explicite : une offre agrégée se candidate chez le
 * partenaire, pas ici. L'omettre laisserait Google croire à une candidature
 * directe et dégraderait l'annonce.
 */
export function jobPostingJsonLd(
  offer: JobOffer,
  canonicalUrl: string,
): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'JobPosting',
    title: offer.title,
    description: offer.description ?? offer.title,
    datePosted: offer.publishedAt ?? undefined,
    validThrough: offer.closesAt ?? undefined,
    employmentType: employmentType(offer.contractType),
    hiringOrganization: {
      '@type': 'Organization',
      name: offer.companyName,
    },
    jobLocation: {
      '@type': 'Place',
      address: {
        '@type': 'PostalAddress',
        addressLocality: offer.location ?? undefined,
      },
    },
    ...(isFullyRemote(offer) ? { jobLocationType: 'TELECOMMUTE' } : {}),
    ...(salaryLabel(offer) !== null
      ? {
          baseSalary: {
            '@type': 'MonetaryAmount',
            currency: offer.currency ?? 'EUR',
            value: {
              '@type': 'QuantitativeValue',
              minValue: offer.salaryMin ?? undefined,
              maxValue: offer.salaryMax ?? undefined,
              unitText: 'YEAR',
            },
          },
        }
      : {}),
    url: offer.external ? (offer.applyUrl ?? canonicalUrl) : canonicalUrl,
    directApply: !offer.external,
  };
}
