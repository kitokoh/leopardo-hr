/**
 * #8454 — métier présélectionné à l'inscription vitrine.
 *
 * Un visiteur qui s'inscrit depuis une page métier (`/packs/station-service`,
 * `/restaurateur`…) arrive avec un `?sector=` dans l'URL : on le capture au
 * montage du formulaire d'inscription (sessionStorage — il survit au tunnel
 * OTP/login dans le même onglet, contrairement au query param) et l'entretien
 * de préparation le CONSOMME à la première connexion pour pré-répondre la
 * question « secteur » au lieu de la reposer (#7493).
 *
 * Fail-closed : la valeur est validée contre l'allowlist miroir de
 * `SetupInterviewPlanner::QUESTIONS['sector']` — toute valeur inconnue est
 * ignorée (jamais de réponse d'entretien forgée hors liste).
 */

export const SIGNUP_SECTOR_STORAGE_KEY = 'leopardo_signup_sector';

/** Miroir strict de l'allowlist serveur (`SetupInterviewPlanner::QUESTIONS`). */
export const SIGNUP_SECTORS = [
  'restaurant',
  'fuel_station',
  'education',
  'commerce',
  'services',
  'travel',
  'association',
  'other',
] as const;

export type SignupSector = (typeof SIGNUP_SECTORS)[number];

export function isSignupSector(value: string | null | undefined): value is SignupSector {
  return typeof value === 'string' && (SIGNUP_SECTORS as readonly string[]).includes(value);
}

/** Lit et valide le `?sector=` d'une query string vitrine (null si absent/invalide). */
export function readSignupSectorFromUrl(search: string): SignupSector | null {
  const value = new URLSearchParams(search).get('sector');
  return isSignupSector(value) ? value : null;
}

/** Capture au montage du formulaire d'inscription (survit au tunnel OTP). */
export function persistSignupSector(sector: SignupSector): void {
  try {
    window.sessionStorage.setItem(SIGNUP_SECTOR_STORAGE_KEY, sector);
  } catch {
    // sessionStorage indisponible (navigation privée) — l'entretien posera
    // simplement la question : la présélection est un confort, jamais un blocage.
  }
}

/** Lecture sans consommation (l'entretien décide de l'usage). */
export function peekSignupSector(): SignupSector | null {
  try {
    const value = window.sessionStorage.getItem(SIGNUP_SECTOR_STORAGE_KEY);
    return isSignupSector(value) ? value : null;
  } catch {
    return null;
  }
}

/** Effacement après consommation par l'entretien (jamais ré-appliqué deux fois). */
export function clearSignupSector(): void {
  try {
    window.sessionStorage.removeItem(SIGNUP_SECTOR_STORAGE_KEY);
  } catch {
    // Même direction d'échec sûre que la persistance.
  }
}
