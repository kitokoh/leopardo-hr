"use client";

/**
 * Hook session acheteur (#7814) — état React synchronisé avec le
 * localStorage via useSyncExternalStore (même pattern que useCart) :
 * même onglet via le CustomEvent émis par writeBuyerSession/clear, autres
 * onglets via l'événement natif `storage`.
 */

import { useCallback, useSyncExternalStore } from "react";

import { ApiError, logoutBuyer } from "@/lib/api";
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

  const signIn = useCallback((next: BuyerSessionState) => {
    writeBuyerSession(next);
  }, []);

  const signOut = useCallback(async () => {
    const current = readBuyerSession();
    clearBuyerSession();
    if (current) {
      try {
        await logoutBuyer(current.token);
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
