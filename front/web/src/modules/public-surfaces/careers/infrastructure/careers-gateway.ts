/**
 * Adaptateur réseau — verticale Emploi : le contrat public `/public/careers*`.
 *
 * Seule couture vers l'API de recrutement. Le mapping est explicite champ par
 * champ : un renommage côté API casse ici, pas dans le rendu.
 *
 * ## Les deux populations d'offres
 *
 * `GET /public/careers/{companySlug}` fusionne **deux sources** : les offres du
 * tenant et les offres importées par la plateforme depuis les agrégateurs. Le
 * contrôleur les distingue par la présence de `apply_url` — une offre agrégée
 * n'a pas d'`id` de `job_postings` et renvoie chez le partenaire. On reproduit
 * cette distinction ici, sans la deviner.
 *
 * ## Contrat de panne
 *
 * `null` = API indisponible. Une liste vide est un résultat légitime (aucune
 * offre publiée) et se distingue par `[]`.
 */

import { resolveSurfaceApiBaseUrl } from '../../kernel/infrastructure/api-base';

import {
  isContractType,
  isRemotePolicy,
  type HiringCompany,
  type JobOffer,
} from '../domain/job-offer';
import {
  isApplicationStatus,
  type ApplicationStatus,
  type TrackedApplication,
} from '../domain/application';

/** Forme brute d'une offre dans le contrat public. */
interface RawJob {
  id?: number;
  title?: string;
  description?: string | null;
  location?: string | null;
  contract_type?: string | null;
  remote_policy?: string | null;
  salary_range_min?: number | null;
  salary_range_max?: number | null;
  salary_min?: number | null;
  salary_max?: number | null;
  currency?: string | null;
  created_at?: string | null;
  published_at?: string | null;
  closes_at?: string | null;
  expires_at?: string | null;
  apply_url?: string | null;
  company_name?: string | null;
}

/** Métadonnées d'entreprise renvoyées par l'API. */
interface RawCompanyMeta {
  slug?: string;
  name?: string;
  display_name?: string;
  country?: string | null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Traduit une offre brute vers le modèle de la vitrine.
 *
 * `external` est déduit de `apply_url` : c'est le seul discriminant que le
 * contrat expose, et il est fiable — une offre du tenant n'a jamais d'URL de
 * candidature externe.
 */
export function toJobOffer(
  raw: RawJob,
  companySlug: string,
  companyName: string,
): JobOffer {
  const external = typeof raw.apply_url === 'string' && raw.apply_url !== '';

  return {
    id: typeof raw.id === 'number' ? raw.id : 0,
    title: typeof raw.title === 'string' ? raw.title : '',
    description: typeof raw.description === 'string' ? raw.description : null,
    companySlug,
    companyName:
      (typeof raw.company_name === 'string' && raw.company_name) || companyName,
    location: typeof raw.location === 'string' ? raw.location : null,
    contractType: isContractType(raw.contract_type) ? raw.contract_type : null,
    remotePolicy: isRemotePolicy(raw.remote_policy) ? raw.remote_policy : null,
    // Les deux familles d'offres n'utilisent pas les mêmes noms de champs :
    // `salary_range_*` pour le tenant, `salary_*` pour l'agrégé.
    salaryMin: raw.salary_range_min ?? raw.salary_min ?? null,
    salaryMax: raw.salary_range_max ?? raw.salary_max ?? null,
    currency: typeof raw.currency === 'string' ? raw.currency : null,
    publishedAt: raw.published_at ?? raw.created_at ?? null,
    closesAt: raw.closes_at ?? raw.expires_at ?? null,
    external,
    applyUrl: external ? (raw.apply_url as string) : null,
  };
}

/** Résultat d'un chargement d'offres pour une entreprise. */
export interface CompanyJobsResult {
  readonly company: HiringCompany;
  readonly offers: readonly JobOffer[];
}

/** Page d'annuaire : entreprises + dernière page connue (#9697). */
export interface HiringDirectoryPage {
  readonly companies: readonly HiringCompany[];
  readonly lastPage: number;
}

/**
 * Charge les offres publiées d'une entreprise.
 *
 * `null` = API indisponible ou entreprise inconnue. La page distingue les deux
 * par un `notFound()` sur 404 et un état d'erreur explicite sur panne.
 */
export async function loadCompanyJobs(
  companySlug: string,
  filters: { q?: string; contractType?: string; remotePolicy?: string } = {},
): Promise<CompanyJobsResult | null> {
  const query = new URLSearchParams();

  if (filters.q) query.set('q', filters.q);
  if (filters.contractType) query.set('contract_type', filters.contractType);
  if (filters.remotePolicy) query.set('remote_policy', filters.remotePolicy);

  const qs = query.toString();

  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/careers/${encodeURIComponent(companySlug)}${qs ? `?${qs}` : ''}`,
      { headers: { Accept: 'application/json' }, next: { revalidate: 300 } },
    );

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = isRecord(payload) ? payload : {};
    const data = Array.isArray(record.data) ? record.data : [];
    const meta = isRecord(record.meta) ? record.meta : {};
    const companyMeta = (isRecord(meta.company) ? meta.company : {}) as RawCompanyMeta;

    const companyName =
      (typeof companyMeta.display_name === 'string' && companyMeta.display_name) ||
      (typeof companyMeta.name === 'string' && companyMeta.name) ||
      companySlug;

    return {
      company: {
        slug: typeof companyMeta.slug === 'string' ? companyMeta.slug : companySlug,
        name: companyName,
        country: typeof companyMeta.country === 'string' ? companyMeta.country : null,
        lastPublishedAt: null,
      },
      offers: data.map((entry) => toJobOffer(entry as RawJob, companySlug, companyName)),
    };
  } catch {
    return null;
  }
}

/**
 * Charge une offre unique.
 *
 * `null` = offre absente, non publiée, ou API indisponible.
 */
export async function loadJobOffer(
  companySlug: string,
  jobId: string | number,
): Promise<JobOffer | null> {
  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/careers/${encodeURIComponent(companySlug)}/jobs/${encodeURIComponent(String(jobId))}`,
      { headers: { Accept: 'application/json' }, next: { revalidate: 300 } },
    );

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = isRecord(payload) ? payload : {};
    const raw = (isRecord(record.data) ? record.data : record) as RawJob;
    const meta = isRecord(record.meta) ? record.meta : {};
    const companyMeta = (isRecord(meta.company) ? meta.company : {}) as RawCompanyMeta;

