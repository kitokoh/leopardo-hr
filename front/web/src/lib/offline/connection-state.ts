/**
 * #8493 / #8494 (I-06, I-07) — etat de connexion explicite.
 *
 * Partie 7 de la mission : « etat de connexion clair ». Fonction PURE : derive
 * l'etat affichable a partir de signaux observables, sans toucher au DOM —
 * donc testable en Node, et reutilisable par le POS comme par le KDS.
 */

export type ConnectionState = 'online' | 'offline' | 'degraded';

export interface ConnectionSignals {
  /** `navigator.onLine` (ou equivalent injecte). */
  navigatorOnline: boolean;
  /** Le dernier cycle de synchronisation a-t-il echoue ? */
  lastSyncFailed: boolean;
  /** Reste-t-il des operations en attente ? */
  hasPendingOperations: boolean;
}

/**
 * - `offline`  : le navigateur se declare hors ligne ;
 * - `degraded` : en ligne mais synchronisation en echec ou operations en
 *   attente (l'utilisateur doit le voir, sans etre bloque) ;
 * - `online`   : en ligne et a jour.
 */
export function deriveConnectionState(signals: ConnectionSignals): ConnectionState {
  if (!signals.navigatorOnline) {
    return 'offline';
  }
  if (signals.lastSyncFailed || signals.hasPendingOperations) {
    return 'degraded';
  }
  return 'online';
}

/** Peut-on tenter une synchronisation maintenant ? (jamais hors ligne.) */
export function canSync(state: ConnectionState): boolean {
  return state !== 'offline';
}
