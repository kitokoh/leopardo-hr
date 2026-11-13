'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import {
  SHARE_PARAM,
  buildShareUrl,
  decodeToolState,
  mergeWithDefaults,
  type ToolStateValues,
} from './share';

/**
 * État de formulaire synchronisé avec l'URL (`?s=…`).
 *
 * Permet à un visiteur de **partager un calcul par lien** sans compte : l'URL
 * porte les entrées, le calcul est rejoué localement à l'ouverture.
 *
 * Choix d'implémentation :
 * - l'URL est lue **au montage** (jamais au rendu serveur) : `window` n'existe
 *   pas côté serveur, et lire l'URL au premier rendu client casserait
 *   l'hydratation ;
 * - l'URL est écrite avec `history.replaceState` (pas `pushState`) : partager
 *   ne doit pas empiler une entrée d'historique par frappe ;
 * - l'écriture n'a lieu qu'après une **première modification** : ouvrir un outil
 *   ne pollue pas l'URL avec les valeurs par défaut.
 */
export function useToolState(
  defaults: ToolStateValues,
): [ToolStateValues, (key: string, value: string) => void] {
  const [values, setValues] = useState<ToolStateValues>(defaults);
  const defaultsRef = useRef(defaults);
  const hydrated = useRef(false);
  const dirty = useRef(false);

  // Hydratation depuis l'URL — une seule fois, après le montage.
  useEffect(() => {
    if (hydrated.current) return;
    hydrated.current = true;

    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const decoded = decodeToolState(params.get(SHARE_PARAM));
    if (decoded) setValues(mergeWithDefaults(decoded, defaultsRef.current));
  }, []);

  // Réécriture de l'URL — uniquement après une saisie de l'utilisateur.
  useEffect(() => {
    if (!dirty.current) return;
    if (typeof window === 'undefined') return;

    try {
      const url = buildShareUrl(window.location.href, values);
      window.history.replaceState(window.history.state, '', url);
    } catch {
      // URL non constructible : l'état reste en mémoire, rien n'échoue.
    }
  }, [values]);

  const setValue = useCallback((key: string, value: string) => {
    dirty.current = true;
    setValues((previous) => ({ ...previous, [key]: value }));
  }, []);

  return [values, setValue];
}
