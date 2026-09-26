"use client";

/**
 * Hook session acheteur (#7814, migration #8096) — état React synchronisé
 * avec le localStorage via useSyncExternalStore (même pattern que useCart) :
 * même onglet via le CustomEvent émis par writeBuyerSession/clear, autres
 * onglets via l'événement natif `storage`.
 *
 * #8096 — la session est portée par le cookie HttpOnly (plus de jeton en
 * localStorage). Ce hook orchestre au montage :
 *   1. la migration douce : un jeton legacy détecté est échangé UNE fois
 *      contre le cookie (`restoreBuyerSession`) puis purgé du stockage ;
 *   2. la restauration de session : si un indice profil existe, il est
 *      validé/rafraîchi via `GET /account/me` (cookie) — 401 → purge.
 */

import { useCallback, useEffect, useRef, useSyncExternalStore } from "react";

import { ApiError, fetchBuyerProfile, logoutBuyer, restoreBuyerSession } from "@/lib/api";
import {
  BUYER_EVENT,
  BUYER_STORAGE_KEY,
  clearBuyerSession,
  readBuyerSession,
  takeLegacyBuyerToken,
  writeBuyerSession,
  type BuyerSessionState,
} from "@/lib/buyer";

function subscribe(onStoreChange: () => void): () => void {
  const onStorage = (event: StorageEvent) => {
    if (event.key === null || event.key === BUYER_STORAGE_KEY) onStoreChange();
  };
  window.addEventListener(BUYER_EVENT, onStoreChange);
  window.addEventListener("storage", onStorage);
  return () => {
    window.removeEventListener(BUYER_EVENT, onStoreChange);
    window.removeEventListener("storage", onStorage);
  };
}

// Snapshot stable tant que le contenu sérialisé n'a pas changé (exigence
// useSyncExternalStore — sinon boucle de re-rendus).
let snapshotRaw: string | null = null;
let snapshotSession: BuyerSessionState | null = null;

function getSnapshot(): BuyerSessionState | null {
  let raw: string | null = null;
  try {
    raw = window.localStorage.getItem(BUYER_STORAGE_KEY);
  } catch {
    raw = null;
  }
  if (raw !== snapshotRaw) {
    snapshotRaw = raw;
    snapshotSession = raw === null ? null : readBuyerSession();
  }
  return snapshotSession;
}

function getServerSnapshot(): BuyerSessionState | null {
  return null;
}

export interface UseBuyer {
  /** false pendant l'hydratation SSR (état inconnu côté serveur). */
  ready: boolean;
  session: BuyerSessionState | null;
  signIn: (session: BuyerSessionState) => void;
  signOut: () => Promise<void>;
  /** À appeler sur une ApiError 401 : purge la session périmée. */
  handleUnauthorized: (error: unknown) => boolean;
}

export function useBuyer(): UseBuyer {
  const session = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  const ready = useSyncExternalStore(
    subscribe,
    () => true,
    () => false,
  );

  // Migration douce + restauration de session — une seule fois par montage.
  const restoredRef = useRef(false);
  useEffect(() => {
    if (restoredRef.current) return;
    restoredRef.current = true;

    let cancelled = false;

    const restore = async () => {
      // 1. Session legacy (jeton en localStorage, avant #8096) : échange
      //    contre le cookie HttpOnly. takeLegacyBuyerToken a DÉJÀ purgé le
      //    jeton du stockage — en cas d'échec réseau, l'utilisateur se
      //    reconnectera simplement (le jeton serveur reste valide 7 j).
      const legacyToken = takeLegacyBuyerToken();
      if (legacyToken !== null) {
        try {
          const buyer = await restoreBuyerSession(legacyToken);
          if (!cancelled) {
            writeBuyerSession({ buyer });
            return; // Session migrée et validée — rien d'autre à faire.
          }
          return;
        } catch {
          // Jeton legacy expiré/révoqué ou réseau KO : pas de session à
          // restaurer (l'indice profil legacy est déjà géré à l'étape 2
          // uniquement s'il reste quelque chose de valide).
          if (!cancelled && legacyToken !== null) {
            clearBuyerSession();
          }
          return;
        }
      }

      // 2. Restauration standard : un indice profil existe → validation
      //    silencieuse contre le cookie (401 → purge ; succès → rafraîchi).
      if (readBuyerSession() === null) return;
      try {
        const buyer = await fetchBuyerProfile();
        if (!cancelled) writeBuyerSession({ buyer });
      } catch (error) {
        if (!cancelled && error instanceof ApiError && error.status === 401) {
          clearBuyerSession();
        }
      }
    };

    void restore();

    return () => {
      cancelled = true;
    };
  }, []);

  const signIn = useCallback((next: BuyerSessionState) => {
    writeBuyerSession(next);
  }, []);

  const signOut = useCallback(async () => {
    clearBuyerSession();
    try {
      // Révocation serveur + expiration du cookie HttpOnly (#8096).
      await logoutBuyer();
    } catch {
      // Révocation best-effort : la session locale est déjà purgée.
    }
  }, []);

  const handleUnauthorized = useCallback((error: unknown): boolean => {
    if (error instanceof ApiError && error.status === 401) {
      clearBuyerSession();
      return true;
    }
    return false;
  }, []);

  return { ready, session, signIn, signOut, handleUnauthorized };
}
