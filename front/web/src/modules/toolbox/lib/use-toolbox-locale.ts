'use client';

import { useEffect, useState } from 'react';
import { normalizeLocale, type AppLocale } from '@/lib/locale-core';

/**
 * Locale courante de la Toolbox, côté client.
 *
 * La Toolbox est rendue côté serveur avec la locale résolue depuis l'en-tête
 * `x-vitrine-lang` (voir `app/toolbox/layout.tsx`). Une frontière d'erreur est
 * un composant CLIENT : elle n'a pas accès à cet en-tête. La locale est donc
 * relue depuis `<html lang>` — la valeur que le layout a déjà posée — plutôt
 * que devinée ou figée en français.
 *
 * Le premier rendu utilise `fr` (valeur par défaut du module) puis se corrige
 * dans un `useEffect` : lire `document` pendant le rendu casserait le rendu
 * serveur de la frontière.
 */
export function useToolboxLocale(): AppLocale {
  const [locale, setLocale] = useState<AppLocale>('fr');

  useEffect(() => {
    const lang = document.documentElement.lang;
    if (lang) setLocale(normalizeLocale(lang));
  }, []);

  return locale;
}
