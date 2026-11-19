/**
 * Verticale Emploi (Careers) — surface publique de recherche d'emploi.
 *
 * ## Ce que cette verticale apporte (#9629)
 *
 * Le socle API existait déjà : `GET /public/careers` (annuaire),
 * `GET /public/careers/{companySlug}` (offres), `POST .../apply`,
 * `POST .../applications/track`, flux XML pour Google Jobs. L'espace pro de
 * publication existait aussi (`/recruitment` du dashboard tenant).
 *
 * Ce qui manquait : **une surface publique cross-tenant**. Un candidat devait
 * connaître le slug d'une entreprise pour voir ses offres, et `GET
 * /public/careers` n'alimentait que le sitemap. Cette verticale est le point
 * d'entrée qui manquait.
 *
 * ## Périmètre exclu
 *
 * - Pas de compte candidat : le contrat API n'en a pas, le suivi se fait par
 *   email. On ne simule pas un espace candidat qui n'existe pas.
 * - Pas de bandeau « Devenir tenant » : il n'existe pas de pack d'inscription
 *   recrutement (cf. `kernel/domain/tenant-invitation.ts`).
 */

export { CAREERS_SURFACE } from './identity/careers-identity';

export {
  CONTRACT_TYPES,
  REMOTE_POLICIES,
  employmentType,
  isContractType,
  isFullyRemote,
  isOpen,
  isRemotePolicy,
  salaryLabel,
  type ContractType,
  type HiringCompany,
  type JobOffer,
  type RemotePolicy,
} from './domain/job-offer';

export {
  APPLICATION_STATUSES,
  MAX_RESUME_BYTES,
  RESUME_EXTENSIONS,
  isAcceptableResume,
  isActiveApplication,
  isApplicationStatus,
  isValidEmail,
  validateApplicationDraft,
  type ApplicationDraft,
  type ApplicationStatus,
  type TrackedApplication,
} from './domain/application';

export {
  buildJobCard,
  contractLabel,
  loadCompanyJobsView,
  loadHiringDirectory,
  remoteLabel,
  splitOffers,
  type HiringDirectoryResult,
  type JobCard,
  type JobsResult,
} from './application/load-jobs';

export {
  jobPostingJsonLd,
  loadJobDetailView,
  type JobDetailView,
} from './application/load-job-detail';

export {
  applicationStatusLabel,
  buildApplicationView,
  loadApplicationTrackingView,
  type ApplicationView,
  type TrackingView,
} from './application/load-tracking';

export {
  loadCompanyJobs,
  loadHiringCompanies,
  loadJobOffer,
  submitApplication,
  toJobOffer,
  trackApplications,
  type ApplyOutcome,
  type CompanyJobsResult,
  type TrackingResult,
} from './infrastructure/careers-gateway';

export { CareersDirectorySurface } from './components/CareersDirectorySurface';
export { CompanyJobsSurface } from './components/CompanyJobsSurface';
export { JobDetailSurface } from './components/JobDetailSurface';
export { ApplyForm } from './components/ApplyForm';
export { TrackApplicationsForm } from './components/TrackApplicationsForm';
