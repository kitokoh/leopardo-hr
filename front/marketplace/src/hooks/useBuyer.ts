"use client";

/**
 * Hook session acheteur (#7814, migrée #8022) — état React synchronisé avec
 * le localStorage via useSyncExternalStore (même pattern que useCart) :
 * même onglet via le CustomEvent émis par writeBuyerSession/clear, autres
 * onglets via l'événement natif `storage`.
 *
 * DEPUIS #8022 : le jeton de session n'existe plus côté client (cookie
 * httpOnly) — le cache local ne porte que le PROFIL. Comme un profil caché
 * peut survivre à l'expiration du cookie (7 j, #7979), la session est
 * re-validée UNE fois par chargement de page via `GET /account/me`
 * (single-flight module-level) : 401 → purge du cache ; succès → profil
 * rafraîchi.
 */

import { useCallback, useEffect, useSyncExternalStore } from "react";

import { ApiError, fetchBuyerProfile, logoutBuyer } from "@/lib/api";
import {
  BUYER_EVENT,
  BUYER_STORAGE_KEY,
  clearBuyerSession,
  readBuyerSession,
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

/**
 * Validation single-flight (#8022) : une seule requête `/account/me` par
 * chargement de page, quel que soit le nombre de composants abonnés au
 * hook. Sans profil caché, rien à valider : le login écrit toujours le
 * profil, donc « pas de profil » = état affiché déconnecté même si un
 * cookie survivait (cas rare — effacement manuel du stockage).
 */
let validationPromise: Promise<void> | null = null;

function validateSessionOnce(): Promise<void> {
  if (validationPromise === null) {
    validationPromise = (async () => {
      if (readBuyerSession() === null) return;
      try {
        const buyer = await fetchBuyerProfile();
        writeBuyerSession({ buyer });
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          clearBuyerSession();
        }
        // Erreur réseau/5xx : on conserve le cache (tolérance hors-ligne) —
        // la prochaine requête authentifiée tranchera via handleUnauthorized.
      }
    })();
  }
  return validationPromise;
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

  useEffect(() => {
    void validateSessionOnce();
  }, []);

  const signIn = useCallback((next: BuyerSessionState) => {
    writeBuyerSession(next);
  }, []);

  const signOut = useCallback(async () => {
    const hadSession = readBuyerSession() !== null;
    clearBuyerSession();
    if (hadSession) {
      try {
        // Révocation backend + suppression du cookie httpOnly (route handler).
        await logoutBuyer();
      } catch {
        // Révocation best-effort : la session locale est déjà purgée.
      }
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
