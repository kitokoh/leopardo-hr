/**
 * Domaine public — verticale Emploi (Careers).
 *
 * Nouvelle verticale (#9629). Le socle API existait déjà
 * (`/public/careers*`) mais **aucune surface publique cross-tenant** ne
 * l'exposait : un candidat devait connaître le slug de l'entreprise pour voir
 * ses offres. Ce domaine porte les règles de présentation d'une offre.
 *
 * Aucune E/S, aucun import de framework.
 */

/** Types de contrat servis par l'API (`job_postings.contract_type`). */
export const CONTRACT_TYPES = ['cdi', 'cdd', 'stage', 'freelance'] as const;

export type ContractType = (typeof CONTRACT_TYPES)[number];

/** Politiques de télétravail servies par l'API. */
export const REMOTE_POLICIES = ['onsite', 'hybrid', 'remote'] as const;

export type RemotePolicy = (typeof REMOTE_POLICIES)[number];

/**
 * Une offre d'emploi telle que le site public la présente.
 *
 * `external` distingue une offre **agrégée** (importée d'un partenaire) d'une
 * offre **du tenant**. La distinction n'est pas cosmétique : on ne candidate
 * pas de la même façon. Une offre agrégée renvoie chez le partenaire ; une
 * offre du tenant se candidate sur place.
 */
export interface JobOffer {
  readonly id: number;
  readonly title: string;
  readonly description: string | null;
  readonly companySlug: string;
  readonly companyName: string;
  readonly location: string | null;
  readonly contractType: ContractType | null;
  readonly remotePolicy: RemotePolicy | null;
  readonly salaryMin: number | null;
  readonly salaryMax: number | null;
  readonly currency: string | null;
  readonly publishedAt: string | null;
  readonly closesAt: string | null;
  readonly external: boolean;
  /** URL de candidature du partenaire — renseignée seulement si `external`. */
  readonly applyUrl: string | null;
}

/** Une entreprise ayant au moins une offre publiée. */
export interface HiringCompany {
  readonly slug: string;
  readonly name: string;
  readonly country: string | null;
  readonly lastPublishedAt: string | null;
}

/** Vrai si le code est un type de contrat connu. */
export function isContractType(value: unknown): value is ContractType {
  return typeof value === 'string' && (CONTRACT_TYPES as readonly string[]).includes(value);
}

/** Vrai si le code est une politique de télétravail connue. */
export function isRemotePolicy(value: unknown): value is RemotePolicy {
  return typeof value === 'string' && (REMOTE_POLICIES as readonly string[]).includes(value);
}

/**
 * Vrai si l'offre est encore ouverte.
 *
 * Une offre sans date de clôture est considérée ouverte : c'est le contrat de
 * l'API (`closes_at` nullable), et refuser d'afficher une offre sans échéance
 * cacherait la majorité du catalogue.
 */
export function isOpen(offer: JobOffer, now: Date = new Date()): boolean {
  if (offer.closesAt === null) {
    return true;
  }

  const closes = new Date(offer.closesAt);

  return Number.isNaN(closes.getTime()) ? true : closes >= now;
}

/**
 * Fourchette salariale lisible, ou `null` si inconnue.
 *
 * On n'affiche jamais « 0 € » : une offre sans salaire renseigné n'affiche
 * rien, elle ne prétend pas payer zéro.
 */
export function salaryLabel(offer: JobOffer): string | null {
  const { salaryMin, salaryMax, currency } = offer;

  if (salaryMin === null && salaryMax === null) {
    return null;
  }

  const unit = currency ?? 'EUR';
  const format = (value: number): string =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value);

  if (salaryMin !== null && salaryMax !== null) {
    return `${format(salaryMin)} – ${format(salaryMax)} ${unit}`;
  }

  return `${format((salaryMin ?? salaryMax) as number)} ${unit}`;
}

/**
 * Type d'emploi Schema.org pour Google Jobs.
 *
 * Google attend les valeurs normalisées de schema.org (`FULL_TIME`,
 * `CONTRACTOR`, `INTERN`…), pas nos codes internes (`cdi`, `cdd`…). Envoyer
 * `cdi` ferait ignorer le champ et l'annonce perdrait son type de contrat.
 */
export function employmentType(contractType: ContractType | null): string {
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

/** Vrai si l'offre est en télétravail total (pour `jobLocationType`). */
export function isFullyRemote(offer: JobOffer): boolean {
  return offer.remotePolicy === 'remote';
}
