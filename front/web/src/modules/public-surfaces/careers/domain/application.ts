/**
 * Domaine public — verticale Emploi : la candidature.
 *
 * Le candidat n'a **pas de compte** : c'est un choix du contrat API, pas une
 * limite d'implémentation. Il retrouve ses dossiers avec l'adresse email
 * utilisée pour postuler. Ce domaine porte les règles de ce parcours.
 *
 * Aucune E/S, aucun import de framework.
 */

/** Statuts d'un dossier de candidature (`applicants.status`). */
export const APPLICATION_STATUSES = [
  'new',
  'screening',
  'interview',
  'offer',
  'hired',
  'rejected',
  'withdrawn',
] as const;

export type ApplicationStatus = (typeof APPLICATION_STATUSES)[number];

/** Une candidature telle que le suivi public la rend. */
export interface TrackedApplication {
  readonly reference: string;
  readonly jobTitle: string | null;
  readonly jobLocation: string | null;
  readonly contractType: string | null;
  readonly status: ApplicationStatus;
  readonly appliedAt: string | null;
  readonly lastUpdate: string | null;
}

/** Vrai si le code est un statut de candidature connu. */
export function isApplicationStatus(value: unknown): value is ApplicationStatus {
  return typeof value === 'string' && (APPLICATION_STATUSES as readonly string[]).includes(value);
}

/**
 * Vrai si le dossier est encore en cours.
 *
 * `hired`, `rejected` et `withdrawn` sont terminaux : le candidat n'attend plus
 * rien, et l'UI ne doit pas lui suggérer de relancer.
 */
export function isActiveApplication(status: ApplicationStatus): boolean {
  return status !== 'hired' && status !== 'rejected' && status !== 'withdrawn';
}

/**
 * Validation d'une adresse email côté domaine.
 *
 * Volontairement permissive : le serveur revalide de toute façon, et une regex
 * stricte rejette des adresses valides (TLD longs, sous-domaines). Le but est
 * seulement d'éviter un aller-retour évident.
 */
export function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

/** Champs d'une candidature, tels que le formulaire les collecte. */
export interface ApplicationDraft {
  readonly firstName: string;
  readonly lastName: string;
  readonly email: string;
  readonly phone: string;
  readonly coverLetter: string;
  readonly consent: boolean;
}

/**
 * Erreurs de validation d'un brouillon de candidature.
 *
 * Le consentement est **obligatoire** : conserver les données personnelles
 * d'un candidat sans base légale documentée serait une non-conformité RGPD,
 * pas un simple défaut d'UX. Le backend rejette en 422 ; on le dit avant
 * l'envoi.
 */
export function validateApplicationDraft(draft: ApplicationDraft): Record<string, string> {
  const errors: Record<string, string> = {};

  if (draft.firstName.trim() === '') {
    errors.firstName = 'required';
  }

  if (draft.lastName.trim() === '') {
    errors.lastName = 'required';
  }

  if (!isValidEmail(draft.email)) {
    errors.email = 'invalid';
  }

  if (!draft.consent) {
    errors.consent = 'required';
  }

  return errors;
}

/** Taille maximale du CV acceptée par l'API (5 Mo). */
export const MAX_RESUME_BYTES = 5 * 1024 * 1024;

/** Extensions de CV acceptées par l'API (`mimes:pdf,doc,docx`). */
export const RESUME_EXTENSIONS = ['.pdf', '.doc', '.docx'] as const;

/**
 * Vrai si le fichier est un CV acceptable.
 *
 * On vérifie l'extension **et** la taille : un fichier de 40 Mo ferait échouer
 * la requête après un upload complet, ce qui est la pire expérience possible
 * sur un réseau lent.
 */
export function isAcceptableResume(file: { name: string; size: number }): boolean {
  const lower = file.name.toLowerCase();
  const hasExtension = RESUME_EXTENSIONS.some((extension) => lower.endsWith(extension));

  return hasExtension && file.size > 0 && file.size <= MAX_RESUME_BYTES;
}
