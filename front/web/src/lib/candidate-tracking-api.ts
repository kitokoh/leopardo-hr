/**
 * Client du suivi de candidature public (ATS #9461).
 *
 * Distinct de `careers-api.ts` : celui-ci ne fait que de la lecture d'offres et
 * de la soumission de candidature, avec un cache Next de 5 minutes. Ici rien ne
 * doit être mis en cache — un candidat qui consulte son statut veut le statut
 * d'aujourd'hui, pas celui d'il y a cinq minutes.
 */

export interface TrackedApplication {
  reference: string;
  job_title: string | null;
  job_location: string | null;
  contract_type: string | null;
  status: string;
  applied_at: string | null;
  last_update: string | null;
}

export interface TrackApplicationsResult {
  success: boolean;
  applications: TrackedApplication[];
  retentionDays: number | null;
  status?: number;
  message?: string;
}

/**
 * Interroge le suivi pour une adresse email.
 *
 * POST et non GET : l'email est une donnée personnelle, un GET la ferait
 * atterrir dans l'historique du navigateur et les journaux d'accès.
 *
 * Passe par le proxy same-origin `/api/v1` comme le formulaire de candidature :
 * le navigateur n'a pas à connaître l'URL du backend.
 */
export async function trackApplications(
  companySlug: string,
  email: string
): Promise<TrackApplicationsResult> {
  let response: Response;

  try {
    response = await fetch(
      `/api/v1/public/careers/${encodeURIComponent(companySlug)}/applications/track`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email }),
        cache: 'no-store',
      }
    );
  } catch {
    // Panne réseau : on ne prétend pas que le candidat n'a pas de candidature.
    return { success: false, applications: [], retentionDays: null };
  }

  if (response.status === 429) {
    return {
      success: false,
      applications: [],
      retentionDays: null,
      status: 429,
      message: 'TOO_MANY_REQUESTS',
    };
  }

  if (!response.ok) {
    return { success: false, applications: [], retentionDays: null, status: response.status };
  }

  const payload = (await response.json()) as {
    data?: TrackedApplication[];
    meta?: { retention_days?: number };
  };

  return {
    success: true,
    applications: payload.data ?? [],
    retentionDays: payload.meta?.retention_days ?? null,
  };
}