    const companyName =
      (typeof companyMeta.display_name === 'string' && companyMeta.display_name) ||
      (typeof companyMeta.name === 'string' && companyMeta.name) ||
      companySlug;

    return toJobOffer(raw, companySlug, companyName);
  } catch {
    return null;
  }
}

/**
 * Charge l'annuaire des entreprises qui recrutent.
 *
 * C'est **le** point qui manquait : sans lui, un candidat devait connaître le
 * slug d'une entreprise pour voir ses offres. L'endpoint existait déjà mais
 * n'alimentait que le sitemap.
 *
 * #9697 — `q` et `page` sont désormais transmis : l'endpoint les accepte depuis
 * que `/emploi` est un annuaire grand public et non plus un index de crawl.
 */
export async function loadHiringCompanies(filters: {
  q?: string;
  page?: number;
} = {}): Promise<HiringDirectoryPage | null> {
  const query = new URLSearchParams();

  if (filters.q) query.set('q', filters.q);
  if (filters.page && filters.page > 1) query.set('page', String(filters.page));

  const qs = query.toString();

  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/careers${qs ? `?${qs}` : ''}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: 600 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = isRecord(payload) ? payload : {};
    const data = Array.isArray(record.data) ? record.data : [];
    const meta = isRecord(record.meta) ? record.meta : {};

    return {
      companies: data.flatMap((entry) => {
        if (!isRecord(entry) || typeof entry.slug !== 'string' || entry.slug === '') {
          return [];
        }

        return [
          {
            slug: entry.slug,
            name: typeof entry.name === 'string' ? entry.name : entry.slug,
            country: typeof entry.country === 'string' ? entry.country : null,
            lastPublishedAt:
              typeof entry.last_published_at === 'string' ? entry.last_published_at : null,
          },
        ];
      }),
      lastPage: typeof meta.last_page === 'number' ? Math.max(1, meta.last_page) : 1,
    };
  } catch {
    return null;
  }
}

