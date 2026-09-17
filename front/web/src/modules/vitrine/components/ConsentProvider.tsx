'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import {
  CONSENT_CHANGED_EVENT,
  type ConsentPayload,
  type ConsentState,
  readConsent,
  writeConsent,
} from '@/modules/vitrine/lib/consent';

/**
 * État de consentement partagé (issue #7593).
 *
 * L'état initial est `null` (aucun choix) : il est relu au montage, côté
 * client, pour ne jamais faire dépendre le HTML servi d'un cookie (et éviter
 * toute désynchronisation d'hydratation).
 */

interface ConsentContextValue {
  /** `null` tant que le visiteur n'a pas exprimé de choix. */
  state: ConsentState | null;
  /** Le bandeau doit-il être visible ? */
  isBannerVisible: boolean;
  /** Le panneau de préférences est-il ouvert (personnalisation ou « gérer ») ? */
  isPreferencesOpen: boolean;
  decide: (payload: ConsentPayload) => void;
  openPreferences: () => void;
  closePreferences: () => void;
}

const ConsentContext = createContext<ConsentContextValue | null>(null);

export function ConsentProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<ConsentState | null>(null);
  const [isLoaded, setIsLoaded] = useState(false);
  const [isPreferencesOpen, setIsPreferencesOpen] = useState(false);

  useEffect(() => {
    setState(readConsent());
    setIsLoaded(true);

    const onConsentChanged = (event: Event) => {
      setState((event as CustomEvent<ConsentState>).detail);
      setIsPreferencesOpen(false);
    };

    window.addEventListener(CONSENT_CHANGED_EVENT, onConsentChanged);
    return () => window.removeEventListener(CONSENT_CHANGED_EVENT, onConsentChanged);
  }, []);

  const decide = useCallback((payload: ConsentPayload) => {
    setState(writeConsent(payload));
    setIsPreferencesOpen(false);
  }, []);

  const value = useMemo<ConsentContextValue>(
    () => ({
      state,
      // Rien n'est affiché avant la lecture du cookie : sinon le bandeau
      // clignote pour les visiteurs ayant déjà choisi.
      isBannerVisible: isLoaded && state === null,
      isPreferencesOpen,
      decide,
      openPreferences: () => setIsPreferencesOpen(true),
      closePreferences: () => setIsPreferencesOpen(false),
    }),
    [state, isLoaded, isPreferencesOpen, decide],
  );

  return <ConsentContext.Provider value={value}>{children}</ConsentContext.Provider>;
}

/** Accès à l'état de consentement. Renvoie un contexte inerte hors provider. */
export function useConsent(): ConsentContextValue {
  const context = useContext(ConsentContext);
  if (!context) {
    return {
      state: null,
      isBannerVisible: false,
      isPreferencesOpen: false,
      decide: () => undefined,
      openPreferences: () => undefined,
      closePreferences: () => undefined,
    };
  }
  return context;
}
