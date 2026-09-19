"use client";

/**
 * Hook session compte acheteur (#7814) — même mécanique que useCart :
 * useSyncExternalStore sur localStorage (CustomEvent même onglet +
 * `storage` multi-onglets).
 */

import { useSyncExternalStore } from "react";

import { useHydrated } from "@/hooks/useCart";
import {
  ACCOUNT_EVENT,
  ACCOUNT_STORAGE_KEY,
  readSession,
  type AccountSession,
} from "@/lib/account";

function subscribe(onStoreChange: () => void): () => void {
  const onStorage = (event: StorageEvent) => {
    if (event.key === null || event.key === ACCOUNT_STORAGE_KEY) onStoreChange();
  };
  window.addEventListener(ACCOUNT_EVENT, onStoreChange);
  window.addEventListener("storage", onStorage);
  return () => {
    window.removeEventListener(ACCOUNT_EVENT, onStoreChange);
    window.removeEventListener("storage", onStorage);
  };
}

let snapshotRaw: string | null = null;
let snapshotSession: AccountSession | null = null;

function getSnapshot(): AccountSession | null {
  let raw: string | null = null;
  try {
    raw = window.localStorage.getItem(ACCOUNT_STORAGE_KEY);
  } catch {
    raw = null;
  }
  if (raw !== snapshotRaw) {
    snapshotRaw = raw;
    snapshotSession = raw === null ? null : readSession();
  }
  return snapshotSession;
}

function getServerSnapshot(): AccountSession | null {
  return null;
}

export function useAccount(): {
  ready: boolean;
  session: AccountSession | null;
} {
  const ready = useHydrated();
  const session = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  return { ready, session };
}
