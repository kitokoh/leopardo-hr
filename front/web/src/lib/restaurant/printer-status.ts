/**
 * REST-OS-5 / #8607 — Dérivation de l'état d'imprimante affiché sur l'écran
 * cuisine.
 *
 * L'écran cuisine ne peut PAS interroger directement le bridge d'impression :
 * `GET /restaurant/printing/bridge/health` est authentifié par jeton d'appareil
 * (`restaurant.print.bridge`, fail-closed) et n'est pas exposé au navigateur du
 * cuisinier. L'état affiché est donc **dérivé des travaux d'impression** de la
 * branche (`GET /restaurant/print-jobs?branch_id=X`), seule source réellement
 * accessible et tenant-scopée.
 *
 * Trois états, jamais un quatrième « optimiste » :
 *   - `ready`          : au moins un travail porte un `printer_ref` (une
 *                        imprimante est configurée) et aucun travail n'est
 *                        bloqué au-delà du seuil ;
 *   - `offline`        : un travail `pending`/`claimed` dépasse le seuil sans
 *                        accusé — l'imprimante ne répond pas ;
 *   - `not_configured` : aucun travail ne porte de `printer_ref` (ou aucun
 *                        travail du tout) — on ne peut PAS affirmer qu'une
 *                        imprimante est prête.
 *
 * Fail-closed : sans preuve d'une imprimante configurée, on n'affiche jamais
 * `ready`. C'est la même exigence que `isOfflineClaimed()` côté vitrine : ne
 * jamais promettre un état qu'on ne peut pas prouver.
 */

export type PrinterStatus = 'ready' | 'offline' | 'not_configured';

/** Travail d'impression tel que renvoyé par `RestaurantPrintJobResource`. */
export interface PrintJobLike {
  id: number;
  status: string;
  printer_ref: string | null;
  attempts: number;
  last_error: string | null;
  created_at: string;
}

/**
 * Délai au-delà duquel un travail `pending`/`claimed` signale une imprimante
 * muette. 5 minutes : bien au-delà d'un cycle d'impression normal, assez court
 * pour que le cuisinier voie le problème pendant le service.
 */
export const PRINTER_OFFLINE_THRESHOLD_MS = 5 * 60 * 1000;

function isConfigured(job: PrintJobLike): boolean {
  return job.printer_ref !== null && job.printer_ref !== '';
}

function isStuck(job: PrintJobLike, now: number): boolean {
  if (job.status !== 'pending' && job.status !== 'claimed') {
    return false;
  }
  const created = Date.parse(job.created_at);
  if (Number.isNaN(created)) {
    return false;
  }
  return now - created > PRINTER_OFFLINE_THRESHOLD_MS;
}

/**
 * Dérive l'état d'imprimante à afficher.
 *
 * @param jobs Travaux de la branche (les plus récents d'abord, comme l'API).
 * @param now  Horodatage courant (injectable pour les tests).
 */
export function derivePrinterStatus(jobs: PrintJobLike[], now: number = Date.now()): PrinterStatus {
  if (jobs.length === 0) {
    return 'not_configured';
  }

  if (!jobs.some(isConfigured)) {
    return 'not_configured';
  }

  if (jobs.some((job) => isStuck(job, now))) {
    return 'offline';
  }

  return 'ready';
}

/** Travaux en échec — ceux qui portent l'action « Relancer l'impression ». */
export function failedPrintJobs(jobs: PrintJobLike[]): PrintJobLike[] {
  return jobs.filter((job) => job.status === 'failed');
}

/**
 * Un travail peut-il être relancé ? Un travail `failed` ou `cancelled` oui ;
 * un travail déjà `printed` non (on ne réimprime pas un ticket abouti sans
 * geste explicite distinct).
 */
export function canRequeue(job: PrintJobLike): boolean {
  return job.status === 'failed' || job.status === 'cancelled';
}
