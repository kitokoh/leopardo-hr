'use client';

import { createContext, useContext, type ReactNode } from 'react';

/**
 * Locale SSR (Accept-Language) propagée du RootLayout (server component)
 * vers les client components de la vitrine.
 *
 * Bug vitrine 2026-08-15 : useVitrineLocale initialisait son état en 'fr'
 * côté serveur ET côté client, puis l'effet de synchro basculait vers la
 * préférence réelle (navigator.language / localStorage) APRÈS hydratation —
 * pour tout visiteur non-FR le premier rendu client ne matchait pas le SSR
 * (erreur React #418 → interactivité morte) et provoquait un re-rendu
 * complet (éléments détachés pendant les interactions).
 *
 * Désormais le premier rendu client utilise la MÊME langue que le SSR
 * (celle du HTML rendu), donc l'hydratation matche ; la préférence stockée
 * n'est appliquée qu'après montage, dans l'effet de useVitrineLocale.
 */
const LocaleSsrContext = createContext<string | null>(null);

export function LocaleSsrProvider({ lang, children }: { lang: string; children: ReactNode }) {
  return <LocaleSsrContext.Provider value={lang}>{children}</LocaleSsrContext.Provider>;
}

export function useSsrLang(): string {
  return useContext(LocaleSsrContext) ?? 'fr';
}
