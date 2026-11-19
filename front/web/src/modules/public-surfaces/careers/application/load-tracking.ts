/**
 * Cas d'usage — verticale Emploi : le suivi de candidature.
 *
 * Le candidat n'a pas de compte : il retrouve ses dossiers par email. Ce
 * fichier compose l'affichage (statuts, dates, conservation RGPD).
 *
 * Ne lève jamais.
 */

import { getSurfaceCopy } from '../../copy/surfaces-copy';
import { isActiveApplication, type ApplicationStatus, type TrackedApplication } from '../domain/application';
import { trackApplications } from '../infrastructure/careers-gateway';

/** Candidature prête à l'affichage. */
export interface ApplicationView {
  readonly reference: string;
  readonly jobTitle: string;
  readonly jobLocation: string | null;
  readonly status: ApplicationStatus;
  readonly statusLabel: string;
  readonly active: boolean;
  readonly appliedAt: string | null;
  readonly lastUpdate: string | null;
}

/** Résultat d'un suivi. */
export interface TrackingView {
  readonly applications: readonly ApplicationView[];
  readonly retentionDays: number | null;
  /** `true` si l'API n'a pas répondu (distinct d'un email sans candidature). */
  readonly failed: boolean;
}

/**
 * Libellé de statut, depuis le catalogue de copie.
 *
 * Un code inconnu retombe sur le code lui-même : un statut doit rester visible
 * pour être signalé, jamais remplacé par une chaîne vide.
 */
export function applicationStatusLabel(status: ApplicationStatus, locale?: string): string {
  const shared = getSurfaceCopy(locale).shared;

  const labels: Record<ApplicationStatus, string> = {
    new: shared.bookable,
    screening: shared.bookable,
    interview: shared.bookable,
    offer: shared.bookable,
    hired: shared.bookable,
    rejected: shared.soldOut,
    withdrawn: shared.soldOut,
  };

  return labels[status] ?? status;
}

/** Compose l'affichage d'une candidature. */
export function buildApplicationView(
  application: TrackedApplication,
  locale?: string,
): ApplicationView {
  return {
    reference: application.reference,
    jobTitle: application.jobTitle ?? '—',
    jobLocation: application.jobLocation,
    status: application.status,
    statusLabel: applicationStatusLabel(application.status, locale),
    active: isActiveApplication(application.status),
    appliedAt: application.appliedAt,
    lastUpdate: application.lastUpdate,
  };
}

/** Charge le suivi d'un candidat ; ne lève jamais. */
export async function loadApplicationTrackingView(
  companySlug: string,
  email: string,
  locale?: string,
): Promise<TrackingView> {
  const result = await trackApplications(companySlug, email);

  if (result === null) {
    return { applications: [], retentionDays: null, failed: true };
  }

  return {
    applications: result.applications.map((application) =>
      buildApplicationView(application, locale),
    ),
    retentionDays: result.retentionDays,
    failed: false,
  };
}
