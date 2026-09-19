"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";

import { fetchAccount, loginAccount, logoutAccount, registerAccount } from "@/lib/api";
import type { CustomerAccount } from "@/lib/types";

/**
 * Compte client grand public (issue #7739) — état d'authentification côté
 * navigateur. Le token Sanctum du guard DÉDIÉ `travel_customer` est conservé
 * en localStorage (site public sans cookie de session) et validé au montage
 * via `/account/me` : un token révoqué/expiré est purgé silencieusement.
 */

const TOKEN_STORAGE_KEY = "travel-account-token";

type AccountContextValue = {
  /** null tant que l'état initial (localStorage + /me) n'est pas résolu. */
  ready: boolean;
  account: CustomerAccount | null;
  token: string | null;
  login: (email: string, password: string) => Promise<CustomerAccount>;
  register: (input: {
    name: string;
    email: string;
    phone?: string;
    password: string;
  }) => Promise<{ account: CustomerAccount; claimedBookings: number }>;
  logout: () => Promise<void>;
};

const AccountContext = createContext<AccountContextValue>({
  ready: false,
  account: null,
  token: null,
  login: async () => Promise.reject(new Error("AccountProvider missing")),
  register: async () => Promise.reject(new Error("AccountProvider missing")),
  logout: async () => undefined,
});

function readStoredToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_STORAGE_KEY);
  } catch {
    return null;
  }
}

function storeToken(token: string | null): void {
  try {
    if (token === null) {
      localStorage.removeItem(TOKEN_STORAGE_KEY);
    } else {
      localStorage.setItem(TOKEN_STORAGE_KEY, token);
    }
  } catch {
    // Stockage indisponible (navigation privée) : session en mémoire seule.
  }
}

export function AccountProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [account, setAccount] = useState<CustomerAccount | null>(null);

  useEffect(() => {
    const stored = readStoredToken();
    if (!stored) {
      setReady(true);
      return;
    }

    let cancelled = false;
    fetchAccount(stored)
      .then((profile) => {
        if (cancelled) return;
        setToken(stored);
        setAccount(profile);
      })
      .catch(() => {
        if (cancelled) return;
        storeToken(null);
      })
      .finally(() => {
        if (!cancelled) setReady(true);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const payload = await loginAccount({ email, password });
    storeToken(payload.token);
    setToken(payload.token);
    setAccount(payload.account);
    return payload.account;
  }, []);

  const register = useCallback(
    async (input: { name: string; email: string; phone?: string; password: string }) => {
      const payload = await registerAccount(input);
      storeToken(payload.token);
      setToken(payload.token);
      setAccount(payload.account);
      return {
        account: payload.account,
        claimedBookings: payload.claimed_bookings ?? 0,
      };
    },
    [],
  );

  const logout = useCallback(async () => {
    const current = token;
    storeToken(null);
    setToken(null);
    setAccount(null);
    if (current) {
      try {
        await logoutAccount(current);
      } catch {
        // Token déjà invalide côté serveur : la session locale est purgée.
      }
    }
  }, [token]);

  const value = useMemo(
    () => ({ ready, account, token, login, register, logout }),
    [ready, account, token, login, register, logout],
  );

  return <AccountContext.Provider value={value}>{children}</AccountContext.Provider>;
}

export function useAccount(): AccountContextValue {
  return useContext(AccountContext);
}
