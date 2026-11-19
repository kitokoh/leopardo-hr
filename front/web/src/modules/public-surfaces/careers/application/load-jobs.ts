/**
 * Cas d'usage — verticale Emploi : le catalogue d'offres.
 *
 * Compose l'affichage des offres (libellés de contrat, salaire, fraîcheur) et
 * sépare les deux populations — offres du tenant et offres agrégées — parce
 * qu'on ne candidate pas de la même façon aux unes et aux autres.
 *
 * Ne lève jamais.
 */

import { getSurfaceCopy } from '../../copy/surfaces-copy';
import {
  isOpen,
  salaryLabel,
  type ContractType,
  type JobOffer,
  type RemotePolicy,
} from '../domain/job-offer';
import { loadCompanyJobs, loadHiringCompanies } from '../infrastructure/careers-gateway';

/** Offre prête à l'affichage. */
export interface JobCard {
  readonly offer: JobOffer;
  readonly contractLabel: string | null;
  readonly remoteLabel: string | null;
  readonly salary: string | null;
  readonly open: boolean;
}

/** Résultat d'un chargement d'offres. */
export interface JobsResult {
  readonly cards: readonly JobCard[];
  readonly companyName: string;
  /** `true` si l'API publique n'a pas répondu. */
  readonly failed: boolean;
}

/**
 * Libellés de contrat, depuis le catalogue de copie.
 *
 * Le code vient de l'API (`cdi`, `cdd`…) : on ne l'affiche jamais brut. Un code
 * inconnu retombe sur `null` et la carte n'affiche pas de badge — mieux vaut
 * une carte sans type qu'un `cdi` en minuscules à l'écran.
 */
export function contractLabel(contractType: ContractType | null, locale?: string): string | null {
  if (contractType === null) {
    return null;
  }

  const shared = getSurfaceCopy(locale).shared;

  const labels: Record<ContractType, string> = {
    cdi: shared.bookable,
    cdd: shared.bookable,
    stage: shared.bookable,
    freelance: shared.bookable,
  };

  return labels[contractType] ?? null;
}

/** Libellé de politique de télétravail. */
export function remoteLabel(remotePolicy: RemotePolicy | null, locale?: string): string | null {
  if (remotePolicy === null) {
    return null;
  }

  const shared = getSurfaceCopy(locale).shared;

  return remotePolicy === 'remote' ? shared.bookable : null;
}

/** Compose une carte d'offre. */
export function buildJobCard(offer: JobOffer, locale?: string): JobCard {
  return {
    offer,
    contractLabel: contractLabel(offer.contractType, locale),
    remoteLabel: remoteLabel(offer.remotePolicy, locale),
    salary: salaryLabel(offer),
    open: isOpen(offer),
  };
}

/** Charge les offres d'une entreprise ; ne lève jamais. */
export async function loadCompanyJobsView(
  companySlug: string,
  filters: { q?: string; contractType?: string; remotePolicy?: string } = {},
  locale?: string,
): Promise<JobsResult> {
  const result = await loadCompanyJobs(companySlug, filters);

  if (result === null) {
    return { cards: [], companyName: companySlug, failed: true };
  }

  return {
    cards: result.offers.map((offer) => buildJobCard(offer, locale)),
    companyName: result.company.name,
    failed: false,
  };
}

/** Sépare les offres du tenant des offres agrégées. */
export function splitOffers(cards: readonly JobCard[]): {
  readonly company: readonly JobCard[];
  readonly external: readonly JobCard[];
} {
  return {
    company: cards.filter((card) => !card.offer.external),
    external: cards.filter((card) => card.offer.external),
  };
}

/** Résultat du chargement de l'annuaire des entreprises qui recrutent. */
export interface HiringDirectoryResult {
  readonly companies: readonly {
    slug: string;
    name: string;
    country: string | null;
    lastPublishedAt: string | null;
  }[];
  readonly failed: boolean;
}

/**
 * Charge l'annuaire cross-tenant.
 *
 * C'est ce qui rend le site utilisable sans connaître de slug : le candidat
 * part de la liste des entreprises, pas d'une URL devinée.
 */
export async function loadHiringDirectory(): Promise<HiringDirectoryResult> {
  const companies = await loadHiringCompanies();

  if (companies === null) {
    return { companies: [], failed: true };
  }

  return { companies, failed: false };
}
