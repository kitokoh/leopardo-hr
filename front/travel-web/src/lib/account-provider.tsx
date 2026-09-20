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
 * navigateur. Depuis #7841 (pattern #1299 de front/web), le token Sanctum du
 * guard DÉDIÉ `travel_customer` vit dans un cookie httpOnly posé par les
 * route handlers Next (`account/login` / `account/register`) : le JS de la
 * page ne voit jamais le token. La session est validée au montage via
 * `/account/me` (cookie envoyé automatiquement, Bearer injecté par le proxy
 * côté serveur) ; une session révoquée/expirée est purgée silencieusement.
 */

/**
 * #7841 — ancienne clé localStorage du token (héritage #7739). Le token n'y
 * est PLUS jamais écrit ; toute valeur résiduelle est purgée au chargement et
 * au logout pour éliminer les tokens legacy exposés au JS.
 */
const LEGACY_TOKEN_STORAGE_KEY = "travel-account-token";

type AccountContextValue = {
  /** null tant que l'état initial (cookie de session + /me) n'est pas résolu. */
  ready: boolean;
  account: CustomerAccount | null;
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
  login: async () => Promise.reject(new Error("AccountProvider missing")),
  register: async () => Promise.reject(new Error("AccountProvider missing")),
  logout: async () => undefined,
});

function purgeLegacyToken(): void {
  try {
    localStorage.removeItem(LEGACY_TOKEN_STORAGE_KEY);
  } catch {
    // Stockage indisponible (navigation privée) : rien à purger.
  }
}

export function AccountProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [account, setAccount] = useState<CustomerAccount | null>(null);

  useEffect(() => {
    // #7841 — purge du token legacy AVANT tout : il ne doit plus exister de
    // copie du token lisible par le JS (XSS), même issue d'une session #7739.
    purgeLegacyToken();

    let cancelled = false;
    // La session (cookie httpOnly) est opaque pour le client : seul `/me`
    // dit si elle existe encore. 401 = pas de session, résolu en anonyme.
    fetchAccount()
      .then((profile) => {
        if (!cancelled) setAccount(profile);
      })
      .catch(() => {
        if (!cancelled) setAccount(null);
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
    setAccount(payload.account);
    return payload.account;
  }, []);

  const register = useCallback(
    async (input: { name: string; email: string; phone?: string; password: string }) => {
      const payload = await registerAccount(input);
      setAccount(payload.account);
      return {
        account: payload.account,
        claimedBookings: payload.claimed_bookings ?? 0,
      };
    },
    [],
  );

  const logout = useCallback(async () => {
    setAccount(null);
    purgeLegacyToken();
    try {
      // Le route handler révoque le token backend puis supprime le cookie
      // httpOnly. Session déjà invalide côté serveur : l'état local est
      // de toute façon purgé.
      await logoutAccount();
    } catch {
      // Best-effort : la déconnexion locale ne dépend pas du réseau.
    }
  }, []);

  const value = useMemo(
    () => ({ ready, account, login, register, logout }),
    [ready, account, login, register, logout],
  );

  return <AccountContext.Provider value={value}>{children}</AccountContext.Provider>;
}

export function useAccount(): AccountContextValue {
  return useContext(AccountContext);
}