/** Résultat d'un suivi de candidature. */
export interface TrackingResult {
  readonly applications: readonly TrackedApplication[];
  readonly retentionDays: number | null;
}

/**
 * Suit les candidatures d'un candidat par email.
 *
 * POST et non GET : l'email est une donnée personnelle, un GET la ferait
 * atterrir dans les journaux d'accès et l'historique du navigateur.
 *
 * Une liste vide est la réponse normale pour un email inconnu — le backend ne
 * rend jamais 404, ce qui confirmerait « cette adresse n'a jamais postulé ».
 */
export async function trackApplications(
  companySlug: string,
  email: string,
): Promise<TrackingResult | null> {
  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/careers/${encodeURIComponent(companySlug)}/applications/track`,
      {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
        cache: 'no-store',
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = isRecord(payload) ? payload : {};
    const data = Array.isArray(record.data) ? record.data : [];
    const meta = isRecord(record.meta) ? record.meta : {};

    return {
      applications: data.flatMap((entry) => {
        if (!isRecord(entry) || typeof entry.reference !== 'string') {
          return [];
        }

        return [
          {
            reference: entry.reference,
            jobTitle: typeof entry.job_title === 'string' ? entry.job_title : null,
            jobLocation: typeof entry.job_location === 'string' ? entry.job_location : null,
            contractType: typeof entry.contract_type === 'string' ? entry.contract_type : null,
            status: (isApplicationStatus(entry.status)
              ? entry.status
              : 'new') as ApplicationStatus,
            appliedAt: typeof entry.applied_at === 'string' ? entry.applied_at : null,
            lastUpdate: typeof entry.last_update === 'string' ? entry.last_update : null,
          },
        ];
      }),
      retentionDays: typeof meta.retention_days === 'number' ? meta.retention_days : null,
    };
  } catch {
    return null;
  }
}

/** Issue d'une soumission de candidature. */
export type ApplyOutcome =
  | { readonly kind: 'created'; readonly reference: string | null }
  | { readonly kind: 'already-applied' }
  | { readonly kind: 'rejected'; readonly message: string | null }
  | { readonly kind: 'failed' };

/**
 * Soumet une candidature.
 *
 * Le formulaire est envoyé en `multipart/form-data` parce qu'il peut porter un
 * CV. Le champ piège `website` est transmis vide : le backend le déclare
 * `prohibited`, donc toute valeur non vide fait rejeter la candidature — c'est
 * l'anti-spam, et il ne doit jamais être rempli par un humain.
 */
export async function submitApplication(
  companySlug: string,
  jobId: string | number,
  draft: {
    firstName: string;
    lastName: string;
    email: string;
    phone: string;
    coverLetter: string;
    consent: boolean;
    resume: File | null;
  },
): Promise<ApplyOutcome> {
  const body = new FormData();

  body.set('first_name', draft.firstName);
  body.set('last_name', draft.lastName);
  body.set('email', draft.email);
  body.set('consent', draft.consent ? '1' : '0');
  body.set('source', 'website');
  // Champ piège : toujours vide côté humain.
  body.set('website', '');

  if (draft.phone.trim() !== '') {
    body.set('phone', draft.phone);
  }

  if (draft.coverLetter.trim() !== '') {
    body.set('cover_letter', draft.coverLetter);
  }

  if (draft.resume !== null) {
    body.set('resume', draft.resume);
  }

  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/careers/${encodeURIComponent(companySlug)}/jobs/${encodeURIComponent(String(jobId))}/apply`,
      { method: 'POST', headers: { Accept: 'application/json' }, body, cache: 'no-store' },
    );

    if (response.status === 201) {
      const payload: unknown = await response.json().catch(() => null);
      const record = isRecord(payload) ? payload : {};
      const data = isRecord(record.data) ? record.data : record;

      return {
        kind: 'created',
        reference: typeof data.reference === 'string' ? data.reference : null,
      };
    }

    if (response.status === 409) {
      return { kind: 'already-applied' };
    }

    if (response.status === 422) {
      const payload: unknown = await response.json().catch(() => null);
      const record = isRecord(payload) ? payload : {};

      return {
        kind: 'rejected',
        message: typeof record.message === 'string' ? record.message : null,
      };
    }

    return { kind: 'failed' };
  } catch {
    return { kind: 'failed' };
  }
}
