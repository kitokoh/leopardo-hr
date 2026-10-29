/**
 * Leopardo Edge — moteur de synchronisation du KDS (#8448).
 *
 * Le contrat avec l'API est volontairement étroit : un geste = un POST, avec
 * un `clientActionId` que le serveur doit traiter de façon idempotente. Ce
 * module ne connaît ni React ni le DOM, ce qui le rend testable et réutilisable
 * par la PWA comme par un futur client Flutter.
 *
 * Politique d'erreur — c'est la partie qui décide si la cuisine perd des gestes :
 *
 *   - **2xx** → acquitté, retiré de la file ;
 *   - **4xx** → refusé définitivement (ticket supprimé, transition invalide) :
 *     rejouer ne changera rien, on retire pour ne pas bloquer la file derrière
 *     un geste impossible ;
 *   - **5xx / réseau / timeout** → transitoire : on garde le geste et on
 *     incrémente les tentatives. Un 5xx n'est PAS un refus : le serveur a pu
 *     appliquer le geste avant de tomber, et l'idempotence couvre ce cas.
 *
 * La file est drainée **séquentiellement** : deux gestes sur le même ticket
 * doivent arriver dans l'ordre, sinon « prêt » pourrait précéder « commencé ».
 */

import {
  KdsQueue,
  type KdsPendingAction,
  type KdsTicket,
} from './kds-queue';

export interface KdsSyncResult {
  acknowledged: string[];
  rejected: string[];
  retry: string[];
}

export interface KdsSyncOptions {
  /** Base URL de l'API Edge (same-origin par défaut, cf. #7653). */
  baseUrl?: string;
  /** Injectable pour les tests. */
  fetcher?: typeof fetch;
  /** Timeout par geste, en ms. */
  timeoutMs?: number;
}

export const KDS_SYNC_TIMEOUT_MS = 8_000;

/** Chemin du contrat serveur. Versionné, comme tout le reste de l'Edge. */
export const KDS_ACTION_PATH = '/api/v1/restaurant/kitchen/actions';

/**
 * Envoie un geste. Retourne le statut HTTP, ou `null` si le réseau a échoué.
 *
 * `null` et un 5xx sont traités de la même façon par l'appelant (transitoire),
 * mais restent distincts ici : un test doit pouvoir dire lequel s'est produit.
 */
async function postAction(
  action: KdsPendingAction,
  options: Required<Pick<KdsSyncOptions, 'baseUrl' | 'fetcher' | 'timeoutMs'>>,
): Promise<number | null> {
  try {
    const response = await options.fetcher(`${options.baseUrl}${KDS_ACTION_PATH}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        client_action_id: action.clientActionId,
        order_id: action.orderId,
        action: action.kind,
      }),
      signal: AbortSignal.timeout(options.timeoutMs),
    });

    return response.status;
  } catch {
    return null;
  }
}

/**
 * Vide la file vers le serveur, dans l'ordre.
 *
 * S'arrête au premier échec transitoire : continuer enverrait des gestes
 * postérieurs avant leurs prédécesseurs, ce qui inverserait les transitions.
 */
export async function drainQueue(
  queue: KdsQueue,
  options: KdsSyncOptions = {},
): Promise<KdsSyncResult> {
  const resolved = {
    baseUrl: options.baseUrl ?? '',
    fetcher: options.fetcher ?? fetch,
    timeoutMs: options.timeoutMs ?? KDS_SYNC_TIMEOUT_MS,
  };

  const result: KdsSyncResult = { acknowledged: [], rejected: [], retry: [] };
  const pending = queue.load().pending;

  for (const action of pending) {
    const status = await postAction(action, resolved);

    if (status !== null && status >= 200 && status < 300) {
      queue.acknowledge(action.clientActionId);
      result.acknowledged.push(action.clientActionId);
      continue;
    }

    if (status !== null && status >= 400 && status < 500) {
      queue.reject(action.clientActionId);
      result.rejected.push(action.clientActionId);
      continue;
    }

    // Transitoire : on garde le geste et on s'arrête là pour préserver l'ordre.
    queue.markAttempt(action.clientActionId);
    result.retry.push(action.clientActionId);
    break;
  }

  return result;
}

/**
 * Récupère la file cuisine du serveur.
 *
 * Retourne `null` en cas d'échec — l'appelant garde alors son état local, ce
 * qui est exactement le comportement voulu hors ligne : on n'efface pas les
 * tickets parce que le réseau est tombé.
 */
export async function fetchKitchenTickets(
  branchId: number,
  options: KdsSyncOptions = {},
): Promise<KdsTicket[] | null> {
  const baseUrl = options.baseUrl ?? '';
  const fetcher = options.fetcher ?? fetch;
  const timeoutMs = options.timeoutMs ?? KDS_SYNC_TIMEOUT_MS;

  try {
    const response = await fetcher(
      `${baseUrl}/api/v1/restaurant/kitchen/orders?branch_id=${encodeURIComponent(String(branchId))}`,
      { signal: AbortSignal.timeout(timeoutMs) },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: unknown };

    return Array.isArray(payload.data) ? (payload.data as KdsTicket[]) : null;
  } catch {
    return null;
  }
}
