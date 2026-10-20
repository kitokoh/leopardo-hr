'use client';

/**
 * #8749 [BOS-CHAT] — temps réel par polling adaptatif.
 *
 * Décision d'architecture (audit §7) : **pas** de Reverb, **pas** de nouveau
 * fournisseur payant. L'hébergement actuel ne tient pas de connexion WebSocket
 * longue durée (workers bloqués, spin-down du plan gratuit), et le SSE a été
 * retiré volontairement (#7481) avec une règle opposée : un client d'abord.
 * Ce hook EST ce client.
 *
 * Comportement :
 *
 *  - **curseur** — on ne redemande jamais l'historique complet : chaque tick
 *    demande `after_seq=<dernier seq connu>` et n'insère que les nouveautés.
 *  - **adaptatif** — cadence rapide quand la conversation est active et
 *    l'onglet visible ; cadence lente quand elle ne bouge pas (moins de
 *    requêtes = moins de charge et de batterie).
 *  - **reprise après reconnexion** — après une coupure, la MÊME requête
 *    `after_seq` rapporte tout ce qui a été manqué, car le serveur a persisté
 *    les messages (aucun message perdu par construction).
 *  - **backoff** — en cas d'échec, l'intervalle double jusqu'à un plafond, et
 *    `offline` passe à `true` pour que l'UI l'affiche.
 *  - **pause** — onglet en arrière-plan (`document.hidden`) : aucun appel.
 */

import { useCallback, useEffect, useRef } from 'react';

import { fetchMessages, mergeMessages, type ChatMessage } from '@/lib/chat';

/** Cadences, en millisecondes. Volontairement peu agressives. */
const INTERVAL_ACTIVE_MS = 5_000;
const INTERVAL_IDLE_MS = 20_000;
/** Nombre de ticks sans nouveauté avant de passer en cadence lente. */
const IDLE_AFTER_TICKS = 3;
/** Plafond du backoff après échec réseau. */
const MAX_BACKOFF_MS = 60_000;

export type PollingState = 'live' | 'offline';

interface UseConversationPollingOptions {
  threadId: number;
  /** Dernier `seq` connu du client (0 si l'historique n'est pas encore chargé). */
  lastSeq: number;
  enabled?: boolean;
  onMessages: (messages: ChatMessage[], meta: { lastSeq: number }) => void;
  onStateChange?: (state: PollingState) => void;
}

/**
 * Retourne une fonction `refreshNow()` pour forcer un tick immédiat (après un
 * envoi, ou au retour de l'onglet) sans attendre le prochain intervalle.
 */
export function useConversationPolling({
  threadId,
  lastSeq,
  enabled = true,
  onMessages,
  onStateChange,
}: UseConversationPollingOptions): () => void {
  // Le curseur vit dans une ref : un tick lent ne doit pas relire une valeur
  // périmée capturée à la création du timer.
  const cursorRef = useRef<number>(lastSeq);
  const idleTicksRef = useRef<number>(0);
  const failureRef = useRef<number>(0);
  const inFlightRef = useRef<boolean>(false);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const stateRef = useRef<PollingState>('live');

  // Le parent peut faire avancer le curseur (chargement initial de l'historique).
  useEffect(() => {
    if (lastSeq > cursorRef.current) {
      cursorRef.current = lastSeq;
    }
  }, [lastSeq]);

  const callbacksRef = useRef({ onMessages, onStateChange });
  callbacksRef.current = { onMessages, onStateChange };

  const setState = useCallback((next: PollingState) => {
    if (stateRef.current === next) return;

    stateRef.current = next;
    callbacksRef.current.onStateChange?.(next);
  }, []);

  const tick = useCallback(
    async (): Promise<void> => {
      if (inFlightRef.current) return;

      inFlightRef.current = true;

      try {
        const page = await fetchMessages(threadId, {
          after_seq: cursorRef.current,
          per_page: 50,
        });

        failureRef.current = 0;
        setState('live');

        if (page.data.length > 0) {
          idleTicksRef.current = 0;
          cursorRef.current = Math.max(cursorRef.current, page.meta.last_seq);
          callbacksRef.current.onMessages(page.data, { lastSeq: cursorRef.current });
        } else {
          idleTicksRef.current += 1;
        }
      } catch {
        // Coupure réseau ou serveur indisponible. On NE remonte pas l'erreur :
        // le prochain tick réessaiera, et l'UI affiche simplement « hors ligne ».
        failureRef.current += 1;
        setState('offline');
      } finally {
        inFlightRef.current = false;
      }
    },
    [threadId, setState],
  );

  const schedule = useCallback((): void => {
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }

    if (!enabled) return;
    if (typeof document !== 'undefined' && document.hidden) return;

    let delay: number;

    if (failureRef.current > 0) {
      delay = Math.min(INTERVAL_ACTIVE_MS * 2 ** failureRef.current, MAX_BACKOFF_MS);
    } else if (idleTicksRef.current >= IDLE_AFTER_TICKS) {
      delay = INTERVAL_IDLE_MS;
    } else {
      delay = INTERVAL_ACTIVE_MS;
    }

    timerRef.current = setTimeout(async () => {
      await tick();
      schedule();
    }, delay);
  }, [enabled, tick]);

  /** Tick immédiat : après un envoi, ou au retour de visibilité. */
  const refreshNow = useCallback((): void => {
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }

    void (async () => {
      await tick();
      schedule();
    })();
  }, [tick, schedule]);

  useEffect(() => {
    if (!enabled) {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }

      return;
    }

    schedule();

    // Retour au premier plan : reprise immédiate, c'est exactement le cas
    // « téléphone verrouillé puis rallumé » du test de reconnexion.
    const onVisibility = (): void => {
      if (typeof document === 'undefined') return;

      if (document.hidden) {
        if (timerRef.current !== null) {
          clearTimeout(timerRef.current);
          timerRef.current = null;
        }
      } else {
        refreshNow();
      }
    };

    // Reconnexion réseau signalée par le navigateur : on ne réessaie pas d'attendre.
    const onOnline = (): void => refreshNow();

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('online', onOnline);

    return () => {
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('online', onOnline);

      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }
    };
  }, [enabled, schedule, refreshNow]);

  return refreshNow;
}

export { mergeMessages };
