'use client';

/**
 * #8493 / #8494 (I-06, I-07) — hook React partagé POS / KDS.
 *
 * Branche le NOYAU hors-ligne (`offline-queue.ts`, `connection-state.ts`) sur
 * un écran réel : expose l'état de connexion affichable, la file d'opérations
 * en attente, et une fonction `dispatch` qui, hors ligne, met l'opération en
 * file au lieu de l'envoyer — puis la rejoue à la reconnexion.
 *
 * Exigences de la mission (Partie 7) rendues vérifiables ici :
 *  - état de connexion clair (`deriveConnectionState`) ;
 *  - opérations hors ligne enregistrées localement et VISIBLES (`pending`) ;
 *  - reprise de synchronisation à la reconnexion (`online` event) ;
 *  - traitements IDEMPOTENTS : la clé d'idempotence est stable
 *    (`makeOperationId`) → un rejeu ne crée pas de doublon ;
 *  - erreurs diagnostiquables (`lastError` conservée).
 *
 * Le hook est volontairement mince : toute la logique testable vit dans les
 * modules purs (`offline-queue`, `connection-state`), testés en Node. Ici on
 * ne fait que le câblage DOM (événements online/offline) et l'état React.
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  OfflineQueue,
  makeOperationId,
  type OfflineOperation,
  type OfflineStorage,
} from '@/lib/offline/offline-queue';
import {
  PersistentOfflineStorage,
  browserStorage,
  storageKey,
} from '@/lib/offline/persistent-offline-storage';
import {
  canSync,
  deriveConnectionState,
  type ConnectionState,
} from '@/lib/offline/connection-state';

/** Envoie réellement une opération au serveur. Doit lever en cas d'échec. */
export type OfflineSender = (operation: OfflineOperation) => Promise<void>;

export interface UseOfflineSyncResult {
  connectionState: ConnectionState;
  /** Opérations en attente ou en échec (visibles à l'écran). */
  pending: OfflineOperation[];
  /** Nombre d'opérations en attente. */
  pendingCount: number;
  /**
   * Exécute une opération : en ligne → envoi immédiat ; hors ligne → mise en
   * file. Idempotent : deux appels identiques produisent la même clé.
   */
  dispatch: (kind: string, payload: unknown) => Promise<'sent' | 'queued' | 'duplicate'>;
  /** Force une tentative de rejeu (bouton « Réessayer »). */
  flush: () => Promise<void>;
  /**
   * #8448 — `false` si la file n'est PAS persistée (stockage indisponible ou
   * plein). L'écran doit le dire : une file en mémoire ne survit pas à un
   * rafraîchissement, contrairement à ce que le cuisinier suppose.
   */
  isDurable: boolean;
}

/**
 * Options du hook.
 *
 * `storage` est injectable : cela permet de tester la durabilité (rechargement
 * simulé) sans dépendre d'un vrai navigateur, et de choisir la station
 * (`station`) pour qu'un second écran cuisine ne vole pas les opérations du
 * premier sur le même navigateur.
 */
export interface UseOfflineSyncOptions {
  storage?: OfflineStorage;
  station?: string;
}

export function useOfflineSync(
  sender: OfflineSender,
  options: UseOfflineSyncOptions = {},
): UseOfflineSyncResult {
  // #8448 — la file est persistée dès qu'un stockage est disponible. Sans
  // persistance, un rafraîchissement pendant la coupure perdrait les gestes
  // déjà saisis : le critère « resync sans perte » ne serait pas tenu.
  const durableRef = useRef<PersistentOfflineStorage | null>(null);
  const queueRef = useRef<OfflineQueue | null>(null);
  if (queueRef.current === null) {
    let storage: OfflineStorage;
    if (options.storage !== undefined) {
      storage = options.storage;
    } else {
      durableRef.current = new PersistentOfflineStorage(
        browserStorage(),
        storageKey(undefined, options.station ?? 'default'),
      );
      storage = durableRef.current;
    }
    queueRef.current = new OfflineQueue(storage);
  }
  const queue = queueRef.current;

  const [navigatorOnline, setNavigatorOnline] = useState(true);
  const [lastSyncFailed, setLastSyncFailed] = useState(false);
  const [pending, setPending] = useState<OfflineOperation[]>([]);
  const [tick, setTick] = useState(0);

  const refresh = useCallback(() => {
    setPending(queue.pending());
  }, [queue]);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    setNavigatorOnline(window.navigator.onLine);
    const onOnline = () => setNavigatorOnline(true);
    const onOffline = () => setNavigatorOnline(false);
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    return () => {
      window.removeEventListener('online', onOnline);
      window.removeEventListener('offline', onOffline);
    };
  }, []);

  const flush = useCallback(async () => {
    const state = deriveConnectionState({
      navigatorOnline,
      lastSyncFailed: false,
      hasPendingOperations: queue.pending().length > 0,
    });
    if (!canSync(state)) {
      return;
    }
    let failed = false;
    for (const op of queue.pending()) {
      queue.markSyncing(op.id);
      try {
        await sender(op);
        queue.markSynced(op.id);
      } catch (error) {
        failed = true;
        queue.markFailed(op.id, error instanceof Error ? error.message : 'sync_failed');
      }
    }
    setLastSyncFailed(failed);
    refresh();
    setTick((t) => t + 1);
  }, [navigatorOnline, queue, refresh, sender]);

  // Reprise automatique à la reconnexion.
  useEffect(() => {
    if (navigatorOnline) {
      void flush();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [navigatorOnline]);

  const dispatch = useCallback(
    async (kind: string, payload: unknown): Promise<'sent' | 'queued' | 'duplicate'> => {
      const id = makeOperationId(kind, payload);
      const state = deriveConnectionState({
        navigatorOnline,
        lastSyncFailed,
        hasPendingOperations: queue.pending().length > 0,
      });

      if (!canSync(state)) {
        const result = queue.enqueue(kind, payload, id);
        refresh();
        return result === 'duplicate' ? 'duplicate' : 'queued';
      }

      // En ligne : envoi immédiat, mais on passe par la file pour garantir
      // l'idempotence (un double-clic ne crée pas deux opérations).
      const result = queue.enqueue(kind, payload, id);
      if (result === 'duplicate') {
        return 'duplicate';
      }
      queue.markSyncing(id);
      try {
        await sender({ id, kind, payload, createdAt: Date.now(), attempts: 0, status: 'syncing' });
        queue.markSynced(id);
        refresh();
        return 'sent';
      } catch (error) {
        queue.markFailed(id, error instanceof Error ? error.message : 'send_failed');
        setLastSyncFailed(true);
        refresh();
        return 'queued';
      }
    },
    [navigatorOnline, lastSyncFailed, queue, refresh, sender],
  );

  const connectionState = useMemo(
    () =>
      deriveConnectionState({
        navigatorOnline,
        lastSyncFailed,
        hasPendingOperations: pending.length > 0,
      }),
    [navigatorOnline, lastSyncFailed, pending.length],
  );

  return {
    connectionState,
    pending,
    pendingCount: pending.length,
    dispatch,
    flush,
    isDurable: durableRef.current === null
      ? options.storage !== undefined
      : durableRef.current.lastStorageIssue === null || durableRef.current.lastStorageIssue === 'corrupt',
  };
}
