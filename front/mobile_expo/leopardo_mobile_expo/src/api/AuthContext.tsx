import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { fetchMe, login as apiLogin, logout as apiLogout, restoreSession, verifyTwoFactor } from '../api/auth';
import { setUnauthorizedHandler } from '../api/client';
import type { Employee } from '../models/employee';

type AuthContextValue = {
  employee: Employee | null;
  booting: boolean;
  pendingChallenge: string | null;
  signIn: (email: string, password: string) => Promise<'authenticated' | 'mfa_required'>;
  submitTwoFactor: (code: string) => Promise<void>;
  cancelTwoFactor: () => void;
  signOut: () => Promise<void>;
  refreshProfile: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [employee, setEmployee] = useState<Employee | null>(null);
  const [booting, setBooting] = useState(true);
  const [pendingChallenge, setPendingChallenge] = useState<string | null>(null);

  // Restauration de session au demarrage.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const restored = await restoreSession();
      if (!cancelled) {
        setEmployee(restored);
        setBooting(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  // Un 401 sur n'importe quel appel ramene a l'ecran de connexion.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setEmployee(null);
      setPendingChallenge(null);
    });
    return () => setUnauthorizedHandler(null);
  }, []);

  const signIn = useCallback(async (email: string, password: string) => {
    const result = await apiLogin(email, password);
    if (result.kind === 'mfa_required') {
      setPendingChallenge(result.challengeToken);
      return 'mfa_required' as const;
    }
    setEmployee(result.employee);
    return 'authenticated' as const;
  }, []);

  const submitTwoFactor = useCallback(
    async (code: string) => {
      if (!pendingChallenge) throw new Error('Aucun challenge 2FA en cours.');
      const profile = await verifyTwoFactor(pendingChallenge, code);
      setEmployee(profile);
      setPendingChallenge(null);
    },
    [pendingChallenge],
  );

  const cancelTwoFactor = useCallback(() => setPendingChallenge(null), []);

  const signOut = useCallback(async () => {
    await apiLogout();
    setEmployee(null);
    setPendingChallenge(null);
  }, []);

  const refreshProfile = useCallback(async () => {
    const profile = await fetchMe();
    setEmployee(profile);
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      employee,
      booting,
      pendingChallenge,
      signIn,
      submitTwoFactor,
      cancelTwoFactor,
      signOut,
      refreshProfile,
    }),
    [employee, booting, pendingChallenge, signIn, submitTwoFactor, cancelTwoFactor, signOut, refreshProfile],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth doit etre utilise dans un AuthProvider.');
  return context;
}
